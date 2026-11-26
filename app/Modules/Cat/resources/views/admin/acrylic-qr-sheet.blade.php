<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak Tag Akrilik QR — {{ $singleCat ? ($singleCat->name . ' (' . $singleCat->unique_code . ')') : 'Semua Kucing Aktif' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4 portrait;
            margin: 12mm;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #F4F6F9;
            color: #0F172A;
            padding: 22px;
            -webkit-font-smoothing: antialiased;
        }

        .toolbar {
            max-width: 920px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .toolbar-group { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 36px;
            padding: 0 14px;
            border-radius: 7px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            background: #FFFFFF;
            color: #334155;
            border-color: #CBD5E1;
        }
        .btn:hover { background: #F8FAFC; }
        .btn-primary { background: #EA580C; color: #FFFFFF; border-color: #EA580C; }
        .btn-primary:hover { background: #C2410C; }
        .btn-active { background: #0B192C; color: #FFFFFF; border-color: #0B192C; }

        .spec-banner {
            max-width: 920px;
            margin: 0 auto 20px;
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            font-size: 12.5px;
            color: #475569;
        }
        .spec-banner strong { color: #0F172A; }

        .sheet {
            max-width: 920px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .tag-pair-card {
            background: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 12px;
            padding: 20px 22px;
            page-break-inside: avoid;
        }

        .tag-pair-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding-bottom: 12px;
            margin-bottom: 18px;
            border-bottom: 1px solid #E2E8F0;
        }
        .tag-pair-title { font-size: 15px; font-weight: 700; color: #0F172A; }
        .tag-pair-meta {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11.5px;
            color: #64748B;
        }

        .tag-pair-grid {
            display: flex;
            align-items: flex-start;
            justify-content: center;
            gap: 28px;
            flex-wrap: wrap;
        }

        .tag-face {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
        }

        /* Mode ukuran fisik akrilik kalung kucing: 42mm x 45mm (presisi siap laser-cut) */
        .scale-actual .tag-svg-box {
            width: 42mm;
            height: 45.15mm;
        }

        /* Mode pratinjau besar: nyaman diperiksa di layar atau cetak stiker demonstrasi */
        .scale-large .tag-svg-box {
            width: 78mm;
            height: 83.85mm;
        }

        .tag-svg-box svg {
            width: 100%;
            height: 100%;
            display: block;
        }

        .tag-face-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748B;
        }

        @media print {
            body { background: #FFFFFF; padding: 0; }
            .no-print { display: none !important; }
            .sheet { max-width: none; gap: 8mm; }
            .tag-pair-card {
                border: 1px solid #CBD5E1;
                border-radius: 6px;
                padding: 6mm 8mm;
            }
            .tag-pair-head { margin-bottom: 4mm; padding-bottom: 2.5mm; }
        }
    </style>
</head>
<body class="{{ $scaleMode === 'large' ? 'scale-large' : 'scale-actual' }}">
@php
    $baseUrl = $singleCat ? ('/admin/cats/' . $singleCat->id . '/qr-sheet') : '/admin/cats/qr-sheet';
@endphp

    <div class="toolbar no-print">
        <div class="toolbar-group">
            <a href="/admin/cats" class="btn">&larr; Kembali ke Katalog</a>
            <a href="{{ $baseUrl }}?scale=actual&cutline={{ $showCutline ? '1' : '0' }}" class="btn {{ $scaleMode !== 'large' ? 'btn-active' : '' }}">Ukuran Akrilik (42&times;45 mm)</a>
            <a href="{{ $baseUrl }}?scale=large&cutline={{ $showCutline ? '1' : '0' }}" class="btn {{ $scaleMode === 'large' ? 'btn-active' : '' }}">Pratinjau Besar</a>
            <a href="{{ $baseUrl }}?scale={{ $scaleMode }}&cutline={{ $showCutline ? '0' : '1' }}" class="btn">
                Garis Potong: {{ $showCutline ? 'Aktif' : 'Nonaktif' }}
            </a>
        </div>
        <div class="toolbar-group">
            @if($singleCat)
                <a href="/admin/cats/{{ $singleCat->id }}/qr-download?format=acrylic_front" class="btn">Unduh SVG Depan</a>
                <a href="/admin/cats/{{ $singleCat->id }}/qr-download?format=acrylic_back" class="btn">Unduh SVG Belakang</a>
                <a href="/admin/cats/{{ $singleCat->id }}/qr-download?format=png" class="btn">Unduh QR PNG</a>
            @endif
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Lembar Akrilik</button>
        </div>
    </div>

    <div class="spec-banner no-print">
        <div>
            <strong>Spesifikasi Cetak Akrilik Kalung:</strong> Akrilik bening 3 mm &middot; UV Print 2 sisi (Depan &amp; Belakang) &middot; Dimensi 42 &times; 45 mm &middot; Lubang ring &Oslash; 4 mm.
        </div>
        <div>Total: <strong>{{ count($items) }}</strong> tag kucing siap cetak</div>
    </div>

    <div class="sheet">
        @foreach($items as $item)
            <section class="tag-pair-card">
                <div class="tag-pair-head">
                    <div class="tag-pair-title">
                        {{ $item['cat']->name }}
                        <span style="font-weight:500; color:#64748B;">({{ $item['cat']->unique_code }})</span>
                    </div>
                    <div class="tag-pair-meta">{{ $item['payload'] }}</div>
                </div>
                <div class="tag-pair-grid">
                    <div class="tag-face">
                        <div class="tag-svg-box">{!! $item['front_svg'] !!}</div>
                        <div class="tag-face-label">Sisi Depan &mdash; {{ $item['cat']->name }} ({{ $item['cat']->unique_code }})</div>
                    </div>
                    <div class="tag-face">
                        <div class="tag-svg-box">{!! $item['back_svg'] !!}</div>
                        <div class="tag-face-label">Sisi Belakang &mdash; Meowtopia</div>
                    </div>
                </div>
            </section>
        @endforeach
    </div>
</body>
</html>
