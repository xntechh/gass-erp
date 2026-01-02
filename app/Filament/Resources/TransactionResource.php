<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Models\Transaction;
use App\Models\InventoryStock; // Import Model Stok Gudang
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-right-start-on-rectangle';
    protected static ?string $navigationLabel = 'Transaksi Barang';
    protected static ?string $navigationGroup = 'Aktivitas Gudang';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', 'DRAFT')->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'description'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Transaksi')
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('No. Transaksi')
                            ->placeholder('Otomatis (TRX/...)')
                            ->disabled()
                            ->dehydrated(),

                        Forms\Components\DatePicker::make('trx_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\Select::make('type')
                            ->label('Tipe Gerakan')
                            ->options([
                                'IN' => 'Masuk (+)',
                                'OUT' => 'Keluar (-)',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn(Set $set) => $set('category', null)),

                        Forms\Components\Select::make('category')
                            ->label('Kategori')
                            ->options(fn(Get $get) => match ($get('type')) {
                                'IN' => [
                                    'PURCHASE'      => '📦 Pembelian Vendor',
                                    'RETURN_IN'     => '🔄 Retur dari User',
                                    'ADJUSTMENT_IN' => '⚖️ Koreksi Stok (+)',
                                ],
                                'OUT' => [
                                    'USAGE'         => '🛠️ Pemakaian Normal',
                                    'CSR'           => '🎁 Sumbangan / CSR',
                                    'SCRAP'         => '🗑️ Pemusnahan (Rusak)',
                                    'RETURN_VENDOR' => '🔙 Retur ke Supplier',
                                    'ADJUSTMENT_OUT' => '⚖️ Koreksi Stok (-)',
                                ],
                                default => [],
                            })
                            ->required()
                            ->live(),

                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->live() // Wajib Live agar repeater di bawah bisa baca ID Gudang
                            ->afterStateUpdated(function ($state, Set $set) {
                                // Opsional: Reset detail jika gudang berubah agar stok valid
                                // $set('details', []); 
                            }),

                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->label('Departemen Peminta')
                            ->searchable()
                            ->preload()
                            ->visible(fn(Get $get) => $get('type') === 'OUT' && in_array($get('category'), ['USAGE', 'CSR']))
                            ->required(fn(Get $get) => $get('type') === 'OUT' && $get('category') === 'USAGE'),

                        Forms\Components\Select::make('status')
                            ->options([
                                'DRAFT'    => 'Draft (Simpan Saja)',
                                'APPROVED' => 'Approved (Update Stok)',
                            ])
                            ->default('DRAFT')
                            ->required()
                            // Proteksi: Hanya Admin yang bisa ganti ke Approved saat Edit
                            ->disabled(fn(string $operation) => $operation === 'edit' && auth()->user()->role !== 'ADMIN'),

                        Forms\Components\Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Daftar Barang')
                    ->schema([
                        Forms\Components\Repeater::make('details')
                            ->relationship()
                            ->schema([
                                Forms\Components\Select::make('item_id')
                                    ->relationship('item', 'name')
                                    ->label('Barang')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->live() // Live agar bisa trigger pencarian stok
                                    ->columnSpan(2)
                                    // Validasi: Pastikan barang tidak ganda di satu form
                                    ->distinct(),

                                Forms\Components\TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->minValue(1)
                                    ->required()
                                    ->columnSpan(1),

                                // 👇 LOGIC BARU: Cek Stok per Gudang
                                Forms\Components\Placeholder::make('current_stock_info')
                                    ->label('Stok Tersedia')
                                    ->content(function (Get $get) {
                                        // Ambil ID Gudang dari Form Induk (Naik 2 level: Repeater -> Section -> Form)
                                        $warehouseId = $get('../../warehouse_id');
                                        $itemId = $get('item_id');

                                        if (! $warehouseId) return 'Pilih Gudang dulu';
                                        if (! $itemId) return '-';

                                        // Cari stok di table inventory_stocks
                                        $stok = InventoryStock::where('warehouse_id', $warehouseId)
                                            ->where('item_id', $itemId)
                                            ->value('quantity') ?? 0;

                                        return $stok . ' Unit';
                                    })
                                    // Hanya muncul kalau Barang Keluar (OUT)
                                    ->visible(fn(Get $get) => $get('../../type') === 'OUT')
                                    ->columnSpan(1),
                            ])
                            ->columns(4) // Ubah jadi 4 kolom biar rapi
                            ->addActionLabel('Tambah Barang')
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('No. Transaksi')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('trx_date')
                    ->label('Tanggal')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'IN' => 'success',
                        'OUT' => 'danger',
                    }),

                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'DRAFT' => 'warning',
                        'APPROVED' => 'success',
                    }),

                Tables\Columns\TextColumn::make('category')
                    ->label('Kategori')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn($state) => str_replace('_', ' ', $state)),
            ])
            ->headerActions([
                // Pastikan plugin Excel terinstall. Jika error, comment bagian ini.
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Excel')
                    ->color('success')
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->withFilename('Transaksi_' . date('Y-m-d')),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    // Sembunyikan tombol Edit jika sudah APPROVED (Stok sudah terkunci)
                    ->hidden(fn(Transaction $record) => $record->status === 'APPROVED'),

                Tables\Actions\DeleteAction::make()
                    ->hidden(fn(Transaction $record) => $record->status === 'APPROVED'),

                // Tombol Print
                Tables\Actions\Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('info')
                    ->url(fn(Transaction $record) => route('transactions.print', $record))
                    ->openUrlInNewTab(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // Custom Bulk Delete dengan Validasi
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Collection $records) {
                            $deletedCount = 0;
                            foreach ($records as $record) {
                                if ($record->status === 'APPROVED') {
                                    continue; // Skip yang sudah approved
                                }
                                $record->delete();
                                $deletedCount++;
                            }

                            if ($deletedCount < $records->count()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Sebagian Data Tidak Dihapus')
                                    ->body('Transaksi yang sudah APPROVED tidak dapat dihapus.')
                                    ->send();
                            }
                        }),
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
            'index'  => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit'   => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
