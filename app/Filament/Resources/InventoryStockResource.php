<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InventoryStockResource\Pages;
use App\Models\InventoryStock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class InventoryStockResource extends Resource
{
    protected static ?string $model = InventoryStock::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationLabel = 'Stok Gudang';

    protected static ?string $navigationGroup = 'Monitoring Stok';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Stok (Locked)')
                    ->description('Data ini dihitung otomatis dari Transaksi & Opname. Tidak bisa diubah manual.')
                    ->schema([
                        // Kunci Gudang (Gak boleh dipindah sembarangan)
                        Forms\Components\Select::make('warehouse_id')
                            ->relationship('warehouse', 'name')
                            ->label('Gudang')
                            ->disabled() // <--- GEMBOK
                            ->required(),

                        // Kunci Barang (Gak boleh diganti jenisnya)
                        Forms\Components\Select::make('item_id')
                            ->relationship('item', 'name')
                            ->label('Barang')
                            ->disabled() // <--- GEMBOK
                            ->required(),

                        // Kunci Jumlah (INI PALING PENTING BIAR GAK KORUPSI STOK)
                        Forms\Components\TextInput::make('quantity')
                            ->label('Sisa Stok Saat Ini')
                            ->numeric()
                            ->disabled() // <--- GEMBOK KERAS
                            ->required(),
                    ])->columns(3),

                Forms\Components\Section::make('Manajemen Lokasi')
                    ->description('Silakan update lokasi rak jika barang dipindahkan.')
                    ->schema([
                        // CUMA INI YANG BOLEH DIEDIT
                        Forms\Components\TextInput::make('rack_location')
                            ->label('Lokasi Rak')
                            ->placeholder('Contoh: A-05-B')
                            ->required(), // Wajib diisi biar rapi
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Tampilkan Nama, bukan ID
                Tables\Columns\TextColumn::make('warehouse.name')
                    ->label('Gudang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('item.name')
                    ->label('Barang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('quantity')
                    ->label('Stok')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('rack_location')
                    ->label('Rak'),
            ])
            ->filters([
                //
            ])

            ->headerActions([
                \pxlrbt\FilamentExcel\Actions\Tables\ExportAction::make()
                    ->label('Download Stok')
                    ->color('success')
                    ->exports([
                        \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                            ->fromTable()
                            ->withFilename('Laporan_Stok_' . date('Y-m-d')),
                    ]),
            ])

            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListInventoryStocks::route('/'),
            'create' => Pages\CreateInventoryStock::route('/create'),
            'edit' => Pages\EditInventoryStock::route('/{record}/edit'),
        ];
    }
}
