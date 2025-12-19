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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Set;
use Filament\Forms\Get;

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
                            ->live()
                            // 👇 KUNCI: Kalau sudah ada isi di repeater, jangan kasih ganti gudang!
                            //>disabled(fn(Get $get) => count($get('details') ?? []) > 0)
                            ->afterStateUpdated(function ($state, Set $set) {
                                if (!$state) return;

                                $stokGudang = InventoryStock::where('warehouse_id', $state)->get();
                                $dataRepeater = $stokGudang->map(fn($stock) => [
                                    'item_id' => $stock->item_id,
                                    'system_qty' => $stock->quantity,
                                    'physical_qty' => $stock->quantity, // Default samain dulu biar gak capek ngetik
                                    'description' => null,
                                ])->toArray();

                                $set('details', $dataRepeater);
                            })
                            ->helperText('Hapus semua barang di bawah jika ingin mengganti gudang.'),

                        Forms\Components\DatePicker::make('opname_date')
                            ->label('Tanggal Audit')
                            ->required()
                            ->default(now()),

                        Forms\Components\TextInput::make('reason')
                            ->label('Alasan / Catatan / Nama Audit')
                            ->placeholder('Contoh: Stock Opname Akhir Tahun 2025')
                            ->required() // Supervisor harus jelas auditnya buat apa!
                            ->maxLength(255),

                        Forms\Components\Select::make('status')
                            ->options([
                                'DRAFT' => 'Draft (Proses Hitung)',
                                'PROCESSED' => 'Processed (Final & Update Stok)',
                            ])
                            ->default('DRAFT')
                            ->required()
                            // 👇 Cegah ganti status manual ke PROCESSED lewat Form jika bukan ADMIN
                            ->disabled(fn($operation) => $operation === 'edit' && auth()->user()->role !== 'ADMIN'),
                    ])->columns(3),

                Forms\Components\Section::make('Hasil Hitung Fisik')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->label('Barang')
                                    ->disabled() // Jangan boleh ganti barang kalau narik otomatis
                                    ->dehydrated() // Tetap kirim ID ke DB
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('system_qty')
                                    ->label('Sistem')
                                    ->numeric()
                                    ->readOnly(),

                                Forms\Components\TextInput::make('physical_qty')
                                    ->label('Fisik')
                                    ->numeric()
                                    ->required()
                                    ->live(onBlur: true), // Update selisih pas user pindah kolom

                                // 👇 FITUR BARU: Biar Auditor liat langsung selisihnya
                                Placeholder::make('variance_hint')
                                    ->label('Selisih')
                                    ->content(function (Get $get) {
                                        $diff = ($get('physical_qty') ?? 0) - ($get('system_qty') ?? 0);
                                        $color = $diff < 0 ? 'text-danger-600' : ($diff > 0 ? 'text-success-600' : 'text-gray-500');
                                        return new \Illuminate\Support\HtmlString("<span class='font-bold {$color}'>{$diff}</span>");
                                    }),

                                Forms\Components\TextInput::make('description')
                                    ->label('Keterangan Selisih')
                                    ->placeholder('Wajib isi jika ada selisih')
                                    ->required(fn(Get $get) => $get('physical_qty') != $get('system_qty'))
                                    ->columnSpanFull(),
                            ])
                            ->columns(5)
                            ->addable(false) // Matikan tambah manual biar gak ngaco dari gudang lain
                            ->reorderable(false)
                    ])
            ]);
    }
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('opname_date')->date(),
                Tables\Columns\TextColumn::make('warehouse.name'),
                Tables\Columns\TextColumn::make('reason')
                    ->label('Catatan Audit')
                    ->limit(30) // Potong teks kalau kepanjangan
                    ->tooltip(fn($state) => $state) // Munculin teks lengkap pas di-hover mouse
                    ->placeholder('Tidak ada catatan'), // Muncul kalau emang kosong
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
                        // 👇 KRUSIAL: Load relasi di awal biar gak 500 & gak lemot (N+1 Problem)
                        $record->load(['details.item.unit']);

                        $csvData = "LAPORAN HASIL STOCK OPNAME - GUDANG SENTUL\n";
                        $csvData .= "Nomor Dokumen: " . $record->code . "\n";
                        $csvData .= "Tanggal Audit: " . $record->opname_date . "\n";
                        $csvData .= "Keterangan: " . ($record->reason ?? '-') . "\n\n";

                        $csvData .= "Kode Barang,Nama Barang,Satuan,Stok Sistem,Stok Fisik,Harga Modal,Total Nilai Fisik (Rp),Selisih Qty,Selisih Rupiah\n";

                        foreach ($record->details as $detail) {
                            // 👇 PROTEKSI 1: Cek apakah item-nya ada? Kalau gak ada, skip baris ini.
                            if (!$detail->item) {
                                continue;
                            }

                            $selisih = (int)$detail->physical_qty - (int)$detail->system_qty;
                            $harga = (float)($detail->item->avg_cost ?? 0);
                            $selisihRupiah = $selisih * $harga;
                            $totalNilaiFisik = (int)$detail->physical_qty * $harga;

                            // 👇 PROTEKSI 2: Pakai Nullsafe operator (?->) buat jaga-jaga unit kosong
                            $csvData .= sprintf(
                                "%s,\"%s\",%s,%d,%d,%d,%.2f,%d,%.2f\n",
                                $detail->item->code,
                                str_replace('"', '""', $detail->item->name), // Escape tanda kutip di nama barang
                                $detail->item->unit?->name ?? '-',
                                $detail->system_qty,
                                $detail->physical_qty,
                                $harga,
                                $totalNilaiFisik,
                                $selisih,
                                $selisihRupiah
                            );
                        }

                        return response()->streamDownload(function () use ($csvData) {
                            echo $csvData;
                        }, 'Laporan_SO_' . ($record->code ?? 'Export') . '.csv');
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
