<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\StockOpname;
use App\Models\InventoryStock; // Import Model Stok
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get; // Buat baca inputan live
use Filament\Forms\Set; // Buat set nilai otomatis

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Stock Opname';

    protected static ?string $navigationGroup = 'Aktivitas Gudang';
    protected static ?int $navigationSort = 1; // Biar dia muncul paling atas

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Info Audit')
                    ->schema([
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang yang Diaudit')
                            ->required()
                            ->live() // Wajib live biar bisa trigger fungsi di bawah
                            ->afterStateUpdated(function ($state, Set $set) {
                                // 1. Cek apakah user sudah memilih gudang?
                                if ($state) {
                                    // 2. Ambil semua stok yg ada di gudang tersebut dari database
                                    $stokGudang = InventoryStock::where('warehouse_id', $state)->get();

                                    // 3. Format datanya biar sesuai sama bentuk Repeater
                                    $dataRepeater = $stokGudang->map(function ($stock) {
                                        return [
                                            'item_id' => $stock->item_id,      // ID Barang
                                            'system_qty' => $stock->quantity,  // Stok Komputer
                                            'physical_qty' => 0,               // Stok Fisik (Default 0 biar dihitung)
                                            'description' => null,             // Keterangan kosong
                                        ];
                                    })->toArray();

                                    // 4. Masukkan data ke Repeater 'details'
                                    $set('details', $dataRepeater);
                                }
                            }),

                        Forms\Components\DatePicker::make('opname_date')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reason')
                            ->label('Keterangan')
                            ->placeholder('Contoh: Audit Akhir Tahun'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'DRAFT' => 'Draft (Hitung Dulu)',
                                'PROCESSED' => 'Processed (Update Stok Resmi)',
                            ])
                            ->default('DRAFT')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Hasil Hitung Fisik')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->label('Barang')
                                    ->required()
                                    ->searchable()
                                    ->live() // Aktifkan Live update
                                    // SAAT BARANG DIPILIH, CARI STOK SISTEM OTOMATIS
                                    ->afterStateUpdated(function ($state, Set $set, Get $get) {
                                        $warehouseId = $get('../../warehouse_id');
                                        if ($warehouseId && $state) {
                                            $stock = InventoryStock::where('warehouse_id', $warehouseId)
                                                ->where('item_id', $state)
                                                ->first();
                                            // Isi kolom System Qty otomatis
                                            $set('system_qty', $stock ? $stock->quantity : 0);
                                        }
                                    }),

                                Forms\Components\TextInput::make('system_qty')
                                    ->label('Stok Komputer')
                                    ->readOnly() // Gak boleh diedit user
                                    ->required(),

                                Forms\Components\TextInput::make('physical_qty')
                                    ->label('Stok Fisik (Asli)')
                                    ->numeric()
                                    ->required(),

                                Forms\Components\TextInput::make('description')
                                    ->label('Alasan Selisih')
                                    ->placeholder('Cth: Barang Pecah / Expired')
                                    ->columnSpan(3),
                            ])
                            ->columns(3)
                            ->defaultItems(1)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('opname_date')->date(),
                Tables\Columns\TextColumn::make('warehouse.name'),
                Tables\Columns\TextColumn::make('reason'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors(['warning' => 'DRAFT', 'success' => 'PROCESSED']),
                Tables\Columns\TextColumn::make('accuracy')
                    ->label('Akurasi Item')
                    ->suffix('%') // Tambah % di belakang angka
                    ->color(fn($state) => $state < 90 ? 'danger' : 'success') // Merah kalau < 90%
                    ->sortable(false),
                Tables\Columns\TextColumn::make('total_valuation')
                    ->label('Total Aset Fisik')
                    ->money('IDR')
                    ->weight('bold') // Tebal biar kelihatan angka penting
                    ->sortable(false),
                // 👇 KOLOM BARU: TOTAL SELISIH RUPIAH 👇
                Tables\Columns\TextColumn::make('variance')
                    ->label('Selisih Nilai (Rp)')
                    ->money('IDR')
                    ->color(fn($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->sortable(false),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn(StockOpname $record) => $record->status === 'PROCESSED'),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(StockOpname $record) => $record->status === 'PROCESSED'),

                Tables\Actions\Action::make('download_report')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (StockOpname $record) {
                        // 1. Update Header CSV (Tambah kolom di paling kanan)
                        $csvData = "Kode Barang,Nama Barang,Satuan,Stok Sistem,Stok Fisik,Harga Modal,Total Nilai Fisik (Rp),Selisih Qty,Selisih Rupiah\n";

                        foreach ($record->details as $detail) {
                            $selisih = $detail->physical_qty - $detail->system_qty;
                            $harga = $detail->item->avg_cost ?? 0;
                            $selisihRupiah = $selisih * $harga;

                            // Hitung Total Nilai per baris
                            $totalNilaiFisik = $detail->physical_qty * $harga;

                            // 2. Update Isi CSV
                            $csvData .= sprintf(
                                "%s,\"%s\",%s,%d,%d,%d,%d,%d,%d\n", // Tambah %d buat angka baru
                                $detail->item->code,
                                $detail->item->name,
                                $detail->item->unit->name ?? '-',
                                $detail->system_qty,
                                $detail->physical_qty,
                                $harga,
                                $totalNilaiFisik, // <--- Data Baru Masuk Sini
                                $selisih,
                                $selisihRupiah
                            );
                        }

                        return response()->streamDownload(function () use ($csvData) {
                            echo $csvData;
                        }, 'Laporan_SO_' . $record->created_at->format('Y-m-d') . '.csv');
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit' => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
