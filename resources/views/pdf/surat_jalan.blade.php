<!DOCTYPE html>
<html>
<head>
    <title>Surat Jalan - {{ $record->code }}</title>
    <style>
        body { font-family: sans-serif; font-size: 14px; }
        .header { text-align: center; margin-bottom: 20px; }
        .info-table { width: 100%; margin-bottom: 20px; }
        .info-table td { padding: 5px; }
        
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items-table th { background-color: #f2f2f2; }
        
        .signatures { width: 100%; margin-top: 50px; }
        .signature-box { width: 30%; float: left; text-align: center; }
        .signature-line { border-bottom: 1px solid #000; margin-top: 60px; width: 80%; margin-left: auto; margin-right: auto; }
    </style>
</head>
<body>

    <div class="header">
        <h2>SURAT JALAN / DELIVERY NOTE</h2>
        <p>GUDANG GENERAL AFFAIRS</p>
    </div>

    <table class="info-table">
        <tr>
            <td width="15%"><strong>No. Transaksi</strong></td>
            <td>: {{ $record->code }}</td>
            <td width="15%"><strong>Tanggal</strong></td>
            <td>: {{ \Carbon\Carbon::parse($record->trx_date)->format('d M Y') }}</td>
        </tr>
        <tr>
            <td><strong>Tipe</strong></td>
            <td>: {{ $record->type }} ({{ $record->status }})</td>
            <td><strong>Gudang</strong></td>
            <td>: {{ $record->warehouse->name }}</td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th width="5%">No</th>
                <th>Nama Barang</th>
                <th>Kode Barang</th>
                <th width="15%">Qty</th>
            </tr>
        </thead>
        <tbody>
            @foreach($record->details as $index => $detail)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $detail->item->name }}</td>
                <td>{{ $detail->item->code }}</td>
                <td>{{ $detail->quantity }} {{ $detail->item->unit }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="signatures">
        <div class="signature-box">
            Dibuat Oleh,
            <div class="signature-line">Admin Gudang</div>
        </div>
        <div class="signature-box" style="float: right;">
            Diterima Oleh,
            <div class="signature-line">Penerima</div>
        </div>
    </div>

</body>
</html>