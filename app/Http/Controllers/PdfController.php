<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class PdfController extends Controller
{
    public function print(Transaction $record)
    {
        // 1. Ambil data transaksi berdasarkan ID
        // (Laravel otomatis cari data krn kita pake Type Hinting 'Transaction $record')

        // 2. Load View HTML yg kita buat tadi, kirim datanya
        $pdf = Pdf::loadView('pdf.surat_jalan', ['record' => $record]);

        // 3. Atur ukuran kertas (A4 Potrait)
        $pdf->setPaper('a4', 'portrait');

        // 4. Tampilkan di browser (stream)
        // Kalau mau langsung download, ganti ->stream() jadi ->download()
        return $pdf->stream('Surat_Jalan_' . $record->code . '.pdf');
    }
}