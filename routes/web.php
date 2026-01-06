<?php

use Illuminate\Support\Facades\Route;
use App\Models\Transaction;

Route::get('/', function () {
    return redirect('/admin');
});

// PRINT (HTML) - pakai template lama: resources/views/transaction/print.blade.php
Route::get('/admin/transactions/{record}/print', function (Transaction $record) {
    // load relasi biar tabel barang & gudang kebaca
    $record->loadMissing(['warehouse.plant', 'details.item.unit', 'department']);

    return view('transaction.print', ['transaction' => $record]);
})
    ->name('transactions.print')
    ->middleware(['auth', 'can:view,record']);
