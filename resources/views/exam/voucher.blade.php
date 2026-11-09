    @php
        $sheets = $vouchers->chunk(10);
        $logoPath = public_path('images/logo.jpg');
        $logo = is_file($logoPath) ? 'data:image/jpeg;base64,'.base64_encode(file_get_contents($logoPath)) : null;
    @endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam vouchers — {{ $exam->title }}</title>
    <style>
        @page { size: A4 portrait; margin: 12mm; }
        * { box-sizing: border-box; }
        :root { color-scheme: light; }
        body { margin: 0; background: #fff; color: #18262b; font-family: Arial, Helvetica, sans-serif; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { break-after: page; }
        .sheet:last-child { break-after: auto; }
        .sheet-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 8mm; margin-bottom: 4mm; height: 22mm; overflow: hidden; }
        .eyebrow { margin: 0 0 2mm; font-size: 8pt; font-weight: 700; letter-spacing: 1.4px; text-transform: uppercase; color: #52646a; }
        h1 { margin: 0; font-size: 14pt; line-height: 1.25; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sheet-meta { margin: 2mm 0 0; color: #52646a; font-size: 9pt; }
        .sheet-count { flex-shrink: 0; text-align: right; font-size: 8pt; line-height: 1.7; color: #52646a; }
        .voucher-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 4mm; }
        .voucher { height: 44mm; position: relative; border: .25mm dashed #899b9e; border-radius: 2mm; padding: 3.5mm 4mm; break-inside: avoid; overflow: hidden; }
        .brand { display: flex; align-items: center; gap: 2mm; padding-bottom: 2mm; border-bottom: .25mm solid #d8e3e2; }
        .brand-mark { width: 7mm; height: 7mm; background: url('{{ $logo }}') center / contain no-repeat; flex-shrink: 0; }
        .brand-name { font-size: 7pt; line-height: 1.35; font-weight: 700; text-transform: uppercase; letter-spacing: .35px; color: #164e49; }
        .exam-title { margin: 2mm 0 1mm; font-size: 8.5pt; font-weight: 700; line-height: 1.3; height: 7.8mm; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; overflow-wrap: anywhere; }
        .pin-row { display: flex; align-items: center; justify-content: space-between; gap: 2mm; }
        .pin-label { font-size: 6pt; line-height: 1.5; letter-spacing: .5px; text-transform: uppercase; color: #52646a; }
        .pin { font-family: 'Courier New', monospace; font-size: 17pt; line-height: 1.25; letter-spacing: .5px; font-weight: 700; white-space: nowrap; color: #123d38; }
        .voucher-meta { margin-top: 2mm; display: flex; justify-content: space-between; gap: 2mm; font-size: 6.5pt; line-height: 1.5; color: #52646a; }
        .serial { font-family: 'Courier New', monospace; color: #18262b; font-weight: 700; }
        .voucher-footer { position: absolute; bottom: 2.5mm; left: 4mm; right: 4mm; display: flex; justify-content: space-between; gap: 2mm; font-size: 6pt; color: #52646a; }
        .sheet-footer { margin-top: 3mm; padding-top: 2mm; border-top: .25mm solid #d8e3e2; display: flex; justify-content: space-between; gap: 4mm; color: #52646a; font-size: 7pt; }
    </style>
</head>
<body>
    @foreach($sheets as $sheet)
        <section class="sheet">
            <header class="sheet-header">
                <div>
                    <p class="eyebrow">Result checker vouchers</p>
                    <h1>{{ $exam->title }}</h1>
                    <p class="sheet-meta">Set {{ $exam->set }} · {{ strtoupper($exam->level ?? '') }}</p>
                </div>
                <div class="sheet-count">{{ $vouchers->count() }} vouchers<br>Sheet {{ $loop->iteration }} of {{ $sheets->count() }}</div>
            </header>
            <div class="voucher-grid">
                @foreach($sheet as $voucher)
                    <article class="voucher">
                        <div class="brand">
                            @if($logo)<span class="brand-mark" aria-hidden="true"></span>@endif
                            <div class="brand-name">Ozoveheni College<br>of Health Technology</div>
                        </div>
                        <p class="exam-title">{{ $exam->title }}</p>
                        <div class="pin-row"><div class="pin-label">Result<br>checker PIN</div><div class="pin">{{ $voucher['pin'] }}</div></div>
                        <div class="voucher-meta"><span class="serial">{{ $voucher['serial'] }}</span><span>{{ $exam->set }} · {{ strtoupper($exam->level ?? '') }}</span></div>
                        <div class="voucher-footer"><span>Use on the school portal.</span><span>Keep your PIN private.</span></div>
                    </article>
                @endforeach
            </div>
            <footer class="sheet-footer"><span>Cut along the dashed borders · Print at actual size (100%).</span><span>Keep unused vouchers secure.</span></footer>
        </section>
    @endforeach
</body>
</html>
