<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meowtopia Admin — Katalog Kucing</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-page: #EEF3F9;
            --surface: #FFFFFF;
            --surface-subtle: #F8FAFC;
            --border: #E2E8F0;
            --border-strong: #CBD5E1;

            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-muted: #64748B;

            --sidebar-navy: #0F2442;
            --sidebar-navy-deep: #0B192C;
            --sidebar-navy-hover: rgba(255, 255, 255, 0.10);
            --sidebar-border: rgba(255, 255, 255, 0.12);
            --sidebar-text: #D7E4F5;
            --sidebar-text-muted: #8FA8C8;
            --sidebar-w: 256px;
            --sidebar-collapsed-w: 84px;
            --sidebar-gap: 14px;

            --primary: #EA580C;
            --primary-hover: #C2410C;
            --edit: #1D4ED8;
            --edit-bg: #EFF6FF;
            --edit-border: #BFDBFE;
            --success: #15803D;
            --success-bg: #ECFDF5;
            --success-border: #A7F3D0;
            --warning: #B45309;
            --warning-bg: #FFFBEB;
            --warning-border: #FDE68A;
            --danger: #DC2626;
            --danger-bg: #FEF2F2;
            --danger-border: #FECACA;

            --radius-bento: 20px;
            --shadow-bento: 0 4px 24px rgba(15, 36, 66, 0.05);
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

        /* ================= FLOATING ROUNDED BENTO SIDEBAR ================= */
        .sidebar {
            width: var(--sidebar-w);
            background: linear-gradient(180deg, #132C52 0%, var(--sidebar-navy-deep) 100%);
            color: #FFFFFF;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: var(--sidebar-gap);
            left: var(--sidebar-gap);
            bottom: var(--sidebar-gap);
            z-index: 60;
            border-radius: 22px;
            box-shadow: 0 14px 36px rgba(11, 25, 44, 0.18);
            transition: width 0.24s cubic-bezier(0.4, 0, 0.2, 1), transform 0.24s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: visible;
        }

        .sidebar-edge-toggle {
            position: fixed;
            top: 34px;
            left: calc(var(--sidebar-w) + var(--sidebar-gap) - 11px);
            z-index: 65;
            width: 24px;
            height: 24px;
            background: #FFFFFF;
            color: var(--sidebar-navy);
            border: 1px solid var(--border);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            box-shadow: 0 3px 10px rgba(15, 23, 42, 0.12);
            transition: left 0.24s cubic-bezier(0.4, 0, 0.2, 1), background 0.15s ease;
            user-select: none;
        }
        .sidebar-edge-toggle:hover { background: #F8FAFC; }

        .sidebar-brand {
            padding: 20px 18px 16px;
            border-bottom: 1px solid var(--sidebar-border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sidebar-logo {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            object-fit: cover;
            flex-shrink: 0;
            background: #FFFFFF;
            border: 2px solid rgba(255, 255, 255, 0.25);
        }
        .sidebar-brand-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            overflow: hidden;
            white-space: nowrap;
        }
        .sidebar-brand-title {
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.03em;
            color: #FFFFFF;
            line-height: 1.2;
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
            overflow-y: auto;
        }

        .nav-section-label {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--sidebar-text-muted);
            padding: 12px 12px 4px;
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
            padding: 9px 13px;
            border: none;
            border-radius: 12px;
            background: transparent;
            color: var(--sidebar-text);
            font-family: inherit;
            font-size: 13.5px;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        }
        .nav-item:hover { background: var(--sidebar-navy-hover); color: #FFFFFF; }
        .nav-item.active {
            background: #FFFFFF;
            color: var(--sidebar-navy);
            font-weight: 700;
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
        }

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
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            color: #FFFFFF;
            flex-shrink: 0;
        }
        .nav-item.active .nav-count {
            background: #FFF7ED;
            color: var(--primary);
        }

        .sidebar-footer {
            padding: 14px 12px 16px;
            border-top: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 10px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.08);
            min-width: 0;
        }
        .sidebar-user-avatar {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--primary);
            color: #FFFFFF;
            font-weight: 700;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .sidebar-user-info { min-width: 0; overflow: hidden; }
        .sidebar-user-name {
            font-size: 12.5px;
            font-weight: 600;
            color: #FFFFFF;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .sidebar-user-role { font-size: 11px; color: var(--sidebar-text-muted); }
        .sidebar-logout-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.16);
            background: transparent;
            color: var(--sidebar-text);
            font-family: inherit;
            font-size: 12.5px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease;
        }
        .sidebar-logout-btn:hover { background: rgba(255, 255, 255, 0.12); color: #FFFFFF; }

        body.sidebar-collapsed .sidebar { width: var(--sidebar-collapsed-w); }
        body.sidebar-collapsed .sidebar-edge-toggle { left: calc(var(--sidebar-collapsed-w) + var(--sidebar-gap) - 11px); }
        body.sidebar-collapsed .sidebar-brand-text,
        body.sidebar-collapsed .nav-item-text,
        body.sidebar-collapsed .nav-count,
        body.sidebar-collapsed .nav-section-label,
        body.sidebar-collapsed .sidebar-user-info,
        body.sidebar-collapsed .logout-text { display: none; }
        body.sidebar-collapsed .sidebar-brand,
        body.sidebar-collapsed .sidebar-user { justify-content: center; padding-left: 0; padding-right: 0; }
        body.sidebar-collapsed .nav-item { justify-content: center; padding: 10px 0; }

        /* ================= CENTERED MAIN AREA ================= */
        .main-wrapper {
            margin-left: calc(var(--sidebar-w) + (var(--sidebar-gap) * 2));
            flex: 1;
            min-width: 0;
            display: flex;
            flex-direction: column;
            transition: margin-left 0.24s cubic-bezier(0.4, 0, 0.2, 1);
        }
        body.sidebar-collapsed .main-wrapper {
            margin-left: calc(var(--sidebar-collapsed-w) + (var(--sidebar-gap) * 2));
        }

        .page-content {
            max-width: 1200px;
            width: 100%;
            margin: 0 auto;
            padding: 20px 24px 48px;
        }

        /* ================= BENTO HERO HEADER CARD ================= */
        .bento-hero {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #FFFFFF 0%, #FFF7ED 60%, #FFEDD5 100%);
            border: 1px solid #FED7AA;
            border-radius: var(--radius-bento);
            padding: 22px 26px;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            flex-wrap: wrap;
            box-shadow: var(--shadow-bento);
        }
        .bento-hero-watermark {
            position: absolute;
            right: 22%;
            bottom: -24px;
            width: 140px;
            height: 140px;
            color: #FB923C;
            opacity: 0.14;
            pointer-events: none;
            transform: rotate(-14deg);
        }
        .bento-hero-left { position: relative; z-index: 2; }
        .bento-hero-title {
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.025em;
            color: var(--text-primary);
            line-height: 1.25;
        }
        .bento-hero-sub {
            font-size: 13.5px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        .bento-hero-actions {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* ================= ALERTS ================= */
        .alert {
            padding: 13px 18px;
            border-radius: 14px;
            border: 1px solid var(--border);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-size: 13.5px;
            box-shadow: 0 2px 10px rgba(15, 23, 42, 0.04);
        }
        .alert-success { background: var(--success-bg); border-color: var(--success-border); color: var(--success); }
        .alert-danger { background: var(--danger-bg); border-color: var(--danger-border); color: var(--danger); }
        .alert-close {
            background: none;
            border: none;
            font-size: 19px;
            line-height: 1;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            padding: 2px 6px;
        }
        .alert-close:hover { opacity: 1; }

        /* ================= BENTO STAT CARDS WITH TRANSLUCENT PAW WATERMARKS ================= */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 18px;
        }

        .stat-item {
            position: relative;
            overflow: hidden;
            border-radius: 18px;
            padding: 16px 18px 18px;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border: 1px solid transparent;
            box-shadow: var(--shadow-bento);
            transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .stat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(15, 36, 66, 0.09);
        }
        .stat-item.active {
            box-shadow: 0 0 0 2px var(--primary), 0 8px 20px rgba(234, 88, 12, 0.12);
        }

        .stat-card-orange { background: linear-gradient(145deg, #FFFFFF 0%, #FFF3E6 100%); border-color: #FED7AA; --card-accent: #EA580C; --chip-bg: #FFEDD5; }
        .stat-card-green  { background: linear-gradient(145deg, #FFFFFF 0%, #E8FBF1 100%); border-color: #A7F3D0; --card-accent: #15803D; --chip-bg: #DCFCE7; }
        .stat-card-rose   { background: linear-gradient(145deg, #FFFFFF 0%, #FFF0F1 100%); border-color: #FECDD3; --card-accent: #E11D48; --chip-bg: #FFE4E6; }
        .stat-card-blue   { background: linear-gradient(145deg, #FFFFFF 0%, #EAF3FF 100%); border-color: #BFDBFE; --card-accent: #1D4ED8; --chip-bg: #DBEAFE; }
        .stat-card-violet { background: linear-gradient(145deg, #FFFFFF 0%, #F3EEFF 100%); border-color: #DDD6FE; --card-accent: #6D28D9; --chip-bg: #EDE9FE; }

        .stat-top {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-icon-chip {
            width: 36px;
            height: 36px;
            border-radius: 11px;
            background: var(--chip-bg);
            color: var(--card-accent);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .stat-icon-chip svg { width: 20px; height: 20px; }

        .stat-body { position: relative; z-index: 2; }
        .stat-value {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.03em;
            color: var(--text-primary);
            line-height: 1.1;
            font-variant-numeric: tabular-nums;
        }
        .stat-label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .stat-watermark {
            position: absolute;
            right: -10px;
            bottom: -14px;
            width: 78px;
            height: 78px;
            color: var(--card-accent);
            opacity: 0.34;
            pointer-events: none;
            transform: rotate(-16deg);
            transition: transform 0.2s ease, opacity 0.2s ease;
        }
        .stat-item:hover .stat-watermark {
            opacity: 0.46;
            transform: rotate(-10deg) scale(1.05);
        }

        /* ================= MAIN BENTO TABLE PANEL ================= */
        .panel {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-bento);
            overflow: visible;
            box-shadow: var(--shadow-bento);
        }
        .toolbar {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            flex: 1;
        }

        /* Search Input with Icon */
        .search-wrap {
            position: relative;
            flex: 1;
            min-width: 200px;
            max-width: 280px;
        }
        .search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 15px;
            height: 15px;
            color: var(--text-muted);
            pointer-events: none;
        }

        .input, .select, .textarea {
            font-family: inherit;
            font-size: 13px;
            color: var(--text-primary);
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            border-radius: 11px;
            padding: 8px 12px;
            height: 38px;
            transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
        }
        .input-search {
            width: 100%;
            padding-left: 36px;
        }
        .textarea { height: auto; min-height: 76px; resize: vertical; line-height: 1.5; }
        .input:focus, .select:focus, .textarea:focus {
            outline: none;
            background: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }

        /* Native modal select styled cleanly with proper right chevron spacing */
        .select {
            appearance: none;
            -webkit-appearance: none;
            padding-right: 36px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748B' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 15px 15px;
            cursor: pointer;
        }

        /* ================= CUSTOM DROPDOWN (TOOLBAR FILTERS) ================= */
        .custom-dd {
            position: relative;
            min-width: 156px;
        }
        .custom-dd-trigger {
            width: 100%;
            height: 38px;
            padding: 0 14px;
            border-radius: 11px;
            border: 1px solid var(--border);
            background: var(--surface-subtle);
            color: var(--text-primary);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            cursor: pointer;
            transition: border-color 0.15s ease, background 0.15s ease, box-shadow 0.15s ease;
            white-space: nowrap;
        }
        .custom-dd-trigger:hover {
            background: var(--surface);
            border-color: var(--border-strong);
        }
        .custom-dd.open .custom-dd-trigger {
            background: var(--surface);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.12);
        }
        .custom-dd.has-value .custom-dd-trigger {
            background: #FFF7ED;
            border-color: #FDBA74;
            color: #9A3412;
            font-weight: 600;
        }
        .custom-dd-label {
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 170px;
        }
        .custom-dd-chevron {
            width: 15px;
            height: 15px;
            flex-shrink: 0;
            color: var(--text-muted);
            transition: transform 0.18s ease;
        }
        .custom-dd.open .custom-dd-chevron {
            transform: rotate(180deg);
            color: var(--primary);
        }
        .custom-dd-menu {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            min-width: 100%;
            width: max-content;
            max-width: 280px;
            max-height: 260px;
            overflow-y: auto;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 13px;
            padding: 6px;
            box-shadow: 0 14px 32px rgba(15, 36, 66, 0.14);
            z-index: 90;
            display: none;
        }
        .custom-dd.open .custom-dd-menu { display: block; }
        .custom-dd-option {
            width: 100%;
            border: none;
            background: transparent;
            color: var(--text-primary);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            padding: 8px 11px;
            border-radius: 8px;
            text-align: left;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            cursor: pointer;
            transition: background 0.12s ease;
        }
        .custom-dd-option:hover { background: var(--surface-subtle); }
        .custom-dd-option.selected {
            background: #FFF7ED;
            color: var(--primary);
            font-weight: 600;
        }

        /* ================= BUTTONS & ICON ACTIONS ================= */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            height: 38px;
            padding: 0 16px;
            border-radius: 10px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        }
        .btn-sm { height: 32px; padding: 0 11px; font-size: 12px; border-radius: 8px; }
        .btn-primary { background: var(--primary); color: #FFFFFF; box-shadow: 0 4px 12px rgba(234, 88, 12, 0.22); }
        .btn-primary:hover { background: var(--primary-hover); }
        .btn-neutral { background: var(--surface); color: var(--text-primary); border-color: var(--border-strong); }
        .btn-neutral:hover { background: var(--surface-subtle); }
        .btn-danger { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }
        .btn-danger:hover { background: #FEE2E2; }

        /* Compact Icon-only Table Action Buttons */
        .actions-cell { display: flex; align-items: center; gap: 6px; }
        .icon-btn {
            width: 34px;
            height: 34px;
            border-radius: 9px;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.14s ease, border-color 0.14s ease, transform 0.12s ease;
        }
        .icon-btn:hover { transform: translateY(-1px); }
        .icon-btn svg { width: 16px; height: 16px; }

        .icon-btn-detail { background: #F1F5F9; color: #334155; border-color: #E2E8F0; }
        .icon-btn-detail:hover { background: #E2E8F0; color: #0F172A; }

        .icon-btn-edit { background: var(--edit-bg); color: var(--edit); border-color: var(--edit-border); }
        .icon-btn-edit:hover { background: #DBEAFE; }

        .icon-btn-qr { background: #FFF7ED; color: var(--primary); border-color: #FED7AA; }
        .icon-btn-qr:hover { background: #FFEDD5; }

        .icon-btn-delete { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }
        .icon-btn-delete:hover { background: #FEE2E2; }

        .icon-btn-restore { background: var(--success-bg); color: var(--success); border-color: var(--success-border); }
        .icon-btn-restore:hover { background: #DCFCE7; }

        /* ================= COMPACT SCANNABLE TABLE ================= */
        .table-wrap { width: 100%; overflow-x: auto; border-radius: 0 0 var(--radius-bento) var(--radius-bento); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            background: var(--surface-subtle);
            padding: 12px 18px;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }
        td {
            padding: 13px 18px;
            border-bottom: 1px solid var(--border);
            vertical-align: middle;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover { background: #F9FBFD; }
        tbody tr.is-deleted { opacity: 0.6; background: #F8FAFC; }

        .cat-cell { display: flex; align-items: center; gap: 12px; min-width: 190px; }
        .cat-thumb {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            object-fit: cover;
            border: 1px solid var(--border);
            flex-shrink: 0;
            background: #FFF7ED;
        }
        .cat-avatar-fallback {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #FFF7ED;
            border: 1px solid #FED7AA;
            color: #C2410C;
            font-weight: 800;
            font-size: 15px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cat-name { font-weight: 700; color: var(--text-primary); font-size: 14px; }
        .cat-meta { font-size: 12px; color: var(--text-muted); margin-top: 2px; }

        /* ================= BADGES ================= */
        .badge-group { display: flex; flex-wrap: wrap; gap: 5px; }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 3px 9px;
            border-radius: 999px;
            font-size: 11.5px;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .badge-success { background: var(--success-bg); color: var(--success); border-color: var(--success-border); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); border-color: var(--warning-border); }
        .badge-danger { background: var(--danger-bg); color: var(--danger); border-color: var(--danger-border); }
        .badge-info { background: var(--edit-bg); color: var(--edit); border-color: var(--edit-border); }
        .badge-neutral { background: var(--surface-subtle); color: var(--text-secondary); border-color: var(--border); }

        /* ================= PAGINATION FOOTER ================= */
        .pagination-bar {
            padding: 14px 20px;
            border-top: 1px solid var(--border);
            background: var(--surface-subtle);
            border-radius: 0 0 var(--radius-bento) var(--radius-bento);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }
        .pagination-info { font-size: 12.5px; color: var(--text-muted); font-weight: 500; }
        .pagination-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .page-btn {
            min-width: 34px;
            height: 34px;
            padding: 0 11px;
            border-radius: 9px;
            border: 1px solid var(--border);
            background: var(--surface);
            color: var(--text-primary);
            font-size: 12.5px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease, border-color 0.15s ease;
        }
        .page-btn:hover:not(.disabled):not(.active) { background: #F1F5F9; border-color: var(--border-strong); }
        .page-btn.active { background: var(--sidebar-navy); color: #FFFFFF; border-color: var(--sidebar-navy); }
        .page-btn.disabled { opacity: 0.45; pointer-events: none; }

        .empty-state { padding: 56px 24px; text-align: center; color: var(--text-muted); }
        .empty-state-title { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 6px; }

        /* ================= MODALS (PROPER FLEX SCROLLING!) ================= */
        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.52);
            backdrop-filter: blur(2px);
            z-index: 120;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-backdrop.open { display: flex; }
        .modal-box {
            background: var(--surface);
            border-radius: 20px;
            border: 1px solid var(--border);
            width: 100%;
            max-width: 780px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 28px 56px rgba(15, 23, 42, 0.24);
        }
        .modal-box.modal-qr { max-width: 820px; }
        #catFormModal .modal-box { height: min(88vh, 860px); }
        .modal-form {
            display: flex;
            flex-direction: column;
            flex: 1 1 auto;
            min-height: 0;
            overflow: hidden;
        }
        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-shrink: 0;
        }
        .modal-title { font-size: 16.5px; font-weight: 700; color: var(--text-primary); }
        .modal-body {
            padding: 20px 24px;
            overflow-y: auto;
            flex: 1 1 auto;
            min-height: 0;
            -webkit-overflow-scrolling: touch;
        }
        .modal-footer {
            padding: 14px 24px;
            border-top: 1px solid var(--border);
            background: var(--surface-subtle);
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 8px;
            flex-wrap: wrap;
            flex-shrink: 0;
        }

        /* ================= DETAIL MODAL BENTO LAYOUT ================= */
        .detail-hero {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 18px;
            border-radius: 16px;
            background: linear-gradient(135deg, #F8FAFC 0%, #FFF7ED 100%);
            border: 1px solid #FED7AA;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }
        .detail-photo {
            width: 68px;
            height: 68px;
            border-radius: 16px;
            object-fit: cover;
            border: 2px solid #FFFFFF;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            display: none;
        }
        .detail-avatar {
            width: 68px;
            height: 68px;
            border-radius: 16px;
            background: #FFEDD5;
            color: #C2410C;
            font-size: 26px;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .detail-id-row {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 6px;
        }
        .id-pill {
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 11.5px;
            font-weight: 600;
            background: #FFFFFF;
            border: 1px solid var(--border-strong);
            color: var(--text-secondary);
            padding: 3px 9px;
            border-radius: 7px;
        }
        .detail-bento-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 12px;
        }
        .detail-card {
            background: var(--surface-subtle);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
        }
        .detail-card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 10px;
        }
        .kv-list { display: flex; flex-direction: column; gap: 7px; }
        .kv-row { display: flex; justify-content: space-between; gap: 12px; font-size: 13px; }
        .kv-key { color: var(--text-muted); flex-shrink: 0; }
        .kv-val { color: var(--text-primary); font-weight: 600; text-align: right; }

        /* ================= FORM SECTIONS ================= */
        .form-section {
            margin-bottom: 20px;
            padding-bottom: 18px;
            border-bottom: 1px solid var(--border);
        }
        .form-section:last-child { margin-bottom: 0; padding-bottom: 0; border-bottom: none; }
        .form-section-title {
            font-size: 11.5px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-muted);
            margin-bottom: 12px;
        }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px 14px; }
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
            border-radius: 12px;
            background: var(--surface-subtle);
        }
        .photo-preview-img {
            width: 56px;
            height: 56px;
            border-radius: 10px;
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
            border-radius: 14px;
            margin-bottom: 16px;
        }
        .acrylic-card { display: flex; flex-direction: column; align-items: center; gap: 10px; }
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
            border-radius: 10px;
            font-size: 12.5px;
        }

        @media (max-width: 1120px) {
            .stats-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
        }
        @media (max-width: 860px) {
            .sidebar { top: 0; left: 0; bottom: 0; border-radius: 0 20px 20px 0; transform: translateX(-100%); }
            .sidebar.mobile-open { transform: translateX(0); }
            .sidebar-edge-toggle { left: 10px; top: 18px; }
            .sidebar.mobile-open + .sidebar-edge-toggle { left: calc(var(--sidebar-w) - 12px); }
            .main-wrapper { margin-left: 0 !important; }
            .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .form-grid, .form-grid-3, .acrylic-stage, .detail-bento-grid { grid-template-columns: 1fr; }
            .field.span-2 { grid-column: span 1; }
            .page-content { padding: 16px 14px 36px; }
        }
    </style>
</head>
<body>
@php
    $genderLabels = ['MALE' => 'Jantan', 'FEMALE' => 'Betina', 'UNKNOWN' => 'Belum diketahui'];
    $ageLabels = ['KITTEN' => 'Anak (Kitten)', 'ADULT' => 'Dewasa', 'SENIOR' => 'Senior', 'UNKNOWN' => 'Belum diketahui'];
    $healthLabels = ['HEALTHY' => 'Sehat', 'SICK' => 'Sakit', 'INJURED' => 'Terluka', 'UNDER_CARE' => 'Dalam Perawatan'];
    $healthFilterOptions = array_merge(['NEEDS_CARE' => 'Perlu Perawatan'], $healthLabels);
    $adoptionLabels = [
        'UNREVIEWED' => 'Belum Dinilai',
        'READY' => 'Siap Adopsi',
        'ADOPTED' => 'Diadopsi',
        'RETURN_PENDING' => 'Proses Kembali',
        'RETURNED_REVIEW' => 'Review Kembali',
    ];
    $presenceLabels = ['ON_CAMPUS' => 'Di Kampus', 'ADOPTED' => 'Diadopsi', 'MISSING' => 'Hilang', 'DECEASED' => 'Meninggal'];
    $userInitial = strtoupper(mb_substr(auth()->user()->name ?? 'A', 0, 1));

    $selectedHealthLabel = $filters['health_status'] !== '' ? ($healthFilterOptions[$filters['health_status']] ?? 'Kesehatan') : 'Semua Kesehatan';
    $selectedAdoptionLabel = $filters['adoption_status'] !== '' ? ($adoptionLabels[$filters['adoption_status']] ?? 'Adopsi') : 'Semua Adopsi';
    $selectedTerritory = $territories->firstWhere('id', $filters['territory_id']);
    $selectedTerritoryLabel = $selectedTerritory ? ($selectedTerritory->code . ' — ' . $selectedTerritory->name) : 'Semua Zona';

    $hasActiveFilter = $filters['search'] !== ''
        || $filters['health_status'] !== ''
        || $filters['adoption_status'] !== ''
        || $filters['territory_id'] !== ''
        || $filters['is_sterilized'] !== ''
        || $filters['is_vaccinated'] !== '';
@endphp

    <!-- FLOATING ROUNDED BENTO SIDEBAR -->
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

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <span class="sidebar-user-avatar">{{ $userInitial }}</span>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">{{ auth()->user()->name }}</div>
                    <div class="sidebar-user-role">Administrator</div>
                </div>
            </div>
            <form method="POST" action="/admin/feeding/logout">
                @csrf
                <button type="submit" class="sidebar-logout-btn" title="Keluar">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                    <span class="logout-text">Keluar</span>
                </button>
            </form>
        </div>
    </aside>

    <button type="button" class="sidebar-edge-toggle" id="sidebarToggleBtn" onclick="toggleSidebar()" title="Toggle Sidebar" aria-label="Toggle Sidebar">&lt;</button>

    <div class="main-wrapper">
        <main class="page-content">
            <!-- BENTO HERO HEADER -->
            <section class="bento-hero">
                <svg class="bento-hero-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                    <circle cx="7" cy="8.5" r="2.2"/>
                    <circle cx="11.5" cy="5.5" r="2.2"/>
                    <circle cx="16.5" cy="7.5" r="2.2"/>
                    <circle cx="19" cy="12" r="2"/>
                    <path d="M12.2 10.6c-3.4 0-6.2 2.9-6.2 5.7 0 2.1 1.6 3.5 3.6 3.5 1.1 0 1.9-.3 2.6-.3.8 0 1.6.3 2.7.3 2 0 3.5-1.4 3.5-3.5 0-2.9-2.8-5.7-6.2-5.7Z"/>
                </svg>
                <div class="bento-hero-left">
                    <h1 class="bento-hero-title">Katalog Kucing</h1>
                    <p class="bento-hero-sub">Kelola profil kucing kampus dan cetak tag QR akrilik kalung secara otomatis.</p>
                </div>
                <div class="bento-hero-actions">
                    <button type="button" class="btn btn-primary" onclick="openCreateModal()">+ Tambah Kucing</button>
                </div>
            </section>

            @if(request()->filled('notice'))
                <div class="alert alert-success">
                    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span>{{ request()->query('notice') }}</span>
                        @php $highlightCatId = request()->query('created_cat') ?? request()->query('regenerated_cat'); @endphp
                        @if($highlightCatId)
                            <a href="/admin/cats/{{ $highlightCatId }}/qr-sheet" target="_blank" class="btn btn-neutral btn-sm">Cetak Tag Akrilik</a>
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

            <!-- BENTO STAT CARDS WITH TRANSLUCENT PAW WATERMARKS -->
            <div class="stats-grid">
                <a href="/admin/cats" class="stat-item stat-card-orange {{ empty($filters['adoption_status']) && empty($filters['health_status']) && $filters['is_sterilized'] === '' && $filters['is_vaccinated'] === '' ? 'active' : '' }}">
                    <div class="stat-top">
                        <span class="stat-icon-chip">
                            <svg viewBox="0 0 24 24" fill="currentColor"><circle cx="7" cy="9" r="2"/><circle cx="11.5" cy="6" r="2"/><circle cx="16.5" cy="8" r="2"/><circle cx="19" cy="12.5" r="1.8"/><path d="M12.2 11c-3.1 0-5.7 2.6-5.7 5.2 0 1.9 1.4 3.3 3.3 3.3 1 0 1.7-.3 2.4-.3s1.5.3 2.5.3c1.8 0 3.2-1.4 3.2-3.3 0-2.6-2.6-5.2-5.7-5.2Z"/></svg>
                        </span>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ $stats['total'] }}</div>
                        <div class="stat-label">Kucing Aktif</div>
                    </div>
                    <svg class="stat-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="7" cy="9" r="2.2"/><circle cx="11.5" cy="6" r="2.2"/><circle cx="16.5" cy="8" r="2.2"/><circle cx="19" cy="12.5" r="2"/><path d="M12.2 11c-3.2 0-5.9 2.7-5.9 5.4 0 2 1.5 3.4 3.4 3.4 1 0 1.8-.3 2.5-.3s1.5.3 2.6.3c1.9 0 3.3-1.4 3.3-3.4 0-2.7-2.7-5.4-5.9-5.4Z"/>
                    </svg>
                </a>

                <a href="/admin/cats?adoption_status=READY" class="stat-item stat-card-green {{ $filters['adoption_status'] === 'READY' ? 'active' : '' }}">
                    <div class="stat-top">
                        <span class="stat-icon-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
                        </span>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ $stats['ready_adoption'] }}</div>
                        <div class="stat-label">Siap Adopsi</div>
                    </div>
                    <svg class="stat-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="7" cy="9" r="2.2"/><circle cx="11.5" cy="6" r="2.2"/><circle cx="16.5" cy="8" r="2.2"/><circle cx="19" cy="12.5" r="2"/><path d="M12.2 11c-3.2 0-5.9 2.7-5.9 5.4 0 2 1.5 3.4 3.4 3.4 1 0 1.8-.3 2.5-.3s1.5.3 2.6.3c1.9 0 3.3-1.4 3.3-3.4 0-2.7-2.7-5.4-5.9-5.4Z"/>
                    </svg>
                </a>

                <a href="/admin/cats?health_status=NEEDS_CARE" class="stat-item stat-card-rose {{ in_array($filters['health_status'], ['NEEDS_CARE', 'SICK', 'INJURED', 'UNDER_CARE'], true) ? 'active' : '' }}">
                    <div class="stat-top">
                        <span class="stat-icon-chip">
                            <!-- Bold solid medical cross (unmistakably medical care, not add) -->
                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M9.5 3a1.5 1.5 0 0 0-1.5 1.5V8H4.5A1.5 1.5 0 0 0 3 9.5v5A1.5 1.5 0 0 0 4.5 16H8v3.5A1.5 1.5 0 0 0 9.5 21h5a1.5 1.5 0 0 0 1.5-1.5V16h3.5a1.5 1.5 0 0 0 1.5-1.5v-5A1.5 1.5 0 0 0 19.5 8H16V4.5A1.5 1.5 0 0 0 14.5 3h-5Z"/>
                            </svg>
                        </span>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ $stats['needs_care'] }}</div>
                        <div class="stat-label">Perlu Perawatan</div>
                    </div>
                    <svg class="stat-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="7" cy="9" r="2.2"/><circle cx="11.5" cy="6" r="2.2"/><circle cx="16.5" cy="8" r="2.2"/><circle cx="19" cy="12.5" r="2"/><path d="M12.2 11c-3.2 0-5.9 2.7-5.9 5.4 0 2 1.5 3.4 3.4 3.4 1 0 1.8-.3 2.5-.3s1.5.3 2.6.3c1.9 0 3.3-1.4 3.3-3.4 0-2.7-2.7-5.4-5.9-5.4Z"/>
                    </svg>
                </a>

                <a href="/admin/cats?is_sterilized=1" class="stat-item stat-card-blue {{ $filters['is_sterilized'] === '1' ? 'active' : '' }}">
                    <div class="stat-top">
                        <span class="stat-icon-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/><path d="m9 12 2 2 4-4"/></svg>
                        </span>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ $stats['sterilized'] }}</div>
                        <div class="stat-label">Sudah Steril</div>
                    </div>
                    <svg class="stat-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="7" cy="9" r="2.2"/><circle cx="11.5" cy="6" r="2.2"/><circle cx="16.5" cy="8" r="2.2"/><circle cx="19" cy="12.5" r="2"/><path d="M12.2 11c-3.2 0-5.9 2.7-5.9 5.4 0 2 1.5 3.4 3.4 3.4 1 0 1.8-.3 2.5-.3s1.5.3 2.6.3c1.9 0 3.3-1.4 3.3-3.4 0-2.7-2.7-5.4-5.9-5.4Z"/>
                    </svg>
                </a>

                <a href="/admin/cats?is_vaccinated=1" class="stat-item stat-card-violet {{ $filters['is_vaccinated'] === '1' ? 'active' : '' }}">
                    <div class="stat-top">
                        <span class="stat-icon-chip">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 2 4 4"/><path d="m17 7 3-3"/><path d="M19 9 8.7 19.3c-1 1-2.5 1-3.4 0l-.6-.6c-1-1-1-2.5 0-3.4L15 5"/><path d="m9 11 4 4"/><path d="m5 19-3 3"/></svg>
                        </span>
                    </div>
                    <div class="stat-body">
                        <div class="stat-value">{{ $stats['vaccinated'] }}</div>
                        <div class="stat-label">Sudah Vaksin</div>
                    </div>
                    <svg class="stat-watermark" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                        <circle cx="7" cy="9" r="2.2"/><circle cx="11.5" cy="6" r="2.2"/><circle cx="16.5" cy="8" r="2.2"/><circle cx="19" cy="12.5" r="2"/><path d="M12.2 11c-3.2 0-5.9 2.7-5.9 5.4 0 2 1.5 3.4 3.4 3.4 1 0 1.8-.3 2.5-.3s1.5.3 2.6.3c1.9 0 3.3-1.4 3.3-3.4 0-2.7-2.7-5.4-5.9-5.4Z"/>
                    </svg>
                </a>
            </div>

            <!-- MAIN BENTO TABLE PANEL -->
            <div class="panel">
                <form method="GET" action="/admin/cats" id="catalogFilterForm" class="toolbar">
                    <div class="filter-row">
                        <div class="search-wrap">
                            <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                            <input type="text" name="search" id="searchInput" value="{{ $filters['search'] }}" placeholder="Cari kucing atau ID..." class="input input-search" autocomplete="off">
                        </div>

                        <!-- Custom Dropdown: Kesehatan -->
                        <div class="custom-dd {{ $filters['health_status'] !== '' ? 'has-value' : '' }}" data-dd="health_status">
                            <input type="hidden" name="health_status" value="{{ $filters['health_status'] }}">
                            <button type="button" class="custom-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-dd-label">{{ $selectedHealthLabel }}</span>
                                <svg class="custom-dd-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="custom-dd-menu" role="listbox">
                                <button type="button" class="custom-dd-option {{ $filters['health_status'] === '' ? 'selected' : '' }}" data-value="">
                                    <span>Semua Kesehatan</span>
                                </button>
                                @foreach($healthFilterOptions as $k => $v)
                                    <button type="button" class="custom-dd-option {{ $filters['health_status'] === $k ? 'selected' : '' }}" data-value="{{ $k }}">
                                        <span>{{ $v }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Custom Dropdown: Adopsi -->
                        <div class="custom-dd {{ $filters['adoption_status'] !== '' ? 'has-value' : '' }}" data-dd="adoption_status">
                            <input type="hidden" name="adoption_status" value="{{ $filters['adoption_status'] }}">
                            <button type="button" class="custom-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-dd-label">{{ $selectedAdoptionLabel }}</span>
                                <svg class="custom-dd-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="custom-dd-menu" role="listbox">
                                <button type="button" class="custom-dd-option {{ $filters['adoption_status'] === '' ? 'selected' : '' }}" data-value="">
                                    <span>Semua Adopsi</span>
                                </button>
                                @foreach($adoptionLabels as $k => $v)
                                    <button type="button" class="custom-dd-option {{ $filters['adoption_status'] === $k ? 'selected' : '' }}" data-value="{{ $k }}">
                                        <span>{{ $v }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Custom Dropdown: Zona -->
                        <div class="custom-dd {{ $filters['territory_id'] !== '' ? 'has-value' : '' }}" data-dd="territory_id">
                            <input type="hidden" name="territory_id" value="{{ $filters['territory_id'] }}">
                            <button type="button" class="custom-dd-trigger" aria-haspopup="listbox" aria-expanded="false">
                                <span class="custom-dd-label">{{ $selectedTerritoryLabel }}</span>
                                <svg class="custom-dd-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>
                            <div class="custom-dd-menu" role="listbox">
                                <button type="button" class="custom-dd-option {{ $filters['territory_id'] === '' ? 'selected' : '' }}" data-value="">
                                    <span>Semua Zona</span>
                                </button>
                                @foreach($territories as $t)
                                    <button type="button" class="custom-dd-option {{ $filters['territory_id'] === $t->id ? 'selected' : '' }}" data-value="{{ $t->id }}">
                                        <span>{{ $t->code }} &mdash; {{ $t->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        @if($filters['is_sterilized'] !== '')
                            <input type="hidden" name="is_sterilized" value="{{ $filters['is_sterilized'] }}">
                        @endif
                        @if($filters['is_vaccinated'] !== '')
                            <input type="hidden" name="is_vaccinated" value="{{ $filters['is_vaccinated'] }}">
                        @endif
                    </div>

                    @if($hasActiveFilter)
                        <a href="/admin/cats" class="btn btn-neutral btn-sm">Reset</a>
                    @endif
                </form>

                <div class="table-wrap">
                    @if($cats->isEmpty())
                        <div class="empty-state">
                            <div class="empty-state-title">Belum ada data kucing yang sesuai filter.</div>
                            <div style="font-size:13px; margin-bottom:16px;">Coba reset filter atau tambahkan kucing baru.</div>
                            <button type="button" class="btn btn-primary btn-sm" onclick="openCreateModal()">+ Tambah Kucing</button>
                        </div>
                    @else
                        <table>
                            <thead>
                                <tr>
                                    <th>Kucing</th>
                                    <th>Zona</th>
                                    <th>Kesehatan</th>
                                    <th>Adopsi</th>
                                    <th style="width:170px;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($cats as $cat)
                                    @php
                                        $photoUrl = $cat->primary_photo_media_id
                                            ? route('cats.photo', ['id' => $cat->id, 'mediaId' => $cat->primary_photo_media_id], false)
                                            : null;
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
                                            'gender_label' => $genderLabels[$cat->gender] ?? $cat->gender,
                                            'age_group' => $cat->age_group,
                                            'age_label' => $ageLabels[$cat->age_group] ?? $cat->age_group,
                                            'primary_color' => $cat->primary_color ?? '',
                                            'fur_pattern' => $cat->fur_pattern ?? '',
                                            'color_pattern' => $cat->color_pattern ?? '',
                                            'is_sterilized' => $cat->is_sterilized === null ? '' : ($cat->is_sterilized ? '1' : '0'),
                                            'is_vaccinated' => $cat->is_vaccinated === null ? '' : ($cat->is_vaccinated ? '1' : '0'),
                                            'vaccinated_at' => $cat->vaccinated_at?->toDateString() ?? '',
                                            'vaccination_notes' => $cat->vaccination_notes ?? '',
                                            'temperament' => $cat->temperament ?? '',
                                            'special_needs' => $cat->special_needs ?? '',
                                            'description' => $cat->description ?? '',
                                            'territory_id' => $cat->territory_id ?? '',
                                            'territory_name' => $cat->territory ? ($cat->territory->code . ' — ' . $cat->territory->name) : 'Belum ditentukan',
                                            'game_rarity' => $gameRarity ?? '—',
                                            'presence_status' => $cat->presence_status,
                                            'presence_label' => $presenceLabels[$cat->presence_status] ?? $cat->presence_status,
                                            'health_status' => $cat->health_status,
                                            'health_label' => $healthLabels[$cat->health_status] ?? $cat->health_status,
                                            'adoption_status' => $cat->adoption_status,
                                            'adoption_label' => $adoptionLabels[$cat->adoption_status] ?? $cat->adoption_status,
                                            'traits' => $cat->traits ?? [],
                                            'photo_url' => $photoUrl,
                                            'has_qr' => isset($qrPreviewMap[$cat->id]),
                                            'trashed' => $cat->trashed(),
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
                                                    <div class="cat-meta">{{ $genderLabels[$cat->gender] ?? $cat->gender }} &middot; {{ $ageLabels[$cat->age_group] ?? $cat->age_group }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span style="font-weight:600; color:var(--text-primary);">{{ $cat->territory?->code ?? '—' }}</span>
                                            @if($cat->territory)
                                                <div style="font-size:11.5px; color:var(--text-muted); max-width:210px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $cat->territory->name }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="badge-group">
                                                <span class="badge {{ $healthBadge }}">{{ $healthLabels[$cat->health_status] ?? $cat->health_status }}</span>
                                                @if($cat->is_sterilized === true)
                                                    <span class="badge badge-info">Steril</span>
                                                @endif
                                                @if($cat->is_vaccinated === true)
                                                    <span class="badge badge-neutral">Vaksin</span>
                                                @endif
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge {{ $adoptionBadge }}">{{ $adoptionLabels[$cat->adoption_status] ?? $cat->adoption_status }}</span>
                                        </td>
                                        <td>
                                            <div class="actions-cell">
                                                <button type="button" class="icon-btn icon-btn-detail" title="Detail kucing" aria-label="Detail {{ $cat->name }}" onclick='openDetailModal(@json($catJson))'>
                                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>
                                                </button>
                                                @if(!$cat->trashed())
                                                    <button type="button" class="icon-btn icon-btn-edit" title="Edit profil" aria-label="Edit {{ $cat->name }}" onclick='openEditModal(@json($catJson))'>
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/><path d="m15 5 4 4"/></svg>
                                                    </button>
                                                    @if(isset($qrPreviewMap[$cat->id]))
                                                        <button type="button" class="icon-btn icon-btn-qr" title="Tag QR Akrilik" aria-label="Tag QR {{ $cat->name }}" onclick="openQrModal('{{ $cat->id }}', '{{ e($cat->name) }}', '{{ e($cat->unique_code) }}')">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="5" height="5" x="3" y="3" rx="1"/><rect width="5" height="5" x="16" y="3" rx="1"/><rect width="5" height="5" x="3" y="16" rx="1"/><path d="M21 16h-3a2 2 0 0 0-2 2v3"/><path d="M21 21v.01"/><path d="M12 7v3a2 2 0 0 1-2 2H7"/><path d="M3 12h.01"/><path d="M12 3h.01"/><path d="M12 16v.01"/><path d="M16 12h1"/><path d="M21 12v.01"/><path d="M12 21v-1"/></svg>
                                                        </button>
                                                    @endif
                                                    <form method="POST" action="/admin/cats/{{ $cat->id }}/delete" onsubmit="return confirm('Hapus {{ e($cat->name) }} dari katalog aktif? Riwayat tetap tersimpan.');" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="icon-btn icon-btn-delete" title="Hapus dari katalog aktif" aria-label="Hapus {{ $cat->name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
                                                        </button>
                                                    </form>
                                                @else
                                                    <form method="POST" action="/admin/cats/{{ $cat->id }}/restore" style="display:inline;">
                                                        @csrf
                                                        <button type="submit" class="icon-btn icon-btn-restore" title="Pulihkan kucing" aria-label="Pulihkan {{ $cat->name }}">
                                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>
                                                        </button>
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

                @if($cats->total() > 0)
                <div class="pagination-bar">
                    <div class="pagination-info">
                        Menampilkan <strong>{{ $cats->firstItem() }}&ndash;{{ $cats->lastItem() }}</strong> dari <strong>{{ $cats->total() }}</strong> kucing (10 per halaman)
                    </div>
                    @if($cats->hasPages())
                    <div class="pagination-links">
                        <a href="{{ $cats->previousPageUrl() ?? '#' }}" class="page-btn {{ $cats->onFirstPage() ? 'disabled' : '' }}">&larr; Sebelumnya</a>
                        @for($page = 1; $page <= $cats->lastPage(); $page++)
                            <a href="{{ $cats->url($page) }}" class="page-btn {{ $cats->currentPage() === $page ? 'active' : '' }}">{{ $page }}</a>
                        @endfor
                        <a href="{{ $cats->nextPageUrl() ?? '#' }}" class="page-btn {{ !$cats->hasMorePages() ? 'disabled' : '' }}">Berikutnya &rarr;</a>
                    </div>
                    @endif
                </div>
                @endif
            </div>
        </main>
    </div>

    <!-- HIDDEN QR SVG TEMPLATES PER CAT ON CURRENT PAGE -->
    @foreach($qrPreviewMap as $catId => $qrData)
        <template id="qr-front-{{ $catId }}">{!! $qrData['front_svg'] !!}</template>
        <div id="qr-meta-{{ $catId }}" data-payload="{{ $qrData['payload'] }}" data-issued="{{ $qrData['issued_at'] }}" style="display:none;"></div>
    @endforeach
    <template id="qr-back-shared">{!! $backAcrylicSvg !!}</template>

    <!-- MODAL DETAIL LENGKAP KUCING -->
    <div class="modal-backdrop" id="catDetailModal" role="dialog" aria-modal="true" aria-labelledby="detailCatTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h2 class="modal-title" id="detailCatTitle">Detail Lengkap Kucing</h2>
                <button type="button" class="alert-close" onclick="closeModal('catDetailModal')" aria-label="Tutup">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-hero">
                    <img id="d_photo" class="detail-photo" alt="Foto kucing">
                    <span id="d_avatar" class="detail-avatar">M</span>
                    <div style="flex:1; min-width:200px;">
                        <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
                            <h3 id="d_name" style="font-size:20px; font-weight:800; color:var(--text-primary);"></h3>
                            <span id="d_health_pill" class="badge badge-success"></span>
                            <span id="d_adoption_pill" class="badge badge-neutral"></span>
                            <span id="d_presence_pill" class="badge badge-neutral"></span>
                        </div>
                        <div class="detail-id-row">
                            <span class="id-pill" id="d_ulid">ID: —</span>
                            <span class="id-pill" id="d_code">Kode Kalung: —</span>
                            <button type="button" class="btn btn-neutral btn-sm" onclick="copyDetailId()" id="d_copy_btn">Salin ID</button>
                        </div>
                    </div>
                </div>

                <div class="detail-bento-grid">
                    <div class="detail-card">
                        <div class="detail-card-title">Identitas &amp; Tampilan</div>
                        <div class="kv-list">
                            <div class="kv-row"><span class="kv-key">Jenis Kelamin</span><span class="kv-val" id="d_gender">—</span></div>
                            <div class="kv-row"><span class="kv-key">Kelompok Umur</span><span class="kv-val" id="d_age">—</span></div>
                            <div class="kv-row"><span class="kv-key">Warna Utama</span><span class="kv-val" id="d_primary_color">—</span></div>
                            <div class="kv-row"><span class="kv-key">Pola Bulu</span><span class="kv-val" id="d_fur_pattern">—</span></div>
                            <div class="kv-row"><span class="kv-key">Zona Kampus</span><span class="kv-val" id="d_territory">—</span></div>
                            <div class="kv-row"><span class="kv-key">Rarity (Game)</span><span class="kv-val" id="d_rarity">—</span></div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-card-title">Kesehatan, Steril &amp; Vaksin</div>
                        <div class="kv-list">
                            <div class="kv-row"><span class="kv-key">Kondisi</span><span class="kv-val" id="d_health">—</span></div>
                            <div class="kv-row"><span class="kv-key">Sterilisasi</span><span class="kv-val" id="d_steril">—</span></div>
                            <div class="kv-row"><span class="kv-key">Vaksinasi</span><span class="kv-val" id="d_vaksin">—</span></div>
                            <div class="kv-row"><span class="kv-key">Tgl Vaksin</span><span class="kv-val" id="d_vaksin_date">—</span></div>
                            <div class="kv-row"><span class="kv-key">Catatan Vaksin</span><span class="kv-val" id="d_vaksin_notes">—</span></div>
                            <div class="kv-row"><span class="kv-key">Kebutuhan Khusus</span><span class="kv-val" id="d_special_needs">—</span></div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-card-title">Ciri Fisik Terstruktur</div>
                        <div class="kv-list">
                            <div class="kv-row"><span class="kv-key">Tanda Fisik</span><span class="kv-val" id="d_trait_marks">—</span></div>
                            <div class="kv-row"><span class="kv-key">Telinga / Ear-tip</span><span class="kv-val" id="d_trait_ear">—</span></div>
                            <div class="kv-row"><span class="kv-key">Bentuk Ekor</span><span class="kv-val" id="d_trait_tail">—</span></div>
                            <div class="kv-row"><span class="kv-key">Warna Mata</span><span class="kv-val" id="d_trait_eye">—</span></div>
                        </div>
                    </div>

                    <div class="detail-card">
                        <div class="detail-card-title">Sifat &amp; Deskripsi</div>
                        <div class="kv-list">
                            <div class="kv-row"><span class="kv-key">Sifat</span><span class="kv-val" id="d_temperament">—</span></div>
                        </div>
                        <p id="d_description" style="margin-top:10px; font-size:13px; color:var(--text-secondary); line-height:1.6;">—</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-neutral" onclick="closeModal('catDetailModal')">Tutup</button>
                <button type="button" class="btn btn-neutral" id="d_qr_btn" onclick="openQrFromDetail()">Tag QR Akrilik</button>
                <button type="button" class="btn btn-primary" id="d_edit_btn" onclick="openEditFromDetail()">Edit Profil</button>
            </div>
        </div>
    </div>

    <!-- MODAL TAMBAH / EDIT KUCING (PROPERLY SCROLLABLE!) -->
    <div class="modal-backdrop" id="catFormModal" role="dialog" aria-modal="true" aria-labelledby="catFormTitle">
        <div class="modal-box">
            <div class="modal-header">
                <h2 class="modal-title" id="catFormTitle">Tambah Kucing Baru</h2>
                <button type="button" class="alert-close" onclick="closeModal('catFormModal')" aria-label="Tutup">&times;</button>
            </div>
            <form method="POST" id="catFormEl" class="modal-form" action="/admin/cats" enctype="multipart/form-data">
                @csrf
                <div class="modal-body" id="catFormBody">
                    <div class="form-section">
                        <div class="form-section-title">1. Identitas &amp; Tampilan Fisik</div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="f_name">Nama Kucing *</label>
                                <input id="f_name" name="name" type="text" class="input" required maxlength="100" placeholder="Contoh: Mochi">
                            </div>
                            <div class="field">
                                <label for="f_unique_code">Kode Kalung Cadangan</label>
                                <input id="f_unique_code" name="unique_code" type="text" class="input" maxlength="32" placeholder="Otomatis jika dikosongkan">
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
                                <input id="f_primary_color" name="primary_color" type="text" class="input" maxlength="80" placeholder="Oranye Putih / Hitam">
                            </div>
                            <div class="field">
                                <label for="f_fur_pattern">Pola Bulu</label>
                                <input id="f_fur_pattern" name="fur_pattern" type="text" class="input" maxlength="80" placeholder="Tabby / Calico / Polos">
                            </div>
                            <div class="field span-2">
                                <label for="f_territory_id">Zona / Lokasi Kampus</label>
                                <select id="f_territory_id" name="territory_id" class="select">
                                    <option value="">Belum ditentukan</option>
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
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                                <input id="f_vaccination_notes" name="vaccination_notes" type="text" class="input" placeholder="F3 & Rabies lengkap">
                            </div>
                            <div class="field span-2">
                                <label for="f_special_needs">Kebutuhan Khusus</label>
                                <input id="f_special_needs" name="special_needs" type="text" class="input" placeholder="Pakan rendah garam / sensitif ikan laut">
                            </div>
                        </div>
                    </div>

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
                                <input id="f_temperament" name="temperament" type="text" class="input" placeholder="Ramah, tenang, suka dielus">
                            </div>
                            <div class="field">
                                <label for="f_status_reason">Alasan Perubahan Status</label>
                                <input id="f_status_reason" name="status_reason" type="text" class="input" placeholder="Opsional, tercatat di log status">
                            </div>
                            <div class="field span-2">
                                <label for="f_description">Deskripsi Singkat</label>
                                <textarea id="f_description" name="description" class="textarea" placeholder="Ringkasan kebiasaan atau lokasi favorit kucing."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="form-section-title">4. Ciri Fisik Terstruktur</div>
                        <div class="form-grid">
                            <div class="field">
                                <label for="f_traits_marks">Tanda Fisik Khas</label>
                                <input id="f_traits_marks" name="traits_physical_marks" type="text" class="input" placeholder="Bercak putih di hidung">
                            </div>
                            <div class="field">
                                <label for="f_traits_ear">Telinga / Ear-tip</label>
                                <input id="f_traits_ear" name="traits_ear_tip" type="text" class="input" placeholder="Ear-tip kiri / Utuh">
                            </div>
                            <div class="field">
                                <label for="f_traits_tail">Bentuk Ekor</label>
                                <input id="f_traits_tail" name="traits_tail_type" type="text" class="input" placeholder="Panjang / Bengkok / Pendek">
                            </div>
                            <div class="field">
                                <label for="f_traits_eye">Warna Mata</label>
                                <input id="f_traits_eye" name="traits_eye_color" type="text" class="input" placeholder="Kuning amber / Hijau">
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
                        <span class="acrylic-caption">Sisi Depan &mdash; Nama &amp; QR Kalung</span>
                    </div>
                    <div class="acrylic-card">
                        <div class="acrylic-svg-frame" id="qrModalBackSlot"></div>
                        <span class="acrylic-caption">Sisi Belakang &mdash; Branding Meowtopia</span>
                    </div>
                </div>

                <div class="qr-meta-bar">
                    <div>
                        <div style="color:var(--text-muted); font-size:11px; font-weight:700; text-transform:uppercase;">Payload QR Resmi</div>
                        <div style="font-family:ui-monospace,Consolas,monospace; font-weight:600; margin-top:2px;" id="qrModalPayload"></div>
                    </div>
                    <div>
                        <div style="color:var(--text-muted); font-size:11px; font-weight:700; text-transform:uppercase;">Diterbitkan</div>
                        <div style="font-weight:500; margin-top:2px;" id="qrModalIssued"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;">
                <div>
                    @if($canRegenerateQr)
                    <form method="POST" id="qrRegenerateForm" action="" onsubmit="return confirm('Cabut QR lama dan terbitkan token QR baru? Tag lama tidak akan bisa dipindai lagi.');" style="display:inline;">
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
                    <a id="qrOpenSheet" href="#" target="_blank" class="btn btn-primary btn-sm">Cetak Tag Akrilik</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentDetailCat = null;

        (function initSidebar() {
            const collapsed = localStorage.getItem('meow_admin_sidebar_collapsed') === '1';
            if (collapsed && window.innerWidth > 860) {
                document.body.classList.add('sidebar-collapsed');
                const btn = document.getElementById('sidebarToggleBtn');
                if (btn) btn.textContent = '>';
            }
        })();

        function toggleSidebar() {
            const sidebar = document.getElementById('appSidebar');
            const btn = document.getElementById('sidebarToggleBtn');
            if (window.innerWidth <= 860) {
                sidebar.classList.toggle('mobile-open');
                return;
            }
            const isCollapsed = document.body.classList.toggle('sidebar-collapsed');
            localStorage.setItem('meow_admin_sidebar_collapsed', isCollapsed ? '1' : '0');
            btn.textContent = isCollapsed ? '>' : '<';
        }

        /* ================= CUSTOM DROPDOWNS & LIVE AUTO-SEARCH ================= */
        function closeAllDropdowns() {
            document.querySelectorAll('.custom-dd.open').forEach(function (dd) {
                dd.classList.remove('open');
                const btn = dd.querySelector('.custom-dd-trigger');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            });
        }

        document.querySelectorAll('.custom-dd').forEach(function (dd) {
            const trigger = dd.querySelector('.custom-dd-trigger');
            const hiddenInput = dd.querySelector('input[type="hidden"]');

            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                const willOpen = !dd.classList.contains('open');
                closeAllDropdowns();
                if (willOpen) {
                    dd.classList.add('open');
                    trigger.setAttribute('aria-expanded', 'true');
                }
            });

            dd.querySelectorAll('.custom-dd-option').forEach(function (opt) {
                opt.addEventListener('click', function () {
                    hiddenInput.value = opt.dataset.value || '';
                    closeAllDropdowns();
                    document.getElementById('catalogFilterForm').submit();
                });
            });
        });

        document.addEventListener('click', closeAllDropdowns);

        // Live debounced search without needing a Cari button
        (function initLiveSearch() {
            const searchInput = document.getElementById('searchInput');
            if (!searchInput) return;
            let debounceTimer = null;
            searchInput.addEventListener('input', function () {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function () {
                    document.getElementById('catalogFilterForm').submit();
                }, 420);
            });
        })();

        /* ================= MODALS ================= */
        function openModal(id) {
            const el = document.getElementById(id);
            el.classList.add('open');
            document.body.style.overflow = 'hidden';
            const body = el.querySelector('.modal-body');
            if (body) body.scrollTop = 0;
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
            if (!document.querySelector('.modal-backdrop.open')) {
                document.body.style.overflow = '';
            }
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                closeAllDropdowns();
                closeModal('catDetailModal');
                closeModal('catFormModal');
                closeModal('qrPreviewModal');
            }
        });

        document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
            backdrop.addEventListener('click', function (e) {
                if (e.target === backdrop) closeModal(backdrop.id);
            });
        });

        function openDetailModal(cat) {
            currentDetailCat = cat;
            document.getElementById('detailCatTitle').textContent = 'Detail Kucing — ' + cat.name;
            document.getElementById('d_name').textContent = cat.name;
            document.getElementById('d_ulid').textContent = 'ID: ' + cat.id;
            document.getElementById('d_code').textContent = 'Kode Kalung: ' + cat.unique_code;
            document.getElementById('d_copy_btn').textContent = 'Salin ID';

            const photoEl = document.getElementById('d_photo');
            const avatarEl = document.getElementById('d_avatar');
            if (cat.photo_url) {
                photoEl.src = cat.photo_url;
                photoEl.style.display = 'block';
                avatarEl.style.display = 'none';
            } else {
                photoEl.style.display = 'none';
                avatarEl.textContent = (cat.name || 'K').substring(0, 1).toUpperCase();
                avatarEl.style.display = 'inline-flex';
            }

            const hp = document.getElementById('d_health_pill');
            hp.textContent = cat.health_label;
            hp.className = 'badge ' + (cat.health_status === 'HEALTHY' ? 'badge-success' : (cat.health_status === 'UNDER_CARE' ? 'badge-warning' : 'badge-danger'));

            const ap = document.getElementById('d_adoption_pill');
            ap.textContent = cat.adoption_label;
            ap.className = 'badge ' + (cat.adoption_status === 'READY' ? 'badge-success' : (cat.adoption_status === 'ADOPTED' ? 'badge-info' : 'badge-neutral'));

            document.getElementById('d_presence_pill').textContent = cat.presence_label;

            document.getElementById('d_gender').textContent = cat.gender_label || '—';
            document.getElementById('d_age').textContent = cat.age_label || '—';
            document.getElementById('d_primary_color').textContent = cat.primary_color || cat.color_pattern || '—';
            document.getElementById('d_fur_pattern').textContent = cat.fur_pattern || '—';
            document.getElementById('d_territory').textContent = cat.territory_name || '—';
            document.getElementById('d_rarity').textContent = cat.game_rarity || '—';

            document.getElementById('d_health').textContent = cat.health_label || '—';
            document.getElementById('d_steril').textContent = cat.is_sterilized === '1' ? 'Sudah Steril' : (cat.is_sterilized === '0' ? 'Belum Steril' : 'Belum diketahui');
            document.getElementById('d_vaksin').textContent = cat.is_vaccinated === '1' ? 'Sudah Vaksin' : (cat.is_vaccinated === '0' ? 'Belum Vaksin' : 'Belum diketahui');
            document.getElementById('d_vaksin_date').textContent = cat.vaccinated_at || '—';
            document.getElementById('d_vaksin_notes').textContent = cat.vaccination_notes || '—';
            document.getElementById('d_special_needs').textContent = cat.special_needs || '—';

            const traits = cat.traits || {};
            document.getElementById('d_trait_marks').textContent = traits.physical_marks || '—';
            document.getElementById('d_trait_ear').textContent = traits.ear_tip || '—';
            document.getElementById('d_trait_tail').textContent = traits.tail_type || '—';
            document.getElementById('d_trait_eye').textContent = traits.eye_color || '—';

            document.getElementById('d_temperament').textContent = cat.temperament || '—';
            document.getElementById('d_description').textContent = cat.description || 'Belum ada deskripsi tambahan.';

            document.getElementById('d_qr_btn').style.display = cat.has_qr && !cat.trashed ? 'inline-flex' : 'none';
            document.getElementById('d_edit_btn').style.display = !cat.trashed ? 'inline-flex' : 'none';

            openModal('catDetailModal');
        }

        function copyDetailId() {
            if (!currentDetailCat) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(currentDetailCat.id).then(function () {
                    document.getElementById('d_copy_btn').textContent = 'Tersalin';
                });
            }
        }

        function openEditFromDetail() {
            if (!currentDetailCat) return;
            closeModal('catDetailModal');
            openEditModal(currentDetailCat);
        }

        function openQrFromDetail() {
            if (!currentDetailCat) return;
            closeModal('catDetailModal');
            openQrModal(currentDetailCat.id, currentDetailCat.name, currentDetailCat.unique_code);
        }

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
            document.getElementById('catFormTitle').textContent = 'Edit Kucing — ' + cat.name;
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
