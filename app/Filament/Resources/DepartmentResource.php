<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification; // Import Notifikasi

class DepartmentResource extends Resource
{
    protected static ?string $model = Department::class;
    protected static ?string $navigationIcon = 'heroicon-o-briefcase';
    protected static ?string $navigationGroup = 'Master Data';

    // Set urutan ke 2 (Setelah Kategori, Sebelum Item)
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identitas Departemen')
                    ->description('Gunakan nama resmi departemen sesuai struktur organisasi.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nama Departemen')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->placeholder('Contoh: Human Resources & General Affairs'),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Singkatan')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->placeholder('Contoh: HRGA')
                            // Validasi: Hanya Huruf Besar & Angka (Tanpa Spasi)
                            ->regex('/^[A-Z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'Kode hanya boleh Huruf Kapital dan Angka (tanpa spasi/simbol).',
                            ])
                            // UX: Visual jadi huruf besar
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            // Backend: Simpan sebagai huruf besar
                            ->dehydrateStateUsing(fn(string $state): string => strtoupper($state)),
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
                    ->color('warning')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Departemen')
                    ->searchable()
                    ->sortable(),

                // Opsional: Tampilkan jumlah karyawan jika relasi sudah ada
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Jml Karyawan')
                    ->counts('users') // Pastikan ada relasi 'users' di Model Department
                    ->badge()
                    ->color('gray')
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

                // PROTEKSI DELETE: Cek apakah ada karyawan di departemen ini?
                Tables\Actions\DeleteAction::make()
                    ->before(function (Tables\Actions\DeleteAction $action, Department $record) {
                        // Pastikan relasi 'users' sudah dibuat di Model Department
                        if ($record->users()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Gagal Menghapus')
                                ->body('Departemen ini masih memiliki Karyawan aktif. Pindahkan karyawan terlebih dahulu.')
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
            'index'  => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit'   => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
