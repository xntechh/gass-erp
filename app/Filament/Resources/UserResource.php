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
use Filament\Notifications\Notification; // Import Notification
use Filament\Forms\Components\Section;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Manajemen User';
    protected static ?string $navigationGroup = 'Pengaturan';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Profil Pengguna')
                    ->description('Informasi dasar dan penugasan departemen.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Lengkap')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Contoh: Budi Santoso'),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('email@kantor.com'),

                        Forms\Components\Select::make('department_id')
                            ->label('Departemen')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),

                        Forms\Components\Select::make('role')
                            ->label('Hak Akses (Role)')
                            ->options([
                                'ADMIN' => 'Administrator (Full Akses)',
                                'STAFF' => 'Staff Gudang (Operasional)',
                            ])
                            ->required()
                            ->native(false),
                    ])->columns(2),

                Section::make('Keamanan Akun')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable() // Biar bisa intip password saat ngetik
                            ->minLength(8) // Minimal 8 karakter biar aman
                            ->dehydrateStateUsing(fn($state) => Hash::make($state))
                            ->dehydrated(fn($state) => filled($state)) // Hanya simpan jika diisi
                            ->required(fn(string $context): bool => $context === 'create')
                            ->helperText('Biarkan kosong jika tidak ingin mengubah password user ini.'),
                    ]),
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
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'ADMIN' => 'danger', // Merah
                        'STAFF' => 'info',   // Biru
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('department.code')
                    ->label('Dept')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Bergabung')
                    ->dateTime('d M Y')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // Filter berdasarkan Role
                Tables\Filters\SelectFilter::make('role')
                    ->label('Filter Role')
                    ->options([
                        'ADMIN' => 'Administrator',
                        'STAFF' => 'Staff Gudang',
                    ]),

                // Filter berdasarkan Departemen
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Filter Departemen')
                    ->relationship('department', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // PROTEKSI DELETE: Jangan sampai admin menghapus dirinya sendiri
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, User $record) {
                        if ($record->id === auth()->id()) {
                            Notification::make()
                                ->danger()
                                ->title('Akses Ditolak')
                                ->body('Anda tidak dapat menghapus akun Anda sendiri saat sedang login.')
                                ->send();

                            // Batalkan aksi hapus
                            $action->cancel();
                        }
                    }),
            ])
            ->bulkActions([
                // Proteksi Bulk Delete juga penting
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function (Tables\Actions\DeleteBulkAction $action, \Illuminate\Database\Eloquent\Collection $records) {
                            // Cek apakah user yang login ada di dalam list yang mau dihapus
                            if ($records->contains(auth()->user())) {
                                Notification::make()
                                    ->danger()
                                    ->title('Gagal')
                                    ->body('Anda tidak dapat menghapus diri sendiri dalam seleksi massal.')
                                    ->send();

                                $action->halt(); // Berhenti total
                            }
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
