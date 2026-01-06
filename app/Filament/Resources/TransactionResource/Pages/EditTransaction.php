<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;

    protected function beforeSave(): void
    {
        if ($this->record->status === 'APPROVED') {
            Notification::make()
                ->warning()
                ->title('Akses Ditolak')
                ->body('Transaksi yang sudah APPROVED tidak bisa diedit lagi!')
                ->persistent()
                ->send();

            $this->halt();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->visible(fn() => $this->record->status === 'DRAFT'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
