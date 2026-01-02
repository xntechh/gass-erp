<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PlantResource\Pages;
use App\Models\Plant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Notifications\Notification; // Import Notifikasi

class PlantResource extends Resource
{
    protected static ?string $model = Plant::class;
    protected static ?string $navigationIcon = 'heroicon-o-globe-asia-australia';
    protected static ?string $navigationGroup = 'Master Data';

    // Hirarki Tertinggi: Nomor 1 (Paling Atas)
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Plant / Site')
                    ->description('Data ini adalah lokasi fisik operasional utama (Induk Gudang).')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Plant')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: SENTUL PLANT')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Plant')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->placeholder('Contoh: SNTL')
                            // Validasi: Hanya Huruf Besar & Angka (Tanpa Spasi)
                            ->regex('/^[A-Z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh Huruf Kapital dan Angka (tanpa spasi).',
                            ])
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
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
                Tables\Columns\TextColumn::make('code')
                    ->label('Kode')
                    ->weight('bold')
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Plant')
                    ->searchable()
                    ->sortable(),

                // Info Strategis: Berapa gudang di bawah Plant ini?
                Tables\Columns\TextColumn::make('warehouses_count')
                    ->label('Jumlah Gudang')
                    ->counts('warehouses') // Pastikan relasi ada di Model Plant
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Aktif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status Aktif'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // PROTEKSI DELETE: Jangan hapus Plant kalau masih ada Gudang di dalamnya
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Plant $record) {
                        if ($record->warehouses()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus')
                                ->body('Plant ini masih memiliki Gudang aktif. Hapus gudang terlebih dahulu.')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPlants::route('/'),
            'create' => Pages\CreatePlant::route('/create'),
            'edit'   => Pages\EditPlant::route('/{record}/edit'),
        ];
    }
}
