<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()->role === 'ADMIN';
    }

    // FORM BIASANYA DIPAKAI VIEW ACTION KALAU GAK PAKE INFOLIST
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Metadata Aktivitas')
                    ->schema([
                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Pelaku (User)')
                            ->weight('bold')
                            ->default('System/Auto'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Jenis Tindakan')
                            ->badge()
                            ->color(fn($state) => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Waktu Kejadian')
                            ->dateTime('d M Y, H:i:s'),
                    ])->columns(3),

                Infolists\Components\Section::make('Objek Yang Terdampak')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Modul/Menu')
                            ->formatStateUsing(fn($state) => class_basename($state)),
                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('ID Data Rekaman'),
                    ])->columns(2),

                Infolists\Components\Section::make('Log Perubahan Data')
                    ->description('Perbandingan data sebelum dan sesudah tindakan')
                    ->schema([
                        Infolists\Components\Grid::make(2)
                            ->schema([
                                Infolists\Components\KeyValueEntry::make('properties.old')
                                    ->label('Data Lama (Sebelum)')
                                    ->keyLabel('Kolom')
                                    ->valueLabel('Nilai'),
                                Infolists\Components\KeyValueEntry::make('properties.attributes')
                                    ->label('Data Baru (Sesudah)')
                                    ->keyLabel('Kolom')
                                    ->valueLabel('Nilai'),
                            ]),
                    ])
            ]);
    }


    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Aksi')
                    ->badge()
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                    ]),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modul')
                    ->formatStateUsing(fn($state) => match (class_basename($state)) {
                        'Item' => '📦 Stok Barang',
                        'StockOpname' => '📋 Audit Opname',
                        'Warehouse' => '🏢 Gudang',
                        default => class_basename($state),
                    }),

                // 👇 TAMBAHAN: Biar tau ID mana yang bermasalah tanpa klik detail
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Ref')
                    ->copyable()
                    ->fontFamily('mono'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('description')
                    ->label('Filter Aksi')
                    ->options([
                        'created' => 'Data Baru',
                        'updated' => 'Perubahan',
                        'deleted' => 'Penghapusan',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Lihat Detail Log')
                    ->modalHeading('Audit Trail Detail'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            // Create dan Edit DIHAPUS demi keamanan data.
        ];
    }
}
