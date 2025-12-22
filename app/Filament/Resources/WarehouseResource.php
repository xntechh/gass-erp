<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WarehouseResource\Pages;
use App\Models\Warehouse;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;

class WarehouseResource extends Resource
{
    protected static ?string $model = Warehouse::class;
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?string $navigationGroup = 'Master Data';

    // Urutan 3: Setelah Category(1) dan Unit(2)
    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Lokasi Gudang')
                    ->schema([
                        Forms\Components\Select::make('plant_id')
                            ->relationship('plant', 'name')
                            ->required()
                            ->searchable()
                            ->preload(),

                        Forms\Components\TextInput::make('name')
                            ->label('Nama Gudang')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Contoh: Gudang Bahan Baku'),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Gudang')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            // 👇 Paksa Uppercase sebelum simpan
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Aktif')
                            ->default(true)
                            ->required(),
                    ])->columns(2)
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('plant.name')
                    ->label('Lokasi Plant')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->weight('bold')
                    ->color('info')
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Gudang')
                    ->searchable(),

                // 👇 Fitur Pantau: Ada berapa item unik di gudang ini?
                Tables\Columns\TextColumn::make('stocks_count')
                    ->label('Varian Barang')
                    ->counts('stocks')
                    ->badge()
                    ->suffix(' SKU'),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWarehouses::route('/'),
            'create' => Pages\CreateWarehouse::route('/create'),
            'edit' => Pages\EditWarehouse::route('/{record}/edit'),
        ];
    }
}
