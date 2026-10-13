<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $reportTitle ?? 'Laporan Resmi VexaHost' }}</title>
    <style>
        @page {
            margin: 28px 30px 36px 30px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1e293b;
            font-size: 9.5px;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }

        /* Enterprise Header */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2.5px solid #0f172a;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-logo {
            height: 42px;
            width: auto;
            max-width: 140px;
        }
        .brand-title {
            font-size: 14px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
            margin: 0;
            text-transform: uppercase;
        }
        .brand-subtitle {
            font-size: 8px;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            margin-top: 2px;
        }
        .brand-address {
            font-size: 7px;
            color: #64748b;
            margin-top: 2px;
        }
        .report-badge {
            display: inline-block;
            background-color: #0f172a;
            color: #ffffff;
            font-size: 7.5px;
            font-weight: 700;
            padding: 2.5px 8px;
            border-radius: 3px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .meta-box {
            text-align: right;
            font-size: 8px;
            color: #475569;
            line-height: 1.4;
            margin-top: 4px;
        }
        .meta-box strong {
            color: #0f172a;
        }

        /* Document Title Section */
        .doc-title-bar {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #0f172a;
            padding: 8px 12px;
            margin-bottom: 14px;
            border-radius: 2px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: 800;
            color: #0f172a;
            margin: 0 0 2px 0;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }
        .doc-desc {
            font-size: 8px;
            color: #475569;
            margin: 0;
        }

        /* Enterprise KPI Cards Grid */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px;
            margin-bottom: 14px;
        }
        .kpi-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-top: 2.5px solid #0f172a;
            border-radius: 3px;
            padding: 7px 9px;
            vertical-align: top;
        }
        .kpi-label {
            font-size: 7.5px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 13.5px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .kpi-sub {
            font-size: 7px;
            color: #64748b;
            margin-top: 2px;
        }

        /* Section Headings */
        .section-header {
            font-size: 9.5px;
            font-weight: 800;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 14px;
            margin-bottom: 7px;
        }

        /* Standard Enterprise Data Tables */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-bottom: 12px;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 7.2px;
            letter-spacing: 0.4px;
            padding: 5.5px 7px;
            text-align: left;
            border: 1px solid #0f172a;
        }
        .data-table td {
            padding: 4.5px 7px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .data-table td.text-right, .data-table th.text-right {
            text-align: right;
        }
        .data-table td.text-center, .data-table th.text-center {
            text-align: center;
        }
        .font-mono {
            font-family: 'Courier New', Courier, monospace;
            font-size: 7.8px;
        }

        /* Status & Service Badges */
        .badge {
            display: inline-block;
            font-size: 6.8px;
            font-weight: 700;
            padding: 2px 5px;
            border-radius: 2px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-warning {
            background-color: #fef9c3;
            color: #854d0e;
            border: 1px solid #fef08a;
        }
        .badge-danger {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .badge-info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .badge-neutral {
            background-color: #f1f5f9;
            color: #334155;
            border: 1px solid #cbd5e1;
        }

        /* Sign-off & Verification */
        .sign-table {
            width: 100%;
            margin-top: 20px;
            border-collapse: collapse;
        }
        .sign-table td {
            width: 50%;
            vertical-align: top;
            font-size: 8px;
        }
        .sign-line {
            margin-top: 40px;
            border-bottom: 1.2px solid #0f172a;
            width: 180px;
        }
        .verification-box {
            background-color: #f8fafc;
            border: 1px dashed #94a3b8;
            padding: 6px 10px;
            font-size: 7px;
            color: #475569;
            margin-top: 14px;
            border-radius: 2px;
            line-height: 1.35;
        }
        .footer-disclaimer {
            font-size: 6.8px;
            color: #64748b;
            margin-top: 8px;
            font-style: italic;
        }
    </style>
</head>
<body>

    <!-- Enterprise Corporate Header with Logo -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        @if(!empty($logoBase64))
                            <td style="width: 75px; vertical-align: middle; padding-right: 10px;">
                                <img src="data:image/png;base64,{{ $logoBase64 }}" class="company-logo" alt="VexaHost Logo">
                            </td>
                        @elseif(file_exists(public_path('images/logo.png')))
                            <td style="width: 75px; vertical-align: middle; padding-right: 10px;">
                                <img src="{{ public_path('images/logo.png') }}" class="company-logo" alt="VexaHost Logo">
                            </td>
                        @endif
                        <td style="vertical-align: middle;">
                            <div class="brand-title">VEXAHOST INDONESIA</div>
                            <div class="brand-subtitle">Cloud Infrastructure, Autonomous AI & Database</div>
                            <div class="brand-address">{{ \App\Support\MailIdentity::contactEmail() }} • vexahostcloud.my.id</div>
                        </td>
                    </tr>
                </table>
            </td>
            <td style="width: 45%; text-align: right;">
                <span class="report-badge">CONFIDENTIAL &bull; DOKUMEN RESMI</span>
                <div class="meta-box">
                    <div>No. Dokumen: <strong class="font-mono">{{ $reportRef ?? 'VXH-RPT-'.date('Ymd-His') }}</strong></div>
                    <div>Periode: <strong>{{ $periodLabel ?? 'Semua Periode' }}</strong></div>
                    <div>Dicetak: <strong>{{ now()->timezone('Asia/Jakarta')->translatedFormat('d F Y, H:i') }} WIB</strong></div>
                    <div>Otorisasi: <strong>{{ $generatedBy ?? 'Administrator Sistem' }}</strong></div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Document Title Section -->
    <div class="doc-title-bar">
        <h1 class="doc-title">{{ $reportTitle ?? 'Laporan Operasional VexaHost' }}</h1>
        <p class="doc-desc">{{ $subTitle ?? 'Konsolidasi Finansial, Infrastruktur Cloud VPS, AI Agent Workstation, dan Database' }}</p>
    </div>

    <!-- Main Content Injection -->
    @yield('pdf_content')

    <!-- Sign-off & Verification Section -->
    <table class="sign-table">
        <tr>
            <td>
                <div style="font-weight: 700; color: #334155; font-size: 7.8px; text-transform: uppercase;">Disiapkan & Diverifikasi Oleh:</div>
                <div class="sign-line"></div>
                <div style="font-weight: 800; font-size: 8.5px; color: #0f172a; margin-top: 3px;">{{ $generatedBy ?? 'Administrator Sistem' }}</div>
                <div style="color: #64748b; font-size: 7px;">Internal Audit & Infrastructure Ops • VexaHost</div>
            </td>
            <td>
                <div style="font-weight: 700; color: #334155; font-size: 7.8px; text-transform: uppercase;">Disetujui & Diotorisasi Oleh:</div>
                <div class="sign-line"></div>
                <div style="font-weight: 800; font-size: 8.5px; color: #0f172a; margin-top: 3px;">Direksi & Operations Lead</div>
                <div style="color: #64748b; font-size: 7px;">PT DESTINARA CHAKRAWALA ARTHA</div>
            </td>
        </tr>
    </table>

    <!-- Digital Security Verification Stamp -->
    <div class="verification-box">
        <strong>VERIFIKASI INTEGRITAS DIGITAL:</strong> Dokumen ini diproduksi secara terotentikasi melalui VexaHost Enterprise Reporting Engine.
        Kode Verifikasi: <span class="font-mono">VXH-AUTH-{{ strtoupper(substr(md5($reportRef ?? 'VXH'), 0, 16)) }}</span>. Valid untuk keperluan audit internal, pelaporan pajak, dan rekonsiliasi data operasional.
    </div>
    <div class="footer-disclaimer">
        * Dokumen ini bersifat rahasia perusahaan (Strictly Confidential). Dilarang menyalin, menyebarluaskan, atau memodifikasi isi laporan tanpa persetujuan tertulis manajemen VexaHost.
    </div>

    <!-- Dompdf Dynamic Page Numbering Script -->
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->getFont("Helvetica", "normal");
            $size = 7;
            $text = "Halaman {PAGE_NUM} dari {PAGE_COUNT}  •  VexaHost Enterprise Platform  •  Dokumen Resmi Terverifikasi";
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 20;
            $pdf->page_text($x, $y, $text, $font, $size, array(0.4, 0.4, 0.4));
        }
    </script>
</body>
</html>
