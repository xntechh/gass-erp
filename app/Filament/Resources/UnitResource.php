<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale';
    protected static ?string $navigationGroup = 'Master Data';

    // Urutan ke-2: Setelah Kategori
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detail Satuan')
                    ->description('Pastikan nama dan kode satuan sudah sesuai standar perusahaan.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Satuan')
                            ->required()
                            ->unique(ignoreRecord: true) // Anti duplikat
                            ->placeholder('Contoh: Pieces, Kilogram, Roll')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Singkatan')
                            ->required() // Wajib diisi
                            ->unique(ignoreRecord: true) // Anti duplikat
                            ->placeholder('Contoh: PCS, KG, ROL')
                            ->maxLength(10)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            // 👇 Paksa simpan jadi HURUF BESAR di database
                            ->dehydrateStateUsing(fn($state) => strtoupper($state)),
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
                    ->color('success') // Warna hijau biar seger
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                // 👇 Info penting buat Supervisor: Dipakai di berapa item?
                // Pastikan di Model Unit ada function items()
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Digunakan Pada')
                    ->counts('items')
                    ->suffix(' Item')
                    ->badge(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Update Terakhir')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit' => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
