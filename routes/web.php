<?php

use Illuminate\Support\Facades\Route;
use App\Models\Transaction;


Route::get('/', function () {
    return redirect('/admin'); // <--- LEMPAR LANGSUNG KE ADMIN
});

use App\Http\Controllers\PdfController;

// Rute khusus cetak PDF
// Middleware 'auth' artinya harus login dulu baru bisa cetak
Route::get('/admin/transactions/{record}/print', [PdfController::class, 'print'])
    ->name('pdf.print')
    ->middleware('auth');

// Route khusus buat nge-print
Route::get('/admin/transactions/{record}/print', function (Transaction $record) {
    // Pastikan user login (Security check)
    if (!auth()->check()) {
        abort(403);
    }

    // Tampilkan surat yang tadi kita desain
    return view('transaction.print', ['transaction' => $record]);
})->name('transactions.print');
