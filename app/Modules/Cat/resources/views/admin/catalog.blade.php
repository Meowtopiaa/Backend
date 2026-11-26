<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meowtopia Admin — Katalog Kucing</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #F4F6F9;
            --surface: #FFFFFF;
            --surface-subtle: #F8FAFC;
            --border: #E2E8F0;
            --border-strong: #CBD5E1;

            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #64748B;

            --sidebar-navy: #0B192C;
            --sidebar-navy-hover: #152B4A;
            --sidebar-navy-border: #1C3559;
            --sidebar-text: #D5E2F2;
            --sidebar-text-muted: #8AA4C8;
            --sidebar-w: 248px;
            --sidebar-collapsed-w: 76px;

            --primary: #EA580C;
            --primary-hover: #C2410C;
            --edit: #1D4ED8;
            --edit-bg: #EFF6FF;
            --edit-border: #BFDBFE;
            --success: #15803D;
            --success-bg: #F0FDF4;
            --success-border: #BBF7D0;
            --warning: #B45309;
            --warning-bg: #FFFBEB;
            --warning-border: #FDE68A;
            --danger: #DC2626;
            --danger-bg: #FEF2F2;
            --danger-border: #FECACA;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--bg-page);
            color: var(--text-primary);
            font-size: 14px;
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            -webkit-font-smoothing: antialiased;
        }

        /* ================= SIDEBAR (SHARED VISUAL SYSTEM WITH /admin/feeding) ================= */
        .sidebar {
            width: var(--sidebar-w);
            background: var(--sidebar-navy);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            z-index: 60;
            border-right: 1px solid var(--sidebar-navy-border);
            transition: width 0.24s cubic-bezier(0.4, 0, 0.2, 1), transform 0.24s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .sidebar-edge-toggle {
            position: fixed;
            top: 21px;
            left: var(--sidebar-w);
            z-index: 65;
            width: 22px;
            height: 34px;
            background: var(--sidebar-navy);
            color: #FFFFFF;
            border: 1px solid var(--sidebar-navy-border);
            border-left: none;
            border-radius: 0 6px 6px 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            transition: left 0.24s cubic-bezier(0.4, 0, 0.2, 1), background 0.15s ease;
            user-select: none;
        }
        .sidebar-edge-toggle:hover { background: var(--sidebar-navy-hover); }

        .sidebar-brand {
            padding: 18px 19px;
            border-bottom: 1px solid var(--sidebar-navy-border);
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 75px;
        }
        .sidebar-logo {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            object-fit: cover;
            flex-shrink: 0;
            background: #FFFFFF;
            border: 1.5px solid rgba(255, 255, 255, 0.22);
        }
        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .sidebar-brand-title {
            font-size: 14.5px;
            font-weight: 700;
            letter-spacing: 0.04em;
            color: #FFFFFF;
            line-height: 1.25;
        }
        .sidebar-brand-sub {
            font-size: 11px;
            color: var(--sidebar-text-muted);
            font-weight: 500;
            margin-top: 2px;
        }

        .sidebar-nav {
            padding: 14px 12px;
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-text-muted);
            padding: 12px 10px 4px;
            white-space: nowrap;
            overflow: hidden;
        }

        .nav-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            width: 100%;
            min-height: 42px;
            padding: 9px 11px;
            border: none;
            border-radius: 8px;
            background: transparent;
            color: var(--sidebar-text);
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .nav-item:hover { background: var(--sidebar-navy-hover); color: #FFFFFF; }
        .nav-item.active { background: var(--primary); color: #FFFFFF; font-weight: 600; }

        .nav-item-label {
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .nav-icon {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
            stroke: currentColor;
        }
        .nav-count {
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.12);
            color: #FFFFFF;
            flex-shrink: 0;
        }
        .nav-item.active .nav-count { background: rgba(255, 255, 255, 0.24); }

        body.sidebar-collapsed .sidebar { width: var(--sidebar-collapsed-w); }
        body.sidebar-collapsed .sidebar-edge-toggle { left: var(--sidebar-collapsed-w); }
        body.sidebar-collapsed .sidebar-brand-text,
        body.sidebar-collapsed .nav-item-text,
        body.sidebar-collapsed .nav-count,
        body.sidebar-collapsed .nav-section-label { display: none; }
        body.sidebar-collapsed .sidebar-brand { justify-content: center; padding: 18px 0; }
        body.sidebar-collapsed .nav-item { justify-content: center; padding: 10px 0; }

        /* ================= MAIN LAYOUT ================= */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.24s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.sidebar-collapsed .main-wrapper { margin-left: var(--sidebar-collapsed-w); }

        .topbar {
            background: var(--surface);
            border-bottom: 1px solid var(--border);
            padding: 15px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            position: sticky;
            top: 0;
            z-index: 40;
            min-height: 68px;
        }
        .topbar-left { display: flex; flex-direction: column; gap: 2px; }
        .topbar-title {
            font-size: 18px;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: var(--text-primary);
        }
        .topbar-sub {
            font-size: 12px;
            color: var(--text-muted);
        }
        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .page-content {
            padding: 22px 28px 40px;
            max-width: 1440px;
            width: 100%;
        }

        /* ================= ALERTS ================= */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13.5px;
        }
        .alert-success { background: var(--success-bg); border-color: var(--success-border); color: var(--success); }
        .alert-danger { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger); }
        .alert-close {
            background: none;
            border: none;
            font-size: 18px;
            line-height: 1;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
        }
        .alert-close:hover { opacity: 1; }

        /* ================= STATS STRIP ================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat-item {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 4px;
            transition: border-color 0.15s ease, transform 0.15s ease;
        }
        .stat-item:hover { border-color: var(--border-strong); transform: translateY(-1px); }
        .stat-item.active { border-color: var(--primary); box-shadow: 0 0 0 1px var(--primary); }
        .stat-label { font-size: 12px; font-weight: 500; color: var(--text-muted); }
        .stat-value { font-size: 24px; font-weight: 700; letter-spacing: -0.02em; color: var(--text-primary); }

        /* ================= PANEL & TOOLBAR ================= */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }
        .toolbar {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            background: var(--surface);
        }
        .filter-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            flex: 1;
        }

        .input, .select, .textarea {
            font-family: inherit;
            font-size: 13px;
            color: var(--text-primary);
            background: var(--surface);
            border: 1px solid var(--border-strong);
            border-radius: 7px;
            padding: 7px 11px;
            height: 36px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }
        .textarea { height: auto; min-height: 76px; resize: vertical; line-height: 1.5; }
        .input:focus, .select:focus, .textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }
        .input-search { min-width: 230px; flex: 1; max-width: 320px; }

        /* ================= BUTTONS ================= */
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
            white-space: nowrap;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .btn-sm { height: 31px; padding: 0 10px; font-size: 12px; }
        .btn-primary { background: var(--primary); color: #FFFFFF; }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-neutral { background: var(--surface); color: var(--text-primary); border-color: var(--border-strong); }
        .btn-neutral:hover { background: var(--surface-subtle); }
        .btn-edit { background: var(--edit-bg); color: var(--edit); border-color: var(--edit-border); }
        .btn-edit:hover { background: #DBEAFE; }
        .btn-danger { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }
        .btn-danger:hover { background: #FEE2E2; }
        .btn-success { background: var(--success-bg); color: var(--success); border-color: var(--success-border); }
        .btn-success:hover { background: #DCFCE7; }

        /* ================= TABLE ================= */
        .table-wrap { width: 100%; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            font-size: 11.5px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            background: var(--surface-subtle);
            padding: 11px 16px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 13px 16px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #FAFBFD; }
        tbody tr.is-deleted { opacity: 0.62; background: #F8FAFC; }

        .cat-cell { display: flex; align-items: center; gap: 12px; min-width: 210px; }
        .cat-thumb {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            object-fit: cover;
            border: 1px solid var(--border);
            flex-shrink: 0;
            background: #FFF7ED;
        }
        .cat-avatar-fallback {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            color: #C2410C;
            font-weight: 700;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cat-name { font-weight: 600; color: var(--text-primary); font-size: 14px; }
        .cat-meta { font-size: 12px; color: var(--text-muted); display: flex; align-items: center; gap: 6px; margin-top: 2px; flex-wrap: wrap; }
        .code-chip {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11px;
            font-weight: 600;
            color: var(--text-secondary);
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            padding: 1px 6px;
            border-radius: 5px;
        }

        /* ================= BADGES ================= */
        .badge-group { display: flex; flex-wrap: wrap; gap: 5px; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .badge-success { background: var(--success-bg); color: var(--success); border-color: var(--success-border); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning-border); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }
        .badge-info { background: var(--edit-bg); color: var(--edit); border-color: var(--edit-border); }
        .badge-neutral { background: var(--surface-subtle); color: var(--text-secondary); border-color: var(--border); }

        .actions-cell { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }

        .empty-state {
            padding: 56px 24px;
            text-align: center;
            color: var(--text-muted);
        }
        .empty-state-title { font-size: 15px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px; }

        /* ================= MODAL ================= */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            z-index: 120;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-box {
            background: var(--surface);
            border-radius: 14px;
            border: 1px solid var(--border);
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 24px 48px rgba(15, 23, 42, 0.22);
        }
        .modal-box.modal-qr { max-width: 820px; }
        .modal-header {
            padding: 16px 22px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }
        .modal-title { font-size: 16px; font-weight: 700; color: var(--text-primary); }
        .modal-body {
            padding: 20px 22px;
            overflow-y: auto;
            flex: 1;
        }
        .modal-footer {
            padding: 14px 22px;
            border-top: 1px solid var(--border);
            background: var(--surface-subtle);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
        }

        .form-section {
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }
        .form-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .form-section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px 14px;
        }
        .form-grid-3 { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        .field { display: flex; flex-direction: column; gap: 5px; }
        .field.span-2 { grid-column: span 2; }
        .field label { font-size: 12.5px; font-weight: 600; color: var(--text-secondary); }
        .field-hint { font-size: 11px; color: var(--text-muted); }

        .photo-upload-row {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 10px 12px;
            border: 1px dashed var(--border-strong);
            border-radius: 9px;
            background: var(--surface-subtle);
        }
        .photo-preview-img {
            width: 56px;
            height: 56px;
            border-radius: 8px;
            object-fit: cover;
            border: 1px solid var(--border);
            display: none;
        }

        /* ================= ACRYLIC TAG PREVIEW STUDIO ================= */
        .acrylic-stage {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
            padding: 20px;
            background: #F8FAFC;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 16px;
        }
        .acrylic-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .acrylic-svg-frame {
            width: 100%;
            max-width: 260px;
            aspect-ratio: 400 / 430;
            display: flex;
            align-items: center;
            justify-content: center;
            filter: drop-shadow(0 10px 18px rgba(15, 23, 42, 0.10));
        }
        .acrylic-svg-frame svg { width: 100%; height: 100%; display: block; }
        .acrylic-caption { font-size: 12px; font-weight: 600; color: var(--text-secondary); }

        .qr-meta-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            padding: 12px 14px;
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 12.5px;
        }

        @media (max-width: 1080px) {
            .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 820px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar-edge-toggle { left: 0; }
            .sidebar.mobile-open + .sidebar-edge-toggle { left: var(--sidebar-w); }
            .main-wrapper { margin-left: 0 !important; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid, .form-grid-3, .acrylic-stage { grid-template-columns: 1fr; }
            .field.span-2 { grid-column: span 1; }
            .page-content { padding: 16px 16px 32px; }
        }
    </style>
</head>
<body>
@php
    $genderLabels = ['MALE' => 'Jantan', 'FEMALE' => 'Betina', 'UNKNOWN' => 'Belum diketahui'];
    $ageLabels = ['KITTEN' => 'Anak (Kitten)', 'ADULT' => 'Dewasa', 'SENIOR' => 'Senior', 'UNKNOWN' => 'Belum diketahui'];
    $healthLabels = ['HEALTHY' => 'Sehat', 'SICK' => 'Sakit', 'INJURED' => 'Terluka', 'UNDER_CARE' => 'Dalam Perawatan'];
    $adoptionLabels = [
        'UNREVIEWED' => 'Belum Dinilai',
        'READY' => 'Siap Adopsi',
        'ADOPTED' => 'Diadopsi',
        'RETURN_PENDING' => 'Proses Kembali',
        'RETURNED_REVIEW' => 'Review Kembali',
    ];
    $presenceLabels = ['ON_CAMPUS' => 'Di Kampus', 'ADOPTED' => 'Diadopsi', 'MISSING' => 'Hilang', 'DECEASED' => 'Meninggal'];
@endphp

    <!-- UNIFIED DARK NAVY COLLAPSIBLE SIDEBAR -->
    <aside class="sidebar" id="appSidebar">
        <div class="sidebar-brand" title="MEOWTOPIA — Admin Portal">
            <img src="/api/v1/feeding/assets/logo.jpg" alt="Meowtopia" class="sidebar-logo">
            <div class="sidebar-brand-text">
                <div class="sidebar-brand-title">MEOWTOPIA</div>
                <div class="sidebar-brand-sub">Admin Portal</div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section-label">Master Data</div>
            <a href="/admin/cats" class="nav-item active" aria-current="page">
                <span class="nav-item-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="4" r="2"/>
                        <circle cx="18" cy="8" r="2"/>
                        <circle cx="20" cy="16" r="2"/>
                        <path d="M9 10a5 5 0 0 1 5 5v3.5a3.5 3.5 0 0 1-6.84 1.045Q6.52 17.48 4.46 16.84A3.5 3.5 0 0 1 5.5 10Z"/>
                    </svg>
                    <span class="nav-item-text">Katalog Kucing</span>
                </span>
                <span class="nav-count">{{ $stats['total'] }}</span>
            </a>

            @if($canManageSpots || $canReviewFeeding)
            <div class="nav-section-label">Operasional Spot</div>
            @if($canManageSpots)
            <a href="/admin/feeding?tab=spots" class="nav-item">
                <span class="nav-item-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/>
                        <circle cx="12" cy="10" r="3"/>
                    </svg>
                    <span class="nav-item-text">Lokasi Feeding Spot</span>
                </span>
                <span class="nav-count">{{ $allSpotsCount }}</span>
            </a>
            @endif

            @if($canReviewFeeding)
            <a href="/admin/feeding?tab=reviews" class="nav-item">
                <span class="nav-item-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="8" height="4" x="8" y="2" rx="1" ry="1"/>
                        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                        <path d="m9 14 2 2 4-4"/>
                    </svg>
                    <span class="nav-item-text">Antrean Review</span>
                </span>
            </a>
            @endif

            <a href="/admin/feeding?tab=audit" class="nav-item">
                <span class="nav-item-label">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                        <path d="M3 3v5h5"/>
                        <path d="M12 7v5l4 2"/>
                    </svg>
                    <span class="nav-item-text">Riwayat Spot &amp; Review</span>
                </span>
            </a>
            @endif
        </nav>
    </aside>

    <button type="button" class="sidebar-edge-toggle" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">&lt;</button>

    <div class="main-wrapper">
        <header class="topbar">
            <div class="topbar-left">
                <h1 class="topbar-title">Katalog Kucing</h1>
                <div class="topbar-sub">Master data tunggal untuk Enigma, Game, Rekans Caps, dan Optimind · QR kalung otomatis</div>
            </div>
            <div class="topbar-actions">
                <a href="/admin/cats/qr-sheet" target="_blank" class="btn btn-neutral btn-sm">Cetak Semua Tag Akrilik</a>
                <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Tambah Kucing</button>
                <form method="POST" action="/admin/feeding/logout" style="display:inline;">
                    @csrf
                    <button type="submit" class="btn btn-neutral btn-sm">Keluar ({{ auth()->user()->name }})</button>
                </form>
            </div>
        </header>

        <main class="page-content">
            @if(request()->filled('notice'))
                <div class="alert alert-success">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span>{{ request()->query('notice') }}</span>
                        @php $highlightCatId = request()->query('created_cat') ?? request()->query('regenerated_cat'); @endphp
                        @if($highlightCatId)
                            <a href="/admin/cats/{{ $highlightCatId }}/qr-sheet" target="_blank" class="btn btn-neutral btn-sm">Lihat &amp; Cetak Tag Akrilik</a>
                            <a href="/admin/cats/{{ $highlightCatId }}/qr-download?format=acrylic_front" class="btn btn-primary btn-sm">Unduh SVG Depan</a>
                        @endif
                    </div>
                    <button type="button" class="alert-close" onclick="this.closest('.alert').remove()" aria-label="Tutup">&times;</button>
                </div>
            @endif

            @if(request()->filled('error') || (isset($errors) && $errors->any()))
                <div class="alert alert-danger">
                    <span>
                        {{ request()->query('error') }}
                        @if(isset($errors) && $errors->any())
                            {{ implode(' ', $errors->all()) }}
                        @endif
                    </span>
                    <button type="button" class="alert-close" onclick="this.closest('.alert').remove()" aria-label="Tutup">&times;</button>
                </div>
            @endif

            <!-- STATS STRIP -->
            <div class="stats-grid">
                <a href="/admin/cats" class="stat-item {{ empty($filters['adoption_status']) && empty($filters['health_status']) && $filters['is_sterilized'] === '' && $filters['is_vaccinated'] === '' ? 'active' : '' }}">
                    <span class="stat-label">Kucing Aktif</span>
                    <span class="stat-value">{{ $stats['total'] }}</span>
                </a>
                <a href="/admin/cats?adoption_status=READY" class="stat-item {{ $filters['adoption_status'] === 'READY' ? 'active' : '' }}">
                    <span class="stat-label">Siap Adopsi</span>
                    <span class="stat-value">{{ $stats['ready_adoption'] }}</span>
                </a>
                <a href="/admin/cats?health_status=UNDER_CARE" class="stat-item {{ in_array($filters['health_status'], ['SICK', 'INJURED', 'UNDER_CARE'], true) ? 'active' : '' }}">
                    <span class="stat-label">Perlu Perawatan</span>
                    <span class="stat-value">{{ $stats['needs_care'] }}</span>
                </a>
                <a href="/admin/cats?is_sterilized=1" class="stat-item {{ $filters['is_sterilized'] === '1' ? 'active' : '' }}">
                    <span class="stat-label">Sudah Steril</span>
                    <span class="stat-value">{{ $stats['sterilized'] }}</span>
                </a>
                <a href="/admin/cats?is_vaccinated=1" class="stat-item {{ $filters['is_vaccinated'] === '1' ? 'active' : '' }}">
                    <span class="stat-label">Sudah Vaksin</span>
                    <span class="stat-value">{{ $stats['vaccinated'] }}</span>
                </a>
            </div>

            <!-- CATALOG TABLE PANEL -->
            <div class="panel">
                <form method="GET" action="/admin/cats" class="toolbar">
                    <div class="filter-row">
                        <input type="text" name="search" value="{{ $filters['search'] }}" placeholder="Cari nama, kode kalung, warna, pola..." class="input input-search">
                        <select name="health_status" class="select" onchange="this.form.submit()">
                            <option value="">Semua Kesehatan</option>
                            @foreach($healthLabels as $k => $v)
                                <option value="{{ $k }}" {{ $filters['health_status'] === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                        <select name="adoption_status" class="select" onchange="this.form.submit()">
                            <option value="">Semua Adopsi</option>
                            @foreach($adoptionLabels as $k => $v)
                                <option value="{{ $k }}" {{ $filters['adoption_status'] === $k ? 'selected' : '' }}>{{ $v }}</option>
                            @endforeach
                        </select>
                        <select name="territory_id" class="select" onchange="this.form.submit()">
                            <option value="">Semua Zona</option>
                            @foreach($territories as $t)
                                <option value="{{ $t->id }}" {{ $filters['territory_id'] === $t->id ? 'selected' : '' }}>{{ $t->code }} — {{ $t->name }}</option>
                            @endforeach
                        </select>
                        <select name="is_sterilized" class="select" onchange="this.form.submit()">
                            <option value="">Steril: Semua</option>
                            <option value="1" {{ $filters['is_sterilized'] === '1' ? 'selected' : '' }}>Sudah Steril</option>
                            <option value="0" {{ $filters['is_sterilized'] === '0' ? 'selected' : '' }}>Belum Steril</option>
                        </select>
                        <select name="is_vaccinated" class="select" onchange="this.form.submit()">
                            <option value="">Vaksin: Semua</option>
                            <option value="1" {{ $filters['is_vaccinated'] === '1' ? 'selected' : '' }}>Sudah Vaksin</option>
                            <option value="0" {{ $filters['is_vaccinated'] === '0' ? 'selected' : '' }}>Belum Vaksin</option>
                        </select>
                        <label style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; color:var(--text-secondary); cursor:pointer;">
                            <input type="checkbox" name="include_deleted" value="1" {{ $filters['include_deleted'] ? 'checked' : '' }} onchange="this.form.submit()">
                            Nonaktif ({{ $stats['inactive'] }})
                        </label>
                    </div>
                    <div style="display:flex; gap:6px;">
                        <button type="submit" class="btn btn-neutral btn-sm">Cari</button>
                        @if(request()->query())
                            <a href="/admin/cats" class="btn btn-neutral btn-sm">Reset</a>
                        @endif
                    </div>
                </form>

                <div class="table-wrap">
                    @if($cats->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-title">Belum ada data kucing yang sesuai filter.</div>
                            <div style="font-size:13px; margin-bottom:16px;">Tambahkan kucing baru untuk membuat profil master dan tag QR akrilik secara otomatis.</div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Tambah Kucing</button>
                        </div>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Kucing</th>
                                    <th>Warna, Pola &amp; Sifat</th>
                                    <th>Zona &amp; Rarity</th>
                                    <th>Kesehatan, Steril &amp; Vaksin</th>
                                    <th>Status Adopsi &amp; Lokasi</th>
                                    <th style="width:230px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cats as $cat)
                                    @php
                                        $photoUrl = $cat->primary_photo_media_id
                                            ? route('cats.photo', ['id' => $cat->id, 'mediaId' => $cat->primary_photo_media_id], false)
                                            : null;
                                        $colorSummary = trim(($cat->primary_color ?? '') . ' ' . ($cat->fur_pattern ?? '')) ?: ($cat->color_pattern ?: '—');
                                        $gameRarity = $rarities[$cat->id] ?? null;
                                        $healthBadge = match($cat->health_status) {
                                            'HEALTHY' => 'badge-success',
                                            'UNDER_CARE' => 'badge-warning',
                                            default => 'badge-danger',
                                        };
                                        $adoptionBadge = match($cat->adoption_status) {
                                            'READY' => 'badge-success',
                                            'ADOPTED' => 'badge-info',
                                            'UNREVIEWED' => 'badge-neutral',
                                            default => 'badge-warning',
                                        };
                                        $catJson = [
                                            'id' => $cat->id,
                                            'unique_code' => $cat->unique_code,
                                            'name' => $cat->name,
                                            'gender' => $cat->gender,
                                            'age_group' => $cat->age_group,
                                            'primary_color' => $cat->primary_color,
                                            'fur_pattern' => $cat->fur_pattern,
                                            'color_pattern' => $cat->color_pattern,
                                            'is_sterilized' => $cat->is_sterilized === null ? '' : ($cat->is_sterilized ? '1' : '0'),
                                            'is_vaccinated' => $cat->is_vaccinated === null ? '' : ($cat->is_vaccinated ? '1' : '0'),
                                            'vaccinated_at' => $cat->vaccinated_at?->toDateString() ?? '',
                                            'vaccination_notes' => $cat->vaccination_notes ?? '',
                                            'temperament' => $cat->temperament ?? '',
                                            'special_needs' => $cat->special_needs ?? '',
                                            'description' => $cat->description ?? '',
                                            'territory_id' => $cat->territory_id ?? '',
                                            'presence_status' => $cat->presence_status,
                                            'health_status' => $cat->health_status,
                                            'adoption_status' => $cat->adoption_status,
                                            'traits' => $cat->traits ?? [],
                                            'photo_url' => $photoUrl,
                                        ];
                                    @endphp
                                    <tr class="{{ $cat->trashed() ? 'is-deleted' : '' }}">
                                        <td>
                                            <div class="cat-cell">
                                                @if($photoUrl)
                                                    <img src="{{ $photoUrl }}" alt="{{ $cat->name }}" class="cat-thumb">
                                                @else
                                                    <span class="cat-avatar-fallback">{{ strtoupper(mb_substr($cat->name, 0, 1)) }}</span>
                                                @endif
                                                <div>
                                                    <div class="cat-name">{{ $cat->name }}</div>
                                                    <div class="cat-meta">
                                                        <span class="code-chip">{{ $cat->unique_code }}</span>
                                                        <span>{{ $genderLabels[$cat->gender] ?? $cat->gender }}</span>
                                                        <span>&middot;</span>
                                                        <span>{{ $ageLabels[$cat->age_group] ?? $cat->age_group }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:500; color:var(--text-primary);">{{ $colorSummary }}</div>
                                            @if($cat->temperament)
                                                <div style="font-size:12px; color:var(--text-muted); margin-top:2px;">Sifat: {{ $cat->temperament }}</div>
                                            @endif
                                            @if($cat->special_needs)
                                                <div style="font-size:11.5px; color:var(--warning); margin-top:2px;">Khusus: {{ mb_strimwidth($cat->special_needs, 0, 48, '…') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div style="font-size:13px; color:var(--text-primary);">{{ $cat->territory?->name ?? 'Belum ditentukan' }}</div>
                                            <div class="badge-group" style="margin-top:4px;">
                                                @if($cat->territory)
                                                    <span class="badge badge-neutral">{{ $cat->territory->code }}</span>
                                                @endif
                                                @if($gameRarity)
                                                    <span class="badge badge-info">Rarity: {{ $gameRarity }}</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge-group">
                                                <span class="badge {{ $healthBadge }}">{{ $healthLabels[$cat->health_status] ?? $cat->health_status }}</span>
                                                <span class="badge {{ $cat->is_sterilized === true ? 'badge-success' : 'badge-neutral' }}">
                                                    {{ $cat->is_sterilized === true ? 'Steril' : ($cat->is_sterilized === false ? 'Belum Steril' : 'Steril: ?') }}
                                                </span>
                                                <span class="badge {{ $cat->is_vaccinated === true ? 'badge-success' : 'badge-neutral' }}">
                                                    {{ $cat->is_vaccinated === true ? 'Vaksin' : ($cat->is_vaccinated === false ? 'Belum Vaksin' : 'Vaksin: ?') }}
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="badge-group">
                                                <span class="badge {{ $adoptionBadge }}">{{ $adoptionLabels[$cat->adoption_status] ?? $cat->adoption_status }}</span>
                                                <span class="badge badge-neutral">{{ $presenceLabels[$cat->presence_status] ?? $cat->presence_status }}</span>
                                                @if($cat->trashed())
                                                    <span class="badge badge-danger">Nonaktif</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                @if(!$cat->trashed())
                                                    <button type="button" class="btn btn-edit btn-sm" onclick='openEditModal(@json($catJson))'>Edit</button>
                                                    @if(isset($qrPreviewMap[$cat->id]))
                                                        <button type="button" class="btn btn-neutral btn-sm" onclick="openQrModal('{{ $cat->id }}', '{{ e($cat->name) }}', '{{ e($cat->unique_code) }}')">Tag Akrilik</button>
                                                    @endif
                                                    <form method="POST" action="/admin/cats/{{ $cat->id }}/delete" onsubmit="return confirm('Nonaktifkan {{ e($cat->name) }} dari katalog aktif? Riwayat tetap tersimpan.');" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-danger btn-sm">Nonaktif</button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="/admin/cats/{{ $cat->id }}/restore" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="btn btn-success btn-sm">Pulihkan</button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </main>
    </div>

    <!-- HIDDEN QR SVG TEMPLATES PER CAT (FOR ZERO-LATENCY MODAL PREVIEW) -->
    @foreach($qrPreviewMap as $catId => $qrData)
        <template id="qr-front-{{ $catId }}">{!! $qrData['front_svg'] !!}</template>
        <div id="qr-meta-{{ $catId }}" data-payload="{{ $qrData['payload'] }}" data-issued="{{ $qrData['issued_at'] }}" style="display:none;"></div>
    @endforeach
    <template id="qr-back-shared">{!! $backAcrylicSvg !!}</template>

    <!-- MODAL TAMBAH / EDIT KUCING -->
    <div class="modal-backdrop" id="catFormModal" role="dialog" aria-modal="true" aria-labelledby="catFormTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h2 class="modal-title" id="catFormTitle">Tambah Kucing Baru</h2>
                <button type="button" class="alert-close" onclick="closeModal('catFormModal')" aria-label="Tutup">&times;</button>
            </div>
            <form method="POST" id="catFormEl" action="/admin/cats" enctype="multipart/form-data">
                @csrf
                <div class="modal-body">
                    <!-- 1. Identitas & Fisik -->
                    <div class="form-section">
                        <div class="form-section-title">1. Identitas &amp; Tampilan Fisik</div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="f_name">Nama Kucing *</label>
                                <input id="f_name" name="name" type="text" class="input" required maxlength="100" placeholder="Contoh: Mochi">
                            </div>
                            <div class="field">
                                <label for="f_unique_code">Kode Kalung Cadangan</label>
                                <input id="f_unique_code" name="unique_code" type="text" class="input" maxlength="32" placeholder="Kosongkan untuk auto (mis. CAT021)">
                                <span class="field-hint">QR Code MTP1.C.* dibuat otomatis saat disimpan.</span>
                            </div>
                            <div class="field">
                                <label for="f_gender">Jenis Kelamin *</label>
                                <select id="f_gender" name="gender" class="select" required>
                                    @foreach($genderLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_age_group">Kelompok Umur *</label>
                                <select id="f_age_group" name="age_group" class="select" required>
                                    @foreach($ageLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_primary_color">Warna Utama</label>
                                <input id="f_primary_color" name="primary_color" type="text" class="input" maxlength="80" placeholder="Contoh: Oranye Putih / Hitam">
                            </div>
                            <div class="field">
                                <label for="f_fur_pattern">Pola Bulu</label>
                                <input id="f_fur_pattern" name="fur_pattern" type="text" class="input" maxlength="80" placeholder="Contoh: Tabby / Calico / Tuxedo / Polos">
                            </div>
                            <div class="field span-2">
                                <label for="f_territory_id">Zona / Lokasi Kampus</label>
                                <select id="f_territory_id" name="territory_id" class="select">
                                    <option value="">— Belum ditentukan —</option>
                                    @foreach($territories as $t)
                                        <option value="{{ $t->id }}">{{ $t->code }} — {{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field span-2">
                                <label for="f_photo">Foto Referensi Kucing (JPG/PNG, maks. 5 MB)</label>
                                <div class="photo-upload-row">
                                    <img id="f_photo_preview" class="photo-preview-img" alt="Pratinjau foto">
                                    <div style="flex:1;">
                                        <input id="f_photo" name="photo" type="file" accept="image/*" onchange="previewPhotoInput(this)" style="font-size:12.5px;">
                                        <div class="field-hint" style="margin-top:4px;">Digunakan untuk katalog, kartu adopsi, game, dan referensi identifikasi.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 2. Kesehatan, Steril & Vaksin -->
                    <div class="form-section">
                        <div class="form-section-title">2. Kesehatan, Sterilisasi &amp; Vaksinasi</div>
                        <div class="form-grid form-grid-3">
                            <div class="field">
                                <label for="f_health_status">Kondisi Kesehatan *</label>
                                <select id="f_health_status" name="health_status" class="select" required>
                                    @foreach($healthLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_is_sterilized">Status Steril</label>
                                <select id="f_is_sterilized" name="is_sterilized" class="select">
                                    <option value="">Belum diketahui</option>
                                    <option value="1">Sudah Steril</option>
                                    <option value="0">Belum Steril</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_is_vaccinated">Status Vaksin</label>
                                <select id="f_is_vaccinated" name="is_vaccinated" class="select">
                                    <option value="">Belum diketahui</option>
                                    <option value="1">Sudah Vaksin</option>
                                    <option value="0">Belum Vaksin</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-grid" style="margin-top:12px;">
                            <div class="field">
                                <label for="f_vaccinated_at">Tanggal Vaksin Terakhir</label>
                                <input id="f_vaccinated_at" name="vaccinated_at" type="date" class="input">
                            </div>
                            <div class="field">
                                <label for="f_vaccination_notes">Catatan Vaksin</label>
                                <input id="f_vaccination_notes" name="vaccination_notes" type="text" class="input" placeholder="Contoh: F3 & Rabies lengkap">
                            </div>
                            <div class="field span-2">
                                <label for="f_special_needs">Kebutuhan Khusus (Enigma)</label>
                                <input id="f_special_needs" name="special_needs" type="text" class="input" placeholder="Contoh: Pakan rendah garam / sensitif ikan laut">
                            </div>
                        </div>
                    </div>

                    <!-- 3. Status Adopsi, Keberadaan & Sifat -->
                    <div class="form-section">
                        <div class="form-section-title">3. Adopsi, Keberadaan &amp; Karakter</div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="f_adoption_status">Status Adopsi *</label>
                                <select id="f_adoption_status" name="adoption_status" class="select" required>
                                    @foreach($adoptionLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_presence_status">Status Keberadaan *</label>
                                <select id="f_presence_status" name="presence_status" class="select" required>
                                    @foreach($presenceLabels as $k => $v)
                                        <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="field">
                                <label for="f_temperament">Sifat / Temperamen</label>
                                <input id="f_temperament" name="temperament" type="text" class="input" placeholder="Contoh: Ramah, tenang, suka dielus">
                            </div>
                            <div class="field">
                                <label for="f_status_reason">Alasan Perubahan Status (opsional)</label>
                                <input id="f_status_reason" name="status_reason" type="text" class="input" placeholder="Tercatat otomatis di log status kucing">
                            </div>
                            <div class="field span-2">
                                <label for="f_description">Deskripsi Singkat</label>
                                <textarea id="f_description" name="description" class="textarea" placeholder="Ringkasan kebiasaan, lokasi favorit, atau informasi penting lain."></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- 4. Ciri Fisik Khusus (Game & Rekans Caps) -->
                    <div class="form-section">
                        <div class="form-section-title">4. Ciri Fisik Terstruktur (Game &amp; Identifikasi)</div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="f_traits_marks">Tanda Fisik Khas</label>
                                <input id="f_traits_marks" name="traits_physical_marks" type="text" class="input" placeholder="Contoh: Bercak putih di hidung">
                            </div>
                            <div class="field">
                                <label for="f_traits_ear">Telinga / Ear-tip</label>
                                <input id="f_traits_ear" name="traits_ear_tip" type="text" class="input" placeholder="Contoh: Ear-tip kiri / Utuh">
                            </div>
                            <div class="field">
                                <label for="f_traits_tail">Bentuk Ekor</label>
                                <input id="f_traits_tail" name="traits_tail_type" type="text" class="input" placeholder="Contoh: Panjang / Bengkok / Pendek">
                            </div>
                            <div class="field">
                                <label for="f_traits_eye">Warna Mata</label>
                                <input id="f_traits_eye" name="traits_eye_color" type="text" class="input" placeholder="Contoh: Kuning amber / Hijau">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-neutral" onclick="closeModal('catFormModal')">Batal</button>
                    <button type="submit" class="btn btn-primary" id="catFormSubmitBtn">Simpan Kucing &amp; Buat QR</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL PRATINJAU TAG QR AKRILIK DEPAN-BELAKANG -->
    <div class="modal-backdrop" id="qrPreviewModal" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
        <div class="modal-box modal-qr">
            <div class="modal-header">
                <h2 class="modal-title" id="qrModalTitle">Tag Kalung Akrilik QR</h2>
                <button type="button" class="alert-close" onclick="closeModal('qrPreviewModal')" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="acrylic-stage">
                    <div class="acrylic-card">
                        <div class="acrylic-svg-frame" id="qrModalFrontSlot"></div>
                        <span class="acrylic-caption">Sisi Depan — Nama &amp; QR Kalung</span>
                    </div>
                    <div class="acrylic-card">
                        <div class="acrylic-svg-frame" id="qrModalBackSlot"></div>
                        <span class="acrylic-caption">Sisi Belakang — Branding Meowtopia</span>
                    </div>
                </div>

                <div class="qr-meta-bar">
                    <div>
                        <div style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase;">Payload QR Resmi</div>
                        <div style="font-family:ui-monospace,Consolas,monospace; font-weight:600; margin-top:2px;" id="qrModalPayload"></div>
                    </div>
                    <div>
                        <div style="color:var(--text-muted); font-size:11px; font-weight:600; text-transform:uppercase;">Diterbitkan</div>
                        <div style="font-weight:500; margin-top:2px;" id="qrModalIssued"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <div>
                    @if($canRegenerateQr)
                    <form method="POST" id="qrRegenerateForm" action="" onsubmit="return confirm('Cabut QR lama dan terbitkan token QR baru? Stiker/tag lama tidak akan bisa dipindai lagi.');" style="display:inline;">
                        @csrf
                        <input type="hidden" name="reason" value="Regenerasi tag akrilik dari panel admin">
                        <button type="submit" class="btn btn-danger btn-sm">Regenerate QR Baru</button>
                    </form>
                    @endif
                </div>
                <div style="display:flex; gap:8px; flex-wrap:wrap;">
                    <a id="qrDownloadPng" href="#" class="btn btn-neutral btn-sm">Unduh QR (PNG)</a>
                    <a id="qrDownloadFront" href="#" class="btn btn-neutral btn-sm">Unduh SVG Depan</a>
                    <a id="qrDownloadBack" href="#" class="btn btn-neutral btn-sm">Unduh SVG Belakang</a>
                    <a id="qrOpenSheet" href="#" target="_blank" class="btn btn-primary btn-sm">Buka Halaman Cetak Akrilik</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function initSidebar() {
            const collapsed = localStorage.getItem('meow_admin_sidebar_collapsed') === '1';
            if (collapsed && window.innerWidth > 820) {
                document.body.classList.add('sidebar-collapsed');
                const btn = document.getElementById('sidebarToggleBtn');
                if (btn) btn.textContent = '>';
            }
        })();

        function toggleSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const btn = document.getElementById('sidebarToggleBtn');
            if (window.innerWidth <= 820) {
                sidebar.classList.toggle('mobile-open');
                return;
            }
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('meow_admin_sidebar_collapsed', isCollapsed ? '1' : '0');
            btn.textContent = isCollapsed ? '>' : '<';
        }

        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeModal('catFormModal');
                closeModal('qrPreviewModal');
            }
        });

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) backdrop.classList.remove('open');
            });
        });

        function previewPhotoInput(input) {
            const img = document.getElementById('f_photo_preview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function (ev) {
                    img.src = ev.target.result;
                    img.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openCreateModal() {
            const form = document.getElementById('catFormEl');
            form.reset();
            form.action = '/admin/cats';
            document.getElementById('catFormTitle').textContent = 'Tambah Kucing Baru';
            document.getElementById('catFormSubmitBtn').textContent = 'Simpan Kucing & Buat QR';
            document.getElementById('f_gender').value = 'UNKNOWN';
            document.getElementById('f_age_group').value = 'ADULT';
            document.getElementById('f_health_status').value = 'HEALTHY';
            document.getElementById('f_adoption_status').value = 'UNREVIEWED';
            document.getElementById('f_presence_status').value = 'ON_CAMPUS';
            const preview = document.getElementById('f_photo_preview');
            preview.style.display = 'none';
            preview.src = '';
            openModal('catFormModal');
        }

        function openEditModal(cat) {
            const form = document.getElementById('catFormEl');
            form.reset();
            form.action = '/admin/cats/' + cat.id + '/update';
            document.getElementById('catFormTitle').textContent = 'Edit Kucing — ' + cat.name + ' (' + cat.unique_code + ')';
            document.getElementById('catFormSubmitBtn').textContent = 'Simpan Perubahan';

            document.getElementById('f_name').value = cat.name || '';
            document.getElementById('f_unique_code').value = cat.unique_code || '';
            document.getElementById('f_gender').value = cat.gender || 'UNKNOWN';
            document.getElementById('f_age_group').value = cat.age_group || 'UNKNOWN';
            document.getElementById('f_primary_color').value = cat.primary_color || '';
            document.getElementById('f_fur_pattern').value = cat.fur_pattern || '';
            document.getElementById('f_territory_id').value = cat.territory_id || '';
            document.getElementById('f_health_status').value = cat.health_status || 'HEALTHY';
            document.getElementById('f_is_sterilized').value = cat.is_sterilized ?? '';
            document.getElementById('f_is_vaccinated').value = cat.is_vaccinated ?? '';
            document.getElementById('f_vaccinated_at').value = cat.vaccinated_at || '';
            document.getElementById('f_vaccination_notes').value = cat.vaccination_notes || '';
            document.getElementById('f_temperament').value = cat.temperament || '';
            document.getElementById('f_special_needs').value = cat.special_needs || '';
            document.getElementById('f_adoption_status').value = cat.adoption_status || 'UNREVIEWED';
            document.getElementById('f_presence_status').value = cat.presence_status || 'ON_CAMPUS';
            document.getElementById('f_description').value = cat.description || '';

            const traits = cat.traits || {};
            document.getElementById('f_traits_marks').value = traits.physical_marks || '';
            document.getElementById('f_traits_ear').value = traits.ear_tip || '';
            document.getElementById('f_traits_tail').value = traits.tail_type || '';
            document.getElementById('f_traits_eye').value = traits.eye_color || '';

            const preview = document.getElementById('f_photo_preview');
            if (cat.photo_url) {
                preview.src = cat.photo_url;
                preview.style.display = 'block';
            } else {
                preview.style.display = 'none';
                preview.src = '';
            }
            openModal('catFormModal');
        }

        function openQrModal(catId, catName, uniqueCode) {
            const frontTpl = document.getElementById('qr-front-' + catId);
            const backTpl = document.getElementById('qr-back-shared');
            const meta = document.getElementById('qr-meta-' + catId);
            if (!frontTpl || !meta) return;

            document.getElementById('qrModalTitle').textContent = 'Tag Akrilik QR — ' + catName + ' (' + uniqueCode + ')';
            document.getElementById('qrModalFrontSlot').innerHTML = frontTpl.innerHTML;
            document.getElementById('qrModalBackSlot').innerHTML = backTpl.innerHTML;
            document.getElementById('qrModalPayload').textContent = meta.dataset.payload || '';
            document.getElementById('qrModalIssued').textContent = meta.dataset.issued || '—';

            document.getElementById('qrDownloadPng').href = '/admin/cats/' + catId + '/qr-download?format=png';
            document.getElementById('qrDownloadFront').href = '/admin/cats/' + catId + '/qr-download?format=acrylic_front';
            document.getElementById('qrDownloadBack').href = '/admin/cats/' + catId + '/qr-download?format=acrylic_back';
            document.getElementById('qrOpenSheet').href = '/admin/cats/' + catId + '/qr-sheet';

            const regenForm = document.getElementById('qrRegenerateForm');
            if (regenForm) regenForm.action = '/admin/cats/' + catId + '/qr';

            openModal('qrPreviewModal');
        }
    </script>
</body>
</html>
