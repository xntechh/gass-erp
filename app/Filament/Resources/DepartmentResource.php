<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DepartmentResource\Pages;
use App\Models\Department;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

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
                            ->unique(ignoreRecord: true) // Anti ganda
                            ->placeholder('Contoh: Human Resources & General Affairs')
                            ->maxLength(255),

                        Forms\Components\TextInput::make('code')
                            ->label('Kode Singkatan')
                            ->required()
                            ->unique(ignoreRecord: true) // Anti ganda
                            ->placeholder('Contoh: HRGA')
                            ->maxLength(10)
                            ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                            // 👇 Paksa simpan sebagai HURUF BESAR
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
                    ->color('warning') // Biar eye-catching
                    ->searchable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nama Departemen')
                    ->searchable()
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
            'index' => Pages\ListDepartments::route('/'),
            'create' => Pages\CreateDepartment::route('/create'),
            'edit' => Pages\EditDepartment::route('/{record}/edit'),
        ];
    }
}
