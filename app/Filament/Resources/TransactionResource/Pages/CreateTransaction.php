<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    // Logic: Habis create, balik ke halaman index (List)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function afterCreate(): void
    {
        // Ambil data transaksi yang barusan dibuat
        $transaction = $this->record;

        // Cek kalau user langsung pilih APPROVED
        if ($transaction->status === 'APPROVED') {
            // Panggil fungsi matematika yang ada di Model tadi
            $transaction->applyStockMutation();
        }
    }
}