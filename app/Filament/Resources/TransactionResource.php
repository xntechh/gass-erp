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
use Filament\Tables\Columns\TextColumn;

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
                Forms\Components\Section::make('Informasi Transaksi')
                    ->schema([
                        // 1. Nomor Transaksi (Handle by Model)
                        Forms\Components\TextInput::make('code')
                            ->label('No. Transaksi')
                            ->placeholder('Otomatis: TRX/IN/2025/...')
                            ->disabled()
                            ->dehydrated(),

                        // 2. Tanggal Transaksi
                        Forms\Components\DatePicker::make('trx_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        // 3. Tipe Gerakan
                        Forms\Components\Select::make('type')
                            ->label('Tipe Gerakan')
                            ->options([
                                'IN' => 'Barang Masuk (+)',
                                'OUT' => 'Barang Keluar (-)',
                            ])
                            ->required()
                            ->live()
                            // Reset kategori kalau tipe berubah biar gak ngaco
                            ->afterStateUpdated(fn(Forms\Set $set) => $set('category', null)),

                        // 4. Alasan / Kategori
                        Forms\Components\Select::make('category')
                            ->label('Alasan / Kategori')
                            ->options(fn(Get $get) => match ($get('type')) {
                                'IN' => [
                                    'PURCHASE' => '📦 Pembelian Vendor',
                                    'RETURN_IN' => '🔄 Retur dari User',
                                    'ADJUSTMENT_IN' => '⚖️ Koreksi Stok (Tambah)',
                                ],
                                'OUT' => [
                                    'USAGE' => '🛠️ Pemakaian Normal',
                                    'CSR' => '🎁 Sumbangan / CSR',
                                    'SCRAP' => '🗑️ Pemusnahan (Rusak)',
                                    'RETURN_VENDOR' => '🔙 Retur ke Supplier',
                                ],
                                default => [],
                            })
                            ->required()
                            ->live(),

                        // 5. GUDANG (INI YANG TADI HILANG!)
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang Tujuan/Asal')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->dehydrated(),

                        // 6. DEPARTEMEN (Muncul hanya jika OUT & USAGE/CSR)
                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->label('Departemen Peminta')
                            ->searchable()
                            ->preload()
                            ->visible(
                                fn(Get $get) =>
                                $get('type') === 'OUT' && in_array($get('category'), ['USAGE', 'CSR'])
                            )
                            ->required(
                                fn(Get $get) =>
                                $get('type') === 'OUT' && $get('category') === 'USAGE'
                            ),

                        // 7. STATUS (Hanya Admin yang bisa Approve)
                        Forms\Components\Select::make('status')
                            ->options(function () {
                                $opts = ['DRAFT' => 'Draft (Simpan Saja)'];
                                if (Auth::user()->role === 'ADMIN') {
                                    $opts['APPROVED'] = 'Approved (Update Stok)';
                                }
                                return $opts;
                            })
                            ->default('DRAFT')
                            ->required()
                            ->disabled(
                                fn(string $operation): bool =>
                                Auth::user()->role !== 'ADMIN' && $operation === 'edit'
                            )
                            ->dehydrated(),

                        // 8. KETERANGAN
                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan / Catatan Tambahan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                // --- BAGIAN DETAIL (REPEATER) ---
                Forms\Components\Section::make('Daftar Barang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->columnSpan(2),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Jumlah')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(1),

                                Forms\Components\Placeholder::make('current_stock')
                                    ->label('Stok Saat Ini')
                                    ->content(fn($get) => \App\Models\Item::find($get('item_id'))?->stock ?? 0)
                                    ->visible(fn($get) => $get('../../type') === 'OUT'),
                            ])->columns(3)
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->fontFamily('mono'),
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
