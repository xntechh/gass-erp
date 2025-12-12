<!DOCTYPE html>
<html>
<head>
    <title>BAST - {{ $transaction->code }}</title>
    <style>
        body { font-family: sans-serif; padding: 20px; font-size: 14px; }
        
        /* HEADER TENGAH */
        .header { text-align: center; margin-bottom: 30px; }
        .header h2 { margin: 0; text-transform: uppercase; }
        .header p { margin: 5px 0; font-size: 12px; color: #555; }

        /* INFO SEJAJAR (INI YANG BARU) */
        .info-container {
            display: flex;           /* Kunci biar sejajar */
            justify-content: space-between; /* Biar ada jarak antar kotak */
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #000; /* Garis tebal pemisah */
        }
        .info-item {
            width: 32%; /* Bagi 3 kolom sama rata */
        }
        .info-label {
            font-weight: bold;
            display: block;
            text-transform: uppercase;
            font-size: 11px;
            color: #444;
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 14px;
            font-weight: bold;
        }

        /* TABEL BARANG */
        table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        th, td { border: 1px solid #333; padding: 10px; text-align: left; }
        th { background-color: #f2f2f2; text-transform: uppercase; font-size: 12px; }

        /* TANDA TANGAN */
        .signatures { display: flex; justify-content: space-between; margin-top: 30px; }
        .sig-box { text-align: center; width: 30%; }
        .sig-line { margin-top: 70px; border-bottom: 1px solid #000; }
    </style>
</head>
<body onload="window.print()">

    <div class="header">
        <h2>Berita Acara Serah Terima</h2>
        <p>NOMOR DOKUMEN: {{ $transaction->id }}/GA/{{ $transaction->created_at->format('m/Y') }}</p>
    </div>

    <div class="info-container">
        <div class="info-item">
            <span class="info-label">Tanggal Transaksi</span>
            <span class="info-value">{{ $transaction->created_at->format('d F Y') }}</span>
            <br>
            <small style="color: #666">Jam: {{ $transaction->created_at->format('H:i') }} WIB</small>
        </div>

        <div class="info-item">
            <span class="info-label">Jenis Transaksi</span>
            <span class="info-value">
                @if($transaction->category == 'USAGE') PEMAKAIAN USER
                @elseif($transaction->category == 'CSR') SUMBANGAN / CSR
                @elseif($transaction->category == 'SCRAP') PEMUSNAHAN (SCRAP)
                @elseif($transaction->category == 'PURCHASE') PEMBELIAN
                @else {{ $transaction->category ?? $transaction->type }}
                @endif
            </span>

            @if($transaction->department)
                <div style="margin-top: 8px;">
                    <span class="info-label">Departemen</span>
                    <span class="info-value">{{ $transaction->department->name }}</span>
                </div>
            @endif
             @if($transaction->supplier)
                <div style="margin-top: 8px;">
                    <span class="info-label">Vendor</span>
                    <span class="info-value">{{ $transaction->supplier->name }}</span>
                </div>
            @endif
        </div>

        <div class="info-item" style="text-align: right;"> <span class="info-label">Lokasi Gudang</span>
            <span class="info-value">{{ $transaction->warehouse->name }}</span>
            <br>
            <small>{{ $transaction->warehouse->plant->name ?? '' }}</small>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">No</th>
                <th style="width: 20%;">Kode</th>
                <th>Nama Barang</th>
                <th style="width: 15%; text-align: center;">Jumlah</th>
                <th style="width: 10%;">Satuan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->details as $index => $detail)
            <tr>
                <td style="text-align: center;">{{ $index + 1 }}</td>
                <td>{{ $detail->item->code }}</td>
                <td>{{ $detail->item->name }}</td>
                <td style="text-align: center; font-weight: bold;">{{ $detail->quantity }}</td>
                <td>{{ $detail->item->unit->name ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>

    </table>

    @if($transaction->description)
        <div style="margin-bottom: 30px; padding: 10px; border: 1px dashed #ccc; background-color: #fafafa;">
            <strong>Catatan:</strong><br>
            <span style="white-space: pre-line;">{{ $transaction->description }}</span>
        </div>
    @endif

    <div class="signatures">
        <div class="sig-box">
            <p>Dibuat Oleh (Admin)</p>
            <div class="sig-line"></div>
        </div>
        
        <div class="sig-box">
            <p>Diterima Oleh</p>
            <div class="sig-line"></div>
            <p>( ........................... )</p>
        </div>

        <div class="sig-box">
            <p>Mengetahui (SPV)</p>
            <div class="sig-line"></div>
        </div>
    </div>

</body>
</html>