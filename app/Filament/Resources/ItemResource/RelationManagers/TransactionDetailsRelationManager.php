<?php

namespace App\Filament\Resources\ItemResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class TransactionDetailsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactionDetails';
    protected static ?string $title = 'Kartu Stok (History)';
    protected static ?string $icon = 'heroicon-o-clock';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('title_code')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                // 1. Tanggal Transaksi (Ambil dari Induk Transaksi)
                Tables\Columns\TextColumn::make('transaction.created_at')
                    ->label('Tanggal')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                // 2. Tipe (IN / OUT)
                Tables\Columns\TextColumn::make('transaction.type')
                    ->label('Tipe')
                    ->badge()
                    ->colors([
                        'success' => 'IN',
                        'danger' => 'OUT',
                    ]),

                // 3. Keterangan (Dept / Supplier / Notes)
                Tables\Columns\TextColumn::make('transaction_description') // Kita bikin custom attribute nanti
                    ->label('Keterangan / Tujuan')
                    ->state(function ($record) {
                        $trx = $record->transaction;
                        // Logic buat nampilin info lengkap
                        if ($trx->type == 'IN') {
                            return 'Masuk dari: ' . ($trx->supplier->name ?? 'Unknown');
                        } else {
                            return 'Keluar ke: ' . ($trx->department->name ?? 'User') .
                                ($trx->category ? " ({$trx->category})" : '');
                        }
                    })
                    ->wrap(), // Biar kalau panjang turun ke bawah

                // 4. Jumlah Perubahan
                Tables\Columns\TextColumn::make('quantity')
                    ->label('Qty')
                    ->weight('bold')
                    ->color(fn($record) => $record->transaction->type == 'IN' ? 'success' : 'danger')
                    ->prefix(fn($record) => $record->transaction->type == 'IN' ? '+' : '-'),
            ])
            ->defaultSort('created_at', 'desc') // Yang terbaru paling atas
            ->headerActions([
                // Gak usah ada tombol Create di sini, kan otomatis dari transaksi
            ])
            ->actions([
                // Gak usah ada Edit/Delete di sini, bahaya kalau edit sejarah
            ]);
    }
}
