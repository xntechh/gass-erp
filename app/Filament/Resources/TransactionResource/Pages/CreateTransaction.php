<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    // Logic: Habis create, balik ke halaman index (List)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Anti-bypass: STAFF tidak boleh create transaksi langsung APPROVED.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (auth()->user()?->role !== 'ADMIN') {
            $data['status'] = 'DRAFT';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $transaction = $this->record->load(['details.item']);

        // Admin boleh create langsung APPROVED (auto update stok)
        if ($transaction->status === 'APPROVED') {
            try {
                DB::transaction(function () use ($transaction) {
                    $transaction->applyStockMutation();

                    if ($transaction->type === 'IN') {
                        $transaction->updateMovingAverage();
                    }
                });
            } catch (\Throwable $e) {
                // Rollback sudah terjadi. Balikin statusnya biar tidak "approved palsu".
                $transaction->updateQuietly(['status' => 'DRAFT']);

                Notification::make()
                    ->danger()
                    ->title('Gagal Approve / Mutasi Stok')
                    ->body($e->getMessage())
                    ->persistent()
                    ->send();
            }
        }
    }
}
