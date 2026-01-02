<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Models\Activity; // Pastikan Model ini mengarah ke Spatie Activitylog
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Model;

class ActivityResource extends Resource
{
    // Menggunakan Model Activity (biasanya dari Spatie)
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationLabel = 'Log Aktivitas';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 4;

    /**
     * Hanya ADMIN yang boleh lihat Log.
     */
    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'ADMIN';
    }

    /**
     * Tampilan Detail (View Mode)
     * Menggunakan Infolist karena kita tidak butuh Form Edit.
     */
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Metadata Aktivitas')
                    ->schema([
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Pelaku (User)')
                            ->icon('heroicon-o-user')
                            ->weight('bold')
                            ->placeholder('System / Otomatis'),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Jenis Tindakan')
                            ->badge()
                            ->color(fn(string $state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default   => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu Kejadian')
                            ->dateTime('d M Y, H:i:s')
                            ->icon('heroicon-o-clock'),
                    ])->columns(3),

                Infolists\Components\Section::make('Objek Yang Terdampak')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Modul / Menu')
                            ->formatStateUsing(fn($state) => class_basename($state))
                            ->weight('bold'),

                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('ID Data (Ref)')
                            ->fontFamily('mono')
                            ->copyable(),
                    ])->columns(2),

                Infolists\Components\Section::make('Log Perubahan Data')
                    ->description('Perbandingan data sebelum dan sesudah tindakan.')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                // Menampilkan Data Lama
                                Infolists\Components\KeyValueEntry::make('properties.old')
                                    ->label('ORIGINAL (Sebelum)')
                                    ->keyLabel('Kolom')
                                    ->valueLabel('Nilai Lama')
                                    ->placeholder('Tidak ada data lama (Data Baru)'),

                                // Menampilkan Data Baru
                                Infolists\Components\KeyValueEntry::make('properties.attributes')
                                    ->label('CHANGES (Sesudah)')
                                    ->keyLabel('Kolom')
                                    ->valueLabel('Nilai Baru')
                                    ->placeholder('Tidak ada perubahan tercatat'),
                            ]),
                    ])
                    ->collapsible(), // Bisa ditutup biar gak penuh
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->defaultSort('created_at', 'desc') // Urutkan dari yang terbaru
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable() // Bisa cari nama user
                    ->default('System')
                    ->weight('bold')
                    ->icon('heroicon-m-user-circle'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Aksi')
                    ->badge()
                    ->formatStateUsing(fn($state) => ucfirst($state)) // Huruf besar di awal
                    ->color(fn(string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default   => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modul')
                    ->formatStateUsing(fn($state) => match (class_basename($state)) {
                        'Item'        => '📦 Stok Barang',
                        'StockOpname' => '📋 Audit Opname',
                        'Warehouse'   => '🏢 Gudang',
                        'User'        => '👤 Pengguna',
                        default       => class_basename($state),
                    })
                    ->searchable(), // Bisa cari nama modul

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Ref')
                    ->fontFamily('mono')
                    ->searchable() // PENTING: Bisa cari ID Transaksi
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true), // Sembunyikan default biar tabel gak penuh
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event') // Biasanya kolom di DB namanya 'event', bukan description
                    ->label('Filter Jenis Aksi')
                    ->options([
                        'created' => 'Data Baru (Created)',
                        'updated' => 'Perubahan (Updated)',
                        'deleted' => 'Penghapusan (Deleted)',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Detail')
                    ->modalHeading('Rincian Aktivitas')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([
                // KOSONGKAN SAJA
                // Log tidak boleh dihapus massal sembarangan
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
        ];
    }
}
