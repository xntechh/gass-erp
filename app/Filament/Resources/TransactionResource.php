<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';
    protected static ?string $navigationLabel = 'Transaksi Barang';

    protected static ?string $navigationGroup = 'Aktivitas Gudang';
    protected static ?int $navigationSort = 1; // Biar dia muncul paling atas

    public static function getNavigationBadge(): ?string
    {
        // Hitung transaksi yang statusnya DRAFT
        return static::getModel()::where('status', 'DRAFT')->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        // Warna Kuning (Warning) atau Biru (Info)
        // Logic: Kalau ada draft, warnanya kuning. Kalau 0, gak muncul (null).
        return static::getModel()::where('status', 'DRAFT')->exists() ? 'warning' : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'description']; // Cari berdasarkan No Transaksi atau Keterangan
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Tipe' => $record->type,
            'Status' => $record->status,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- BAGIAN HEADER ---
                Forms\Components\Section::make('Informasi Transaksi')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('No. Transaksi')
                            ->default('TRX-' . random_int(100000, 999999)) // Auto-generate nomor acak
                            ->required()
                            ->readOnly(),

                        Forms\Components\DatePicker::make('trx_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now()),

                        Forms\Components\Select::make('type')
                            ->options([
                                'IN' => 'Barang Masuk',
                                'OUT' => 'Barang Keluar',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Forms\Set $set) => $set('category', null)), // Reset kategori kalau tipe berubah

                        // 2. KATEGORI TRANSAKSI (Anak - Logic Pro)
                        Forms\Components\Select::make('category')
                            ->label('Kategori / Alasan')
                            ->options(fn(Forms\Get $get) => match ($get('type')) {
                                'IN' => [
                                    'PURCHASE' => 'Pembelian Vendor',
                                    'RETURN_IN' => 'Retur dari User (Barang Balik)',
                                    'ADJUSTMENT_IN' => 'Koreksi Stok (Tambah)',
                                ],
                                'OUT' => [
                                    'USAGE' => 'Pemakaian User (Normal)',
                                    'CSR' => 'Sumbangan / CSR',
                                    'SCRAP' => 'Pemusnahan / Barang Rusak',
                                    'RETURN_VENDOR' => 'Retur ke Supplier',
                                ],
                                default => [],
                            })
                            ->live() // Live juga, karena nanti ngaruh ke validasi Dept/Supplier
                            ->required(),

                        Forms\Components\Group::make()
                            ->schema([

                                // INPUT DEPARTEMEN
                                Forms\Components\Select::make('department_id')
                                    ->relationship('department', 'name')
                                    ->label('Departemen Peminta')
                                    ->searchable()
                                    ->preload()
                                    // Muncul kalau Tipe OUT, TAPI bukan Pemusnahan/Retur Vendor
                                    ->visible(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'OUT' &&
                                            in_array($get('category'), ['USAGE', 'CSR'])
                                    )
                                    // Wajib kalau USAGE
                                    ->required(
                                        fn(Forms\Get $get) =>
                                        $get('type') === 'OUT' &&
                                            $get('category') === 'USAGE'
                                    ),
                            ])
                            ->columnSpanFull(),

                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang')
                            ->required(),

                        Forms\Components\Select::make('status')
                            ->options(function () {
                                // Opsi standar
                                $opts = [
                                    'DRAFT' => 'Draft (Simpan Saja)',
                                ];

                                // Kalau yang login ADMIN, baru kasih opsi APPROVED
                                if (Auth::user()->role === 'ADMIN') {
                                    $opts['APPROVED'] = 'Approved (Update Stok)';
                                }

                                return $opts;
                            })
                            ->default('DRAFT')
                            ->required()
                            // Tambahan: Kalau bukan Admin, matikan inputnya pas Edit (biar gak bisa utak-atik status)
                            ->disabled(fn() => Auth::user()->role !== 'ADMIN' && $form->getOperation() === 'edit'),

                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan / Catatan Tambahan')
                            ->placeholder('Contoh: Barang diambil oleh Pak Budi, atau No. Resi JNE: 12345')
                            ->rows(3) // Tinggi kotak 3 baris
                            ->columnSpanFull(), // Biar lebar dari kiri ke kanan
                    ])->columns(2),

                // --- BAGIAN DETAIL (REPEATER) ---
                Forms\Components\Section::make('Daftar Barang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship() // Ini magic-nya, otomatis simpan ke tabel details
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->columnSpan(2), // Lebar kolom

                                Forms\Components\TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('price')
                                    ->label('Harga Beli (Satuan)')
                                    ->prefix('Rp')
                                    ->numeric()
                                    // Cuma muncul & wajib kalau Tipe = IN (Barang Masuk)
                                    ->visible(fn(Forms\Get $get) => $get('../../type') === 'IN')
                                    ->required(fn(Forms\Get $get) => $get('../../type') === 'IN'),
                            ])
                            ->columns(3) // Layout 3 kolom
                            ->defaultItems(1) // Default ada 1 baris
                    ])

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')->searchable(),
                Tables\Columns\TextColumn::make('trx_date')->date(),
                Tables\Columns\BadgeColumn::make('type')
                    ->colors([
                        'success' => 'IN',
                        'danger' => 'OUT',
                    ]),
                Tables\Columns\TextColumn::make('warehouse.name'),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'DRAFT',
                        'success' => 'APPROVED',
                    ]),
                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->colors([
                        'success' => 'PURCHASE',
                        'info' => 'USAGE',
                        'warning' => 'CSR',
                        'danger' => 'SCRAP',
                    ]),
            ])
            ->filters([])

            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Download Excel') // Label tombol
                    ->color('success') // Warna Hijau
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable() // Ambil kolom sesuai tampilan tabel
                            ->withFilename('Laporan_Transaksi_' . date('Y-m-d')),
                    ]),
            ])

            ->actions([

                // 1. Tombol EDIT (Cuma muncul kalau masih DRAFT)
                Tables\Actions\EditAction::make()
                    ->hidden(fn(Transaction $record) => $record->status === 'APPROVED'),
                // Artinya: Sembunyikan kalau statusnya APPROVED

                // 2. Tombol DELETE (Haram hapus kalau udah Approved)
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(Transaction $record) => $record->status === 'APPROVED'),

                // 3. Tombol PRINT (Tetap ada)
                Tables\Actions\Action::make('print_bast')
                    ->label('Cetak BAST')
                    ->icon('heroicon-o-printer')
                    ->color('info') // Warna Biru
                    ->url(fn(Transaction $record) => route('transactions.print', $record))
                    ->openUrlInNewTab(),  // Buka tab baru biar gak ganggu kerjaan
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
            'index' => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit' => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
