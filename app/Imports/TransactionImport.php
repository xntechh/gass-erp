<?php

namespace App\Imports;

use App\Models\Department;
use App\Models\Item;
use App\Models\Transaction;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class TransactionImport implements ToCollection, WithHeadingRow
{
    public function collection(Collection $rows): void
    {
        $groupedRows = [];

        foreach ($rows as $index => $row) {
            $groupKey = $this->normalizeValue($row, ['batch', 'kode_transaksi', 'no_transaksi']);

            if ($groupKey === null) {
                $groupKey = 'row-' . $index;
            }

            $groupedRows[$groupKey][] = $row;
        }

        foreach ($groupedRows as $groupRows) {
            $this->importTransactionGroup($groupRows);
        }
    }

    private function importTransactionGroup(array $rows): void
    {
        $header = $rows[0];

        $warehouse = $this->resolveWarehouse($header);
        $department = $this->resolveDepartment($header);

        $trxDate = $this->parseDate($this->normalizeValue($header, ['trx_date', 'tanggal']));
        $type = $this->normalizeType($this->normalizeValue($header, ['type', 'tipe']));
        $status = $this->normalizeStatus($this->normalizeValue($header, ['status']));
        $category = $this->normalizeCategory($this->normalizeValue($header, ['category', 'kategori']));
        $description = $this->normalizeValue($header, ['description', 'keterangan', 'deskripsi']);

        DB::transaction(function () use ($rows, $warehouse, $department, $trxDate, $type, $status, $category, $description) {
            $transaction = Transaction::create([
                'warehouse_id' => $warehouse->id,
                'department_id' => $department?->id,
                'trx_date' => $trxDate,
                'type' => $type,
                'status' => $status,
                'category' => $category,
                'description' => $description,
            ]);

            foreach ($rows as $row) {
                $item = $this->resolveItem($row);
                $quantity = (int) ($row['quantity'] ?? $row['qty'] ?? 0);
                $price = (float) ($row['price'] ?? $row['harga'] ?? 0);

                if ($quantity <= 0) {
                    throw new \RuntimeException('Qty wajib diisi dan lebih dari 0.');
                }

                $transaction->details()->create([
                    'item_id' => $item->id,
                    'quantity' => $quantity,
                    'price' => $price,
                ]);
            }

            if ($transaction->status === 'APPROVED') {
                $transaction->loadMissing(['details.item']);
                $transaction->applyStockMutation();

                if ($transaction->type === 'IN') {
                    $transaction->updateMovingAverage();
                }
            }
        });
    }

    private function normalizeValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function parseDate(?string $value): Carbon
    {
        if ($value === null) {
            return now();
        }

        if (is_numeric($value)) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject($value));
        }

        return Carbon::parse($value);
    }

    private function resolveWarehouse(array $row): Warehouse
    {
        $warehouseName = $this->normalizeValue($row, ['warehouse', 'gudang']);
        $warehouseCode = $this->normalizeValue($row, ['warehouse_code', 'kode_gudang']);

        $query = Warehouse::query();

        if ($warehouseCode) {
            $query->where('code', $warehouseCode);
        } elseif ($warehouseName) {
            $query->where('name', $warehouseName);
        }

        $warehouse = $query->first();

        if (! $warehouse) {
            throw new \RuntimeException('Gudang tidak ditemukan pada file Excel.');
        }

        return $warehouse;
    }

    private function resolveDepartment(array $row): ?Department
    {
        $departmentName = $this->normalizeValue($row, ['department', 'departemen']);

        if (! $departmentName) {
            return null;
        }

        return Department::where('name', $departmentName)->first();
    }

    private function resolveItem(array $row): Item
    {
        $itemCode = $this->normalizeValue($row, ['item_code', 'kode_barang', 'kode_item']);
        $itemName = $this->normalizeValue($row, ['item_name', 'nama_barang', 'barang']);

        $item = null;

        if ($itemCode) {
            $item = Item::where('code', $itemCode)->first();
        }

        if (! $item && $itemName) {
            $item = Item::where('name', $itemName)->first();
        }

        if (! $item) {
            throw new \RuntimeException('Barang tidak ditemukan pada file Excel.');
        }

        return $item;
    }

    private function normalizeType(?string $value): string
    {
        $normalized = strtoupper((string) $value);

        if (in_array($normalized, ['IN', 'MASUK', 'MASUK (+)'], true)) {
            return 'IN';
        }

        if (in_array($normalized, ['OUT', 'KELUAR', 'KELUAR (-)'], true)) {
            return 'OUT';
        }

        throw new \RuntimeException('Tipe transaksi wajib IN atau OUT.');
    }

    private function normalizeStatus(?string $value): string
    {
        $normalized = strtoupper((string) $value);

        if ($normalized === 'APPROVED') {
            return 'APPROVED';
        }

        return 'DRAFT';
    }

    private function normalizeCategory(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $normalized = strtoupper(str_replace([' ', '-'], '_', $value));

        return $normalized;
    }
}
