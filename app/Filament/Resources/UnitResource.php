<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UnitResource\Pages;
use App\Models\Unit;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification; // Import Notifikasi

class UnitResource extends Resource
{
    protected static ?string $model = Unit::class;
    protected static ?string $navigationIcon = 'heroicon-o-scale'; // Ikon Timbangan
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
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: Pieces, Kilogram, Roll')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Singkatan')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->placeholder('Contoh: PCS')
                            ->maxLength(10)
                            // Validasi: Hanya Huruf & Angka (Tanpa Spasi)
                            ->regex('/^[A-Z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh Huruf dan Angka (tanpa spasi).',
                            ])
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
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
                    ->color('success')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Lengkap')
                    ->searchable()
                    ->sortable(),

                // Info Penggunaan
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Digunakan Pada')
                    ->counts('items') // Pastikan relasi items() ada di Model Unit
                    ->suffix(' Item')
                    ->badge()
                    ->color(fn($state) => $state > 0 ? 'info' : 'gray')
                    ->sortable(),

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

                // PROTEKSI DELETE: Jangan hapus Satuan jika masih dipakai Barang
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Unit $record) {
                        if ($record->items()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus')
                                ->body('Satuan ini sedang digunakan oleh Barang lain. Ganti satuan barang dulu sebelum menghapus.')
                                ->send();

                            $action->cancel();
                        }
                    }),
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
            'index'  => Pages\ListUnits::route('/'),
            'create' => Pages\CreateUnit::route('/create'),
            'edit'   => Pages\EditUnit::route('/{record}/edit'),
        ];
    }
}
