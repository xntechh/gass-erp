<?php

namespace App\Filament\Resources\TransactionResource\Pages;

use App\Filament\Resources\TransactionResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Filament\Notifications\Notification; 

class EditTransaction extends EditRecord
{
    protected static string $resource = TransactionResource::class;
    
    protected function beforeSave(): void
    {
        // Cek apakah data yang mau diedit statusnya udah APPROVED?
        if ($this->record->status === 'APPROVED') {
            
            // Kirim notifikasi error ke user
            Notification::make()
                ->warning()
                ->title('Akses Ditolak')
                ->body('Transaksi yang sudah APPROVED tidak bisa diedit lagi!')
                ->persistent()
                ->send();

            // BATALKAN PENYIMPANAN (HALT)
            $this->halt();
        }
    }
    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Logic: Habis save edit, balik ke halaman index (List)
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}