<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StockOpnameResource\Pages;
use App\Models\StockOpname;
use App\Models\InventoryStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Support\HtmlString;

class StockOpnameResource extends Resource
{
    protected static ?string $model = StockOpname::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'Stock Opname';
    protected static ?string $navigationGroup = 'Aktivitas Gudang';
    protected static ?int $navigationSort = 1;

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
                            ->live()
                            // PERBAIKAN: Aktifkan disabled jika sudah ada detail barang agar data tidak tertimpa tidak sengaja
                            ->disabled(fn(Get $get) => count($get('details') ?? []) > 0)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (! $state) return;

                                // Ambil stok dari gudang terpilih
                                $stokGudang = InventoryStock::where('warehouse_id', $state)->get();

                                $dataRepeater = $stokGudang->map(fn($stock) => [
                                    'item_id'      => $stock->item_id,
                                    'system_qty'   => $stock->quantity,
                                    'physical_qty' => $stock->quantity, // Default disamakan
                                    'description'  => null,
                                ])->toArray();

                                $set('details', $dataRepeater);
                            })
                            ->helperText('Jika ingin mengganti gudang, hapus semua list barang di bawah terlebih dahulu.'),

                        Forms\Components\DatePicker::make('opname_date')
                            ->label('Tanggal Audit')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reason')
                            ->label('Catatan / Nama Audit')
                            ->placeholder('Contoh: Stock Opname Akhir Tahun 2025')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->options([
                                'DRAFT'     => 'Draft (Proses Hitung)',
                                'PROCESSED' => 'Processed (Final & Update Stok)',
                            ])
                            ->default('DRAFT')
                            ->required()
                            // Validasi: Hanya Admin yang boleh ubah status jadi PROCESSED di mode edit
                            ->disabled(fn(string $operation) => $operation === 'edit' && auth()->user()->role !== 'ADMIN'),
                    ])->columns(3),

                Forms\Components\Section::make('Hasil Hitung Fisik')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->label('Barang')
                                    ->disabled()
                                    ->dehydrated() // Penting: tetap kirim data ke DB meski disabled
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('system_qty')
                                    ->label('Sistem')
                                    ->numeric()
                                    ->readOnly(),

                                Forms\Components\TextInput::make('physical_qty')
                                    ->label('Fisik')
                                    ->numeric()
                                    ->required()
                                    ->live(onBlur: true), // Update realtime saat pindah kolom

                                // Visualisasi Selisih
                                Placeholder::make('variance_hint')
                                    ->label('Selisih')
                                    ->content(function (Get $get) {
                                        $sistem = (int) $get('system_qty');
                                        $fisik  = (int) $get('physical_qty');
                                        $diff   = $fisik - $sistem;

                                        $color = match (true) {
                                            $diff < 0 => 'text-danger-600',
                                            $diff > 0 => 'text-success-600',
                                            default   => 'text-gray-500',
                                        };

                                        return new HtmlString("<span class='font-bold {$color}'>{$diff}</span>");
                                    }),

                                Forms\Components\TextInput::make('description')
                                    ->label('Keterangan Selisih')
                                    ->placeholder('Wajib isi jika ada selisih')
                                    // Validasi: Wajib isi jika fisik != sistem
                                    ->required(fn(Get $get) => $get('physical_qty') != $get('system_qty'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(5)
                            ->addable(false)      // Tidak boleh tambah manual
                            ->reorderable(false)  // Tidak perlu digeser-geser
                            ->deletable(false)    // Sebaiknya tidak dihapus agar sesuai snapshot sistem
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('opname_date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reason')
                    ->label('Catatan Audit')
                    ->limit(30)
                    ->tooltip(fn($state) => $state),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'DRAFT' => 'warning',
                        'PROCESSED' => 'success',
                    }),

                Tables\Columns\TextColumn::make('accuracy')
                    ->label('Akurasi')
                    ->suffix('%')
                    ->color(fn($state) => $state < 90 ? 'danger' : 'success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_valuation')
                    ->label('Total Aset')
                    ->money('IDR')
                    ->toggleable(), // Bisa disembunyikan user

                Tables\Columns\TextColumn::make('variance')
                    ->label('Selisih (Rp)')
                    ->money('IDR')
                    ->color(fn($state) => $state < 0 ? 'danger' : ($state > 0 ? 'success' : 'gray'))
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // --- AKSI DOWNLOAD LAPORAN ---
                Tables\Actions\Action::make('download_report')
                    ->label('Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (StockOpname $record) {
                        return response()->streamDownload(function () use ($record) {
                            // Buka stream output
                            $handle = fopen('php://output', 'w');

                            // 1. Header Report
                            fputcsv($handle, ["LAPORAN HASIL STOCK OPNAME"]);
                            fputcsv($handle, ["Nomor Dokumen", $record->code ?? '-']);
                            fputcsv($handle, ["Tanggal Audit", $record->opname_date ?? '-']);
                            fputcsv($handle, ["Gudang", $record->warehouse->name ?? '-']);
                            fputcsv($handle, []); // Baris kosong

                            // 2. Header Table
                            fputcsv($handle, [
                                "Kode Barang",
                                "Nama Barang",
                                "Satuan",
                                "Stok Sistem",
                                "Stok Fisik",
                                "Selisih Qty",
                                "Harga Modal",
                                "Total Nilai Fisik",
                                "Selisih Rupiah"
                            ]);

                            // 3. Isi Data (Eager Loading biar cepat)
                            foreach ($record->details()->with('item.unit')->get() as $detail) {
                                $item = $detail->item;
                                if (! $item) continue;

                                $systemQty   = (float) $detail->system_qty;
                                $physicalQty = (float) $detail->physical_qty;
                                $avgCost     = (float) ($item->avg_cost ?? 0);
                                $selisihQty  = $physicalQty - $systemQty;

                                fputcsv($handle, [
                                    $item->code,
                                    $item->name,
                                    $item->unit?->name ?? '-',
                                    $systemQty,
                                    $physicalQty,
                                    $selisihQty,
                                    $avgCost,
                                    $physicalQty * $avgCost, // Total Nilai
                                    $selisihQty * $avgCost   // Selisih Rupiah
                                ]);
                            }

                            fclose($handle);
                        }, 'Laporan_SO_' . ($record->code ?? date('Ymd')) . '.csv');
                    })
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
            'index'  => Pages\ListStockOpnames::route('/'),
            'create' => Pages\CreateStockOpname::route('/create'),
            'edit'   => Pages\EditStockOpname::route('/{record}/edit'),
        ];
    }
}
