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
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make([
                    Forms\Components\TextInput::make('causer.name')->label('Pelaku'),
                    Forms\Components\TextInput::make('description')->label('Aktivitas'),
                    Forms\Components\TextInput::make('created_at')->label('Waktu'),
                ])->columns(3),

                Forms\Components\Section::make('Detail Perubahan')
                    ->schema([
                        Forms\Components\KeyValue::make('properties.old')
                            ->label('Data Lama')
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('properties.attributes')
                            ->label('Data Baru')
                            ->columnSpanFull(),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label('Aktivitas')
                    ->badge()
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                    ]),
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Menu')
                    ->formatStateUsing(fn($state) => class_basename($state)),
                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Data'),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]); // Matikan bulk delete, log jangan dihapus!
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            // Create dan Edit DIHAPUS demi keamanan data.
        ];
    }
}
