<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 1; // User harus di atas di menu Pengaturan

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informasi Dasar')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true) // WAJIB: Biar gak ada email kembar
                            ->maxLength(255),

                        Forms\Components\Select::make('department_id')
                            ->label('Departemen Asal')
                            ->relationship('department', 'name') // Hubungkan ke Master Departemen
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Akses & Keamanan')
                    ->schema([
                        Forms\Components\Select::make('role')
                            ->label('Level Akses')
                            ->options([
                                'ADMIN' => 'Administrator (Full Akses)',
                                'STAFF' => 'Staff Gudang (Input Saja)',
                            ])
                            ->required(),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->label('Password Baru')
                            ->helperText('Kosongkan jika tidak ingin mengubah password')
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state))
                            ->required(fn(string $context): bool => $context === 'create')
                            ->maxLength(255),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->striped()
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->copyable() // Bagus buat admin kalau mau reset password user
                    ->searchable(),

                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departemen')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->colors([
                        'danger' => 'ADMIN',
                        'info' => 'STAFF',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department')
                    ->relationship('department', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->before(function (User $record) {
                        if ($record->id === auth()->id()) {
                            // Cegah admin hapus diri sendiri
                            throw new \Exception('Lo gak bisa hapus akun lo sendiri, Capt!');
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
