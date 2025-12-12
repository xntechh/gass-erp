<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityResource\Pages;
use App\Filament\Resources\ActivityResource\RelationManagers;
use App\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 4; // Muncul di bawah

    public static function shouldRegisterNavigation(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->role === 'ADMIN';
    }

    public static function canViewAny(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->role === 'ADMIN';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('log_name')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('subject_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('event')
                    ->maxLength(255),
                Forms\Components\TextInput::make('subject_id')
                    ->numeric(),
                Forms\Components\TextInput::make('causer_type')
                    ->maxLength(255),
                Forms\Components\TextInput::make('causer_id')
                    ->numeric(),
                Forms\Components\TextInput::make('properties'),
                Forms\Components\TextInput::make('batch_uuid')
                    ->maxLength(36),
                Forms\Components\KeyValue::make('properties.attributes')
                    ->label('Data Baru (New Value)')
                    ->columnSpanFull(),

                Forms\Components\KeyValue::make('properties.old')
                    ->label('Data Lama (Old Value)')
                    //->color('Red') // Merah biar kelihatan bedanya
                    ->columnSpanFull()
                    ->visible(fn($record) => isset($record->properties['old'])),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Siapa Pelakunya
                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User / Pelaku')
                    ->icon('heroicon-o-user')
                    ->searchable(),

                // 2. Ngapain Dia? (Created/Updated)
                Tables\Columns\TextColumn::make('description')
                    ->label('Aktivitas')
                    ->badge()
                    ->colors([
                        'success' => 'created',
                        'warning' => 'updated',
                        'danger' => 'deleted',
                    ]),

                // 3. Objek Apa? (Misal: Transaction #55)
                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Tipe Data')
                    ->formatStateUsing(fn($state) => class_basename($state)), // Biar muncul "Transaction" bukan "App\Models..."

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID Data'),

                // 4. Kapan?
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu Kejadian')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc') // Yang terbaru paling atas
            ->actions([
                Tables\Actions\ViewAction::make(), // Cuma boleh LIHAT (Mata)
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivities::route('/'),
            'create' => Pages\CreateActivity::route('/create'),
            'edit' => Pages\EditActivity::route('/{record}/edit'),
        ];
    }
}
