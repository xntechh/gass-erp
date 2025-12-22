<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Spatie\Activitylog\Models\Activity; // Pastikan model ini benar

class RecentActivityWidget extends BaseWidget
{
    // 👇 WAJIB: Atur lebar biar bisa bagi lapak (1 dari 2 kolom)
    protected int | string | array $columnSpan = 1;

    protected static ?string $heading = 'AKTIVITAS TERAKHIR USER';

    public function table(Table $table): Table
    {
        return $table
            // 👇 INI DIA KUNCI YANG TADI HILANG!
            ->query(
                Activity::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Waktu')
                    ->dateTime('H:i')
                    ->description(fn($record) => $record->created_at->diffForHumans()),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->default('System')
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('description')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn($state) => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Modul')
                    ->formatStateUsing(fn($state) => class_basename($state)),
            ])
            ->paginated(false); // Dashboard gak butuh pagination, biar ringkas
    }
}
