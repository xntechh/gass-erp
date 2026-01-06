<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

class PdfController extends Controller
{
    public function print(Transaction $record)
    {
        // Route sudah pakai middleware can:view,record
        // Kalau mau double check, pastikan Controller sudah pakai AuthorizesRequests
        // $this->authorize('view', $record);

        $pdf = Pdf::loadView('pdf.surat_jalan', ['record' => $record])
            ->setPaper('a4', 'portrait');

        // code contoh: TRX/IN/2025/12/0001 -> gak boleh dipakai mentah jadi filename
        $safeCode = Str::slug((string) $record->code, '-'); // TRX-IN-2025-12-0001
        $filename = 'Surat_Jalan_' . $safeCode . '.pdf';

        return $pdf->stream($filename);
    }
}
