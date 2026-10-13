<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\SupportTicket;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\JcTable;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Parse days filter and retrieve start date.
     */
    public function parseDateFilter(mixed $days): array
    {
        if ($days === 'all' || !$days) {
            return [null, 'Semua Periode', 'all'];
        }

        $daysInt = in_array((int) $days, [30, 90, 365], true) ? (int) $days : 30;
        $from = now()->subDays($daysInt)->startOfDay();
        $label = match ($daysInt) {
            365 => '1 Tahun Terakhir',
            90 => '90 Hari Terakhir',
            default => '30 Hari Terakhir',
        };

        return [$from, $label, (string) $daysInt];
    }

    /**
     * Aggregate report dataset.
     */
    public function getReportData(string $type, mixed $days): array
    {
        // Normalize alias types
        $type = match ($type) {
            'ai' => 'ai_combo',
            'managed_db' => 'database',
            default => $type,
        };

        [$from, $periodLabel, $periodKey] = $this->parseDateFilter($days);

        $invoicesQuery = Invoice::with(['order.customer', 'order.vpsSpec'])->latest('created_at');
        $ordersQuery = Order::with(['customer', 'vpsSpec'])->latest('created_at');
        $ticketsQuery = SupportTicket::with(['customer', 'assignee'])->latest('created_at');

        if ($from) {
            $invoicesQuery->where('created_at', '>=', $from);
            $ordersQuery->where('created_at', '>=', $from);
            $ticketsQuery->where('created_at', '>=', $from);
        }

        $invoices = $invoicesQuery->get();
        $orders = $ordersQuery->get();
        $tickets = $ticketsQuery->get();

        // Categorize orders: VPS, AI Agent, Managed Database
        $vpsOrders = $orders->filter(fn ($o) => !$o->isAiPackage() && !$o->isDatabasePackage());
        $aiOrders = $orders->filter(fn ($o) => $o->isAiPackage());
        $dbOrders = $orders->filter(fn ($o) => $o->isDatabasePackage());

        // Financial aggregations
        $revenue = (float) $invoices->where('status', 'paid')->sum('amount');
        $paidInvoices = $invoices->where('status', 'paid')->count();

        $vpsRevenue = (float) $invoices->where('status', 'paid')
            ->filter(fn ($i) => $i->order && !$i->order->isAiPackage() && !$i->order->isDatabasePackage())
            ->sum('amount');

        $aiRevenue = (float) $invoices->where('status', 'paid')
            ->filter(fn ($i) => $i->order && $i->order->isAiPackage())
            ->sum('amount');

        $dbRevenue = (float) $invoices->where('status', 'paid')
            ->filter(fn ($i) => $i->order && $i->order->isDatabasePackage())
            ->sum('amount');

        $avgFirstResponse = $tickets->filter(fn ($t) => $t->first_response_at)
            ->map(fn ($t) => $t->created_at->diffInMinutes($t->first_response_at))
            ->avg();

        $dailyRevenue = $invoices->where('status', 'paid')
            ->groupBy(fn ($inv) => $inv->created_at->format('Y-m-d'))
            ->map(fn ($rows) => (float) $rows->sum('amount'))
            ->sortKeys();

        $orderStatuses = $orders->groupBy('status')->map->count();
        $ticketStatuses = $tickets->groupBy('status')->map->count();

        // Filtered order set based on report type
        $filteredOrders = match ($type) {
            'vps' => $vpsOrders,
            'ai_combo' => $aiOrders,
            'database' => $dbOrders,
            default => $orders,
        };

        // Prepare Base64 Logo for PDF rendering (rock-solid in DomPDF)
        $logoPath = public_path('images/logo.png');
        $logoBase64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

        $meta = $this->getMeta($type, $periodLabel);

        return array_merge([
            'type' => $type,
            'days' => $periodKey,
            'periodLabel' => $periodLabel,
            'reportRef' => 'VXH-RPT-' . now()->format('Ymd-His'),
            'generatedBy' => auth()->user()?->full_name ?? auth()->user()?->username ?? 'Administrator Sistem',
            'logoBase64' => $logoBase64,
            'logoPath' => $logoPath,
            'invoices' => $invoices,
            'orders' => $orders,
            'filteredOrders' => $filteredOrders,
            'vpsOrders' => $vpsOrders,
            'aiOrders' => $aiOrders,
            'dbOrders' => $dbOrders,
            'tickets' => $tickets,
            'revenue' => $revenue,
            'paidInvoices' => $paidInvoices,
            'vpsRevenue' => $vpsRevenue,
            'aiRevenue' => $aiRevenue,
            'dbRevenue' => $dbRevenue,
            'avgFirstResponse' => $avgFirstResponse,
            'dailyRevenue' => $dailyRevenue,
            'orderStatuses' => $orderStatuses,
            'ticketStatuses' => $ticketStatuses,
        ], $meta);
    }

    /**
     * Get title, template name, and filename prefix.
     */
    public function getMeta(string $type, string $periodLabel): array
    {
        $type = match ($type) {
            'ai' => 'ai_combo',
            'managed_db' => 'database',
            default => $type,
        };

        return match ($type) {
            'vps' => [
                'reportTitle' => 'Laporan Khusus Infrastruktur Cloud VPS',
                'reportCategory' => 'Cloud VPS Infrastructure & Compute Services',
                'viewName' => 'admin.reports.pdf.vps',
                'filenamePrefix' => 'VexaHost-Laporan-VPS',
                'subTitle' => 'Rincian Alokasi Komputasi vCPU, RAM, Disk NVMe, Sistem Operasi & Lokasi Datacenter Cloud VPS',
            ],
            'ai_combo' => [
                'reportTitle' => 'Laporan Khusus AI Agent & Autonomous Workstation',
                'reportCategory' => 'Artificial Intelligence & Autonomous Agent Platforms',
                'viewName' => 'admin.reports.pdf.ai',
                'filenamePrefix' => 'VexaHost-Laporan-AIAgent',
                'subTitle' => 'Rincian Deployment AI Coding CLI (Claude/OpenCode), LLM Engine (Ollama), & Private RAG Studio',
            ],
            'database' => [
                'reportTitle' => 'Laporan Khusus Managed Database Server',
                'reportCategory' => 'Dedicated & Clustered Managed Database Solutions',
                'viewName' => 'admin.reports.pdf.database',
                'filenamePrefix' => 'VexaHost-Laporan-Database',
                'subTitle' => 'Rincian Database Terisolasi (PostgreSQL, MySQL, Redis, MongoDB, Qdrant) & Manajemen Koneksi',
            ],
            'orders' => [
                'reportTitle' => 'Laporan Konsolidasi Seluruh Pesanan Layanan',
                'reportCategory' => 'Enterprise Service Provisioning & Order Operations',
                'viewName' => 'admin.reports.pdf.orders',
                'filenamePrefix' => 'VexaHost-Laporan-Orders',
                'subTitle' => 'Rekapitulasi Lengkap Seluruh Pesanan Cloud Hosting, VPS, AI Agent Workstation, dan Database',
            ],
            'invoices' => [
                'reportTitle' => 'Laporan Faktur Penagihan & Arus Kas Pendapatan',
                'reportCategory' => 'Financial Invoicing & Revenue Reconciliation',
                'viewName' => 'admin.reports.pdf.invoices',
                'filenamePrefix' => 'VexaHost-Laporan-Invoices',
                'subTitle' => 'Daftar Faktur Resmi, Pelunasan Transaksi, Status Jatuh Tempo, dan Arus Kas Masuk',
            ],
            'tickets' => [
                'reportTitle' => 'Laporan Layanan & Performa Tiket Dukungan (SLA)',
                'reportCategory' => 'Customer Support Operations & SLA Performance',
                'viewName' => 'admin.reports.pdf.tickets',
                'filenamePrefix' => 'VexaHost-Laporan-TiketSLA',
                'subTitle' => 'Evaluasi Kecepatan Respon, Rasio Resolusi Masalah, dan Metrik Kepuasan Pelanggan',
            ],
            default => [
                'reportTitle' => 'Laporan Eksekutif & Ringkasan Portofolio Bisnis',
                'reportCategory' => 'Executive Corporate & Multi-Service Portfolio Summary',
                'viewName' => 'admin.reports.pdf.executive',
                'filenamePrefix' => 'VexaHost-Laporan-Eksekutif',
                'subTitle' => 'Konsolidasi Finansial, Performa Penjualan Multi-Layanan, Arus Kas Harian, dan Metrik Operasional',
            ],
        };
    }

    /**
     * Generate PDF stream (inline for modal popup or attachment for download).
     */
    public function generatePdf(string $type, mixed $days, bool $inline = false): Response
    {
        $data = $this->getReportData($type, $days);
        $filename = sprintf('%s-%s-%s.pdf', $data['filenamePrefix'], Str::slug($data['periodLabel']), now()->format('Ymd-His'));

        $pdf = Pdf::loadView($data['viewName'], $data)
            ->setPaper('a4', 'portrait')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'Helvetica',
            ]);

        $content = $pdf->output();
        $disposition = $inline ? 'inline' : 'attachment';

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "{$disposition}; filename=\"{$filename}\"",
            'X-Report-Title' => $data['reportTitle'],
            'X-Report-Period' => $data['periodLabel'],
        ]);
    }

    /**
     * Generate Word document (.docx) with enterprise styling & company logo.
     */
    public function generateDocx(string $type, mixed $days): BinaryFileResponse
    {
        $data = $this->getReportData($type, $days);
        $filename = sprintf('%s-%s-%s.docx', $data['filenamePrefix'], Str::slug($data['periodLabel']), now()->format('Ymd-His'));

        $phpWord = new PhpWord();
        $phpWord->getDocInfo()->setCreator('VexaHost Platform');
        $phpWord->getDocInfo()->setCompany('PT DESTINARA CHAKRAWALA ARTHA');
        $phpWord->getDocInfo()->setTitle($data['reportTitle']);
        $phpWord->getDocInfo()->setDescription('Official VexaHost Enterprise Operations & Business Report');

        $section = $phpWord->addSection([
            'marginTop' => 720,
            'marginBottom' => 720,
            'marginLeft' => 900,
            'marginRight' => 900,
        ]);

        // 1. Header with Logo & Corporate Identity Table
        $headerTableStyle = [
            'borderSize' => 0,
            'borderColor' => 'FFFFFF',
            'cellMargin' => 40,
            'alignment' => JcTable::CENTER,
        ];
        $phpWord->addTableStyle('HeaderTable', $headerTableStyle);
        $headerTable = $section->addTable('HeaderTable');
        $headerTable->addRow();

        $cLogo = $headerTable->addCell(3000);
        if (!empty($data['logoPath']) && file_exists($data['logoPath'])) {
            $cLogo->addImage($data['logoPath'], [
                'width' => 115,
                'height' => 63,
                'alignment' => Jc::START,
            ]);
        } else {
            $cLogo->addText('VEXAHOST', ['name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => '0F172A']);
        }

        $cBrand = $headerTable->addCell(6500);
        $cBrand->addText('VEXAHOST INDONESIA', ['name' => 'Arial', 'size' => 13, 'bold' => true, 'color' => '0F172A']);
        $cBrand->addText('Cloud Infrastructure, Autonomous AI & Managed Database Platform', ['name' => 'Arial', 'size' => 8, 'bold' => true, 'color' => '475569']);
        $cBrand->addText(\App\Support\MailIdentity::contactEmail() . ' • vexahostcloud.my.id', ['name' => 'Arial', 'size' => 7.5, 'color' => '64748B']);

        $section->addTextBreak(1);

        // 2. Document Meta Bar Table
        $metaTableStyle = [
            'borderSize' => 6,
            'borderColor' => '0F172A',
            'cellMargin' => 80,
            'alignment' => JcTable::CENTER,
        ];
        $phpWord->addTableStyle('MetaTable', $metaTableStyle);
        $metaTable = $section->addTable('MetaTable');
        $metaTable->addRow();

        $c1 = $metaTable->addCell(5200, ['bgColor' => '0F172A']);
        $c1->addText(strtoupper($data['reportTitle']), ['bold' => true, 'size' => 10, 'color' => 'FFFFFF']);
        $c1->addText($data['subTitle'] ?? 'Official VexaHost Business Report', ['size' => 7.5, 'color' => 'CBD5E1']);

        $c2 = $metaTable->addCell(4300, ['bgColor' => 'F8FAFC']);
        $c2->addText("Nomor Ref: {$data['reportRef']}", ['bold' => true, 'size' => 8, 'color' => '0F172A']);
        $c2->addText("Periode: {$data['periodLabel']}", ['size' => 8, 'color' => '334155']);
        $c2->addText("Dicetak: " . now()->timezone('Asia/Jakarta')->format('d F Y, H:i') . " WIB oleh {$data['generatedBy']}", ['size' => 7.5, 'color' => '64748B']);

        $section->addTextBreak(1);

        // 3. KPI Summary Block
        $section->addText('RINGKASAN METRIK KUNCI (KEY PERFORMANCE INDICATORS)', [
            'name' => 'Arial',
            'size' => 10,
            'bold' => true,
            'color' => '0F172A',
        ]);

        $kpiTableStyle = [
            'borderSize' => 6,
            'borderColor' => 'E2E8F0',
            'cellMargin' => 100,
            'alignment' => JcTable::CENTER,
        ];
        $phpWord->addTableStyle('KpiTable', $kpiTableStyle);
        $kpiTable = $section->addTable('KpiTable');
        $kpiTable->addRow();

        $kpi1 = $kpiTable->addCell(2375, ['bgColor' => 'F8FAFC']);
        $kpi1->addText('TOTAL OMZET LUNAS', ['size' => 7.5, 'bold' => true, 'color' => '64748B']);
        $kpi1->addText('Rp ' . number_format($data['revenue'], 0, ',', '.'), ['size' => 11, 'bold' => true, 'color' => '0F172A']);
        $kpi1->addText("{$data['paidInvoices']} invoice paid", ['size' => 7, 'color' => '64748B']);

        $kpi2 = $kpiTable->addCell(2375, ['bgColor' => 'F8FAFC']);
        $kpi2->addText('TOTAL PESANAN', ['size' => 7.5, 'bold' => true, 'color' => '64748B']);
        $kpi2->addText((string) $data['orders']->count(), ['size' => 11, 'bold' => true, 'color' => '0F172A']);
        $kpi2->addText("{$data['orders']->where('status', 'active')->count()} aktif beroperasi", ['size' => 7, 'color' => '64748B']);

        $kpi3 = $kpiTable->addCell(2375, ['bgColor' => 'F8FAFC']);
        $kpi3->addText('DISTRIBUSI PORTOFOLIO', ['size' => 7.5, 'bold' => true, 'color' => '64748B']);
        $kpi3->addText("VPS: {$data['vpsOrders']->count()} | AI: {$data['aiOrders']->count()} | DB: {$data['dbOrders']->count()}", ['size' => 8.5, 'bold' => true, 'color' => '0F172A']);
        $kpi3->addText('3 pilar utama layanan', ['size' => 7, 'color' => '64748B']);

        $kpi4 = $kpiTable->addCell(2375, ['bgColor' => 'F8FAFC']);
        $kpi4->addText('SLA TIKET SUPPORT', ['size' => 7.5, 'bold' => true, 'color' => '64748B']);
        $kpi4->addText($data['avgFirstResponse'] ? round($data['avgFirstResponse'] / 60, 1) . ' Jam' : '0 Jam', ['size' => 11, 'bold' => true, 'color' => '0F172A']);
        $kpi4->addText("{$data['tickets']->count()} total tiket masuk", ['size' => 7, 'color' => '64748B']);

        $section->addTextBreak(1);

        // 4. Data Tables based on type
        $dataTableStyle = [
            'borderSize' => 6,
            'borderColor' => 'E2E8F0',
            'cellMargin' => 60,
            'alignment' => JcTable::CENTER,
        ];
        $phpWord->addTableStyle('DataTable', $dataTableStyle);

        if ($type === 'vps') {
            $section->addText('DAFTAR RINCIAN INFRASTRUKTUR CLOUD VPS', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('ORDER ID', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2400, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2200, ['bgColor' => '0F172A'])->addText('PAKET / SPESIFIKASI', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1600, ['bgColor' => '0F172A'])->addText('OS / PANEL', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('NILAI (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['vpsOrders'] as $idx => $order) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $table->addRow();
                $table->addCell(1400, ['bgColor' => $bg])->addText('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT), ['size' => 8]);
                $table->addCell(2400, ['bgColor' => $bg])->addText($order->customer?->email ?? '-', ['size' => 8]);
                $specText = $order->vpsSpec?->name ?? 'Standard VPS';
                if ($order->vpsSpec) {
                    $specText .= " ({$order->vpsSpec->cpu}C/{$order->vpsSpec->ram}GB/{$order->vpsSpec->disk}GB)";
                }
                $table->addCell(2200, ['bgColor' => $bg])->addText($specText, ['size' => 7.5]);
                $table->addCell(1600, ['bgColor' => $bg])->addText(($order->os ?? 'Linux') . ' • ' . $order->control_panel_label, ['size' => 7.5]);
                $table->addCell(1400, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $order->status)), ['size' => 8, 'bold' => true]);
                $table->addCell(1500, ['bgColor' => $bg])->addText('Rp ' . number_format($order->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
            }
        } elseif ($type === 'ai_combo') {
            $section->addText('DAFTAR RINCIAN WORKSTATION & AI AGENT', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('ORDER ID', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2400, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2400, ['bgColor' => '0F172A'])->addText('PAKET AI / ENGINE', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1800, ['bgColor' => '0F172A'])->addText('AI STACK / RUNTIME', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1200, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('NILAI (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['aiOrders'] as $idx => $order) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $table->addRow();
                $table->addCell(1400, ['bgColor' => $bg])->addText('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT), ['size' => 8]);
                $table->addCell(2400, ['bgColor' => $bg])->addText($order->customer?->email ?? '-', ['size' => 8]);
                $table->addCell(2400, ['bgColor' => $bg])->addText($order->vpsSpec?->name ?? 'AI Workstation', ['size' => 8, 'bold' => true]);
                $table->addCell(1800, ['bgColor' => $bg])->addText($order->control_panel_label, ['size' => 7.5]);
                $table->addCell(1200, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $order->status)), ['size' => 8, 'bold' => true]);
                $table->addCell(1500, ['bgColor' => $bg])->addText('Rp ' . number_format($order->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
            }
        } elseif ($type === 'database') {
            $section->addText('DAFTAR RINCIAN MANAGED DATABASE SERVER', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('ORDER ID', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2400, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2200, ['bgColor' => '0F172A'])->addText('PAKET DATABASE', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1800, ['bgColor' => '0F172A'])->addText('ENGINE & MANAGER', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1200, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('NILAI (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['dbOrders'] as $idx => $order) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $table->addRow();
                $table->addCell(1400, ['bgColor' => $bg])->addText('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT), ['size' => 8]);
                $table->addCell(2400, ['bgColor' => $bg])->addText($order->customer?->email ?? '-', ['size' => 8]);
                $table->addCell(2200, ['bgColor' => $bg])->addText($order->vpsSpec?->name ?? 'DB Micro Server', ['size' => 8, 'bold' => true]);
                $engineText = strtoupper($order->db_engine ?? 'PostgreSQL / MySQL');
                if ($order->db_manager) {
                    $engineText .= ' (' . ucfirst($order->db_manager) . ')';
                }
                $table->addCell(1800, ['bgColor' => $bg])->addText($engineText, ['size' => 7.5]);
                $table->addCell(1200, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $order->status)), ['size' => 8, 'bold' => true]);
                $table->addCell(1500, ['bgColor' => $bg])->addText('Rp ' . number_format($order->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
            }
        } elseif ($type === 'orders') {
            $section->addText('DAFTAR KONSOLIDASI SELURUH PESANAN LAYANAN', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('NO. ORDER', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2200, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1600, ['bgColor' => '0F172A'])->addText('KATEGORI', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('TOTAL (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('TANGGAL', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['orders'] as $idx => $order) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $cat = $order->isAiPackage() ? 'AI AGENT' : ($order->isDatabasePackage() ? 'MANAGED DB' : 'CLOUD VPS');
                $table->addRow();
                $table->addCell(1400, ['bgColor' => $bg])->addText('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT), ['size' => 8]);
                $table->addCell(2200, ['bgColor' => $bg])->addText($order->customer?->email ?? '-', ['size' => 8]);
                $table->addCell(1600, ['bgColor' => $bg])->addText($cat, ['size' => 7.5, 'bold' => true]);
                $table->addCell(1400, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $order->status)), ['size' => 8, 'bold' => true]);
                $table->addCell(1500, ['bgColor' => $bg])->addText('Rp ' . number_format($order->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
                $table->addCell(1400, ['bgColor' => $bg])->addText($order->created_at ? $order->created_at->format('d/m/Y') : '-', ['size' => 8]);
            }
        } elseif ($type === 'invoices') {
            $section->addText('DAFTAR RINCIAN INVOICE & PENAGIHAN', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(2000, ['bgColor' => '0F172A'])->addText('NO. INVOICE', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('NO. ORDER', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1500, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1800, ['bgColor' => '0F172A'])->addText('NOMINAL (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1400, ['bgColor' => '0F172A'])->addText('TERBIT', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1300, ['bgColor' => '0F172A'])->addText('LUNAS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['invoices'] as $idx => $invoice) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $table->addRow();
                $table->addCell(2000, ['bgColor' => $bg])->addText($invoice->invoice_number ?? ('INV-' . $invoice->id), ['size' => 8]);
                $table->addCell(1500, ['bgColor' => $bg])->addText($invoice->order_id ? '#ORD-' . str_pad($invoice->order_id, 5, '0', STR_PAD_LEFT) : '-', ['size' => 8]);
                $table->addCell(1500, ['bgColor' => $bg])->addText(strtoupper($invoice->status), ['size' => 8, 'bold' => true]);
                $table->addCell(1800, ['bgColor' => $bg])->addText('Rp ' . number_format($invoice->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
                $table->addCell(1400, ['bgColor' => $bg])->addText($invoice->issued_at ? $invoice->issued_at->format('d/m/Y') : ($invoice->created_at ? $invoice->created_at->format('d/m/Y') : '-'), ['size' => 8]);
                $table->addCell(1300, ['bgColor' => $bg])->addText($invoice->paid_at ? $invoice->paid_at->format('d/m/Y') : '-', ['size' => 8]);
            }
        } elseif ($type === 'tickets') {
            $section->addText('DAFTAR TIKET BANTUAN TEKNIS & SLA', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $table = $section->addTable('DataTable');
            $table->addRow();
            $table->addCell(1200, ['bgColor' => '0F172A'])->addText('ID TIKET', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2500, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(2800, ['bgColor' => '0F172A'])->addText('SUBJEK', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1200, ['bgColor' => '0F172A'])->addText('PRIORITAS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $table->addCell(1800, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['tickets'] as $idx => $ticket) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $table->addRow();
                $table->addCell(1200, ['bgColor' => $bg])->addText('#TCK-' . str_pad($ticket->id, 4, '0', STR_PAD_LEFT), ['size' => 8]);
                $table->addCell(2500, ['bgColor' => $bg])->addText($ticket->customer?->email ?? '-', ['size' => 8]);
                $table->addCell(2800, ['bgColor' => $bg])->addText($ticket->subject ?? '-', ['size' => 8]);
                $table->addCell(1200, ['bgColor' => $bg])->addText(strtoupper($ticket->priority ?? 'normal'), ['size' => 8]);
                $table->addCell(1800, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $ticket->status)), ['size' => 8, 'bold' => true]);
            }
        } else {
            // Executive Summary: 3-Pillar Category Breakdown + Daily Revenue + Recent Orders
            $section->addText('KOMPARASI KINERJA 3 PILAR LAYANAN UTAMA', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $tableCat = $section->addTable('DataTable');
            $tableCat->addRow();
            $tableCat->addCell(3000, ['bgColor' => '0F172A'])->addText('PILAR LAYANAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableCat->addCell(2000, ['bgColor' => '0F172A'])->addText('TOTAL ORDER', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableCat->addCell(2000, ['bgColor' => '0F172A'])->addText('AKTIF RUNNING', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableCat->addCell(2500, ['bgColor' => '0F172A'])->addText('TOTAL OMZET (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            $cats = [
                ['Cloud VPS (Compute)', $data['vpsOrders']->count(), $data['vpsOrders']->where('status', 'active')->count(), $data['vpsRevenue']],
                ['AI Agent & Workstation', $data['aiOrders']->count(), $data['aiOrders']->where('status', 'active')->count(), $data['aiRevenue']],
                ['Managed Database Server', $data['dbOrders']->count(), $data['dbOrders']->where('status', 'active')->count(), $data['dbRevenue']],
            ];

            foreach ($cats as $idx => $row) {
                $bg = $idx % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $tableCat->addRow();
                $tableCat->addCell(3000, ['bgColor' => $bg])->addText($row[0], ['size' => 8, 'bold' => true]);
                $tableCat->addCell(2000, ['bgColor' => $bg])->addText($row[1] . ' pesanan', ['size' => 8]);
                $tableCat->addCell(2000, ['bgColor' => $bg])->addText($row[2] . ' instance', ['size' => 8]);
                $tableCat->addCell(2500, ['bgColor' => $bg])->addText('Rp ' . number_format($row[3], 0, ',', '.'), ['size' => 8, 'bold' => true]);
            }

            $section->addTextBreak(1);

            $section->addText('RINCIAN OMZET HARIAN (PAID INVOICES)', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $tableRev = $section->addTable('DataTable');
            $tableRev->addRow();
            $tableRev->addCell(3500, ['bgColor' => '0F172A'])->addText('TANGGAL', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableRev->addCell(3000, ['bgColor' => '0F172A'])->addText('JUMLAH INVOICE', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableRev->addCell(3000, ['bgColor' => '0F172A'])->addText('TOTAL OMZET (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            $idx = 0;
            foreach ($data['dailyRevenue'] as $date => $amount) {
                $count = $data['invoices']->where('status', 'paid')->filter(fn ($i) => $i->created_at->format('Y-m-d') === $date)->count();
                $bg = $idx++ % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $tableRev->addRow();
                $tableRev->addCell(3500, ['bgColor' => $bg])->addText(Carbon::parse($date)->format('d F Y'), ['size' => 8]);
                $tableRev->addCell(3000, ['bgColor' => $bg])->addText("{$count} invoice", ['size' => 8]);
                $tableRev->addCell(3000, ['bgColor' => $bg])->addText('Rp ' . number_format($amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
            }

            $section->addTextBreak(1);

            $section->addText('15 TRANSAKSI TERAKHIR', ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => '0F172A']);
            $tableOrd = $section->addTable('DataTable');
            $tableOrd->addRow();
            $tableOrd->addCell(1500, ['bgColor' => '0F172A'])->addText('NO. ORDER', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableOrd->addCell(3000, ['bgColor' => '0F172A'])->addText('PELANGGAN', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableOrd->addCell(1800, ['bgColor' => '0F172A'])->addText('STATUS', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableOrd->addCell(1800, ['bgColor' => '0F172A'])->addText('NOMINAL (IDR)', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);
            $tableOrd->addCell(1400, ['bgColor' => '0F172A'])->addText('TANGGAL', ['bold' => true, 'size' => 8, 'color' => 'FFFFFF']);

            foreach ($data['orders']->take(15) as $i => $order) {
                $bg = $i % 2 === 1 ? 'F8FAFC' : 'FFFFFF';
                $tableOrd->addRow();
                $tableOrd->addCell(1500, ['bgColor' => $bg])->addText('#ORD-' . str_pad($order->id, 5, '0', STR_PAD_LEFT), ['size' => 8]);
                $tableOrd->addCell(3000, ['bgColor' => $bg])->addText($order->customer?->email ?? '-', ['size' => 8]);
                $tableOrd->addCell(1800, ['bgColor' => $bg])->addText(strtoupper(str_replace('_', ' ', $order->status)), ['size' => 8, 'bold' => true]);
                $tableOrd->addCell(1800, ['bgColor' => $bg])->addText('Rp ' . number_format($order->amount, 0, ',', '.'), ['size' => 8, 'bold' => true]);
                $tableOrd->addCell(1400, ['bgColor' => $bg])->addText($order->created_at ? $order->created_at->format('d/m/Y') : '-', ['size' => 8]);
            }
        }

        // 5. Formal Sign-off Table
        $section->addTextBreak(2);
        $signTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 40, 'alignment' => JcTable::CENTER]);
        $signTable->addRow();
        $s1 = $signTable->addCell(4750);
        $s1->addText('Disiapkan & Diverifikasi oleh:', ['size' => 8, 'bold' => true, 'color' => '334155']);
        $s1->addTextBreak(2);
        $s1->addText($data['generatedBy'], ['size' => 8.5, 'bold' => true, 'color' => '0F172A', 'underline' => 'single']);
        $s1->addText('Administrator Sistem VexaHost', ['size' => 7.5, 'color' => '64748B']);

        $s2 = $signTable->addCell(4750);
        $s2->addText('Disetujui & Diotorisasi oleh:', ['size' => 8, 'bold' => true, 'color' => '334155']);
        $s2->addTextBreak(2);
        $s2->addText('Direksi & Operations VexaHost', ['size' => 8.5, 'bold' => true, 'color' => '0F172A', 'underline' => 'single']);
        $s2->addText('PT DESTINARA CHAKRAWALA ARTHA', ['size' => 7.5, 'color' => '64748B']);

        $section->addTextBreak(1);
        $section->addText('* Laporan resmi ini dihasilkan secara otomatis oleh VexaHost Enterprise Reporting System. Dokumen ini dilindungi ketentuan kerahasiaan perusahaan (Strictly Confidential).', [
            'name' => 'Arial',
            'size' => 7.5,
            'color' => '64748B',
            'italic' => true,
        ]);

        // Add page numbers to footer
        $footer = $section->addFooter();
        $footer->addPreserveText('Halaman {PAGE_NUM} dari {NUMPAGES}  •  VexaHost Enterprise Confidential Report', [
            'name' => 'Arial',
            'size' => 7.5,
            'color' => '64748B',
        ], ['alignment' => Jc::CENTER]);

        $tempFile = tempnam(sys_get_temp_dir(), 'vxh_rep_') . '.docx';
        $objWriter = IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        return response()->download($tempFile, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Stream Excel-ready CSV export with UTF-8 BOM.
     */
    public function streamCsv(string $type, mixed $days): StreamedResponse
    {
        $data = $this->getReportData($type, $days);
        $filename = sprintf('%s-%s-%s.csv', $data['filenamePrefix'], Str::slug($data['periodLabel']), now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($type, $data) {
            $out = fopen('php://output', 'w');

            // UTF-8 BOM for clean Microsoft Excel parsing on Windows
            fwrite($out, "\xEF\xBB\xBF");

            if ($type === 'vps') {
                fputcsv($out, ['ID Order', 'Email Pelanggan', 'Nama Pelanggan', 'Paket VPS', 'CPU (Core)', 'RAM (GB)', 'Disk NVMe (GB)', 'OS', 'Control Panel', 'Datacenter', 'Status', 'Nominal (IDR)', 'Siklus', 'Tanggal Dibuat']);
                foreach ($data['vpsOrders'] as $row) {
                    fputcsv($out, [
                        'ORD-' . str_pad($row->id, 5, '0', STR_PAD_LEFT),
                        $row->customer?->email ?? '',
                        $row->customer?->full_name ?? ($row->customer?->username ?? ''),
                        $row->vpsSpec?->name ?? 'Standard VPS',
                        $row->vpsSpec?->cpu ?? 1,
                        $row->vpsSpec?->ram ?? 1,
                        $row->vpsSpec?->disk ?? 20,
                        $row->os ?? 'Ubuntu',
                        $row->control_panel_label,
                        strtoupper($row->datacenter_location ?? ($row->provider ?? 'JAKARTA')),
                        $row->status,
                        $row->amount,
                        $row->billing_cycle ?? 'monthly',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } elseif ($type === 'ai_combo') {
                fputcsv($out, ['ID Order', 'Email Pelanggan', 'Nama Pelanggan', 'Paket AI Agent', 'AI Stacks / Pre-installed', 'CPU (Core)', 'RAM (GB)', 'Status', 'Nominal (IDR)', 'Siklus', 'Tanggal Dibuat']);
                foreach ($data['aiOrders'] as $row) {
                    fputcsv($out, [
                        'ORD-' . str_pad($row->id, 5, '0', STR_PAD_LEFT),
                        $row->customer?->email ?? '',
                        $row->customer?->full_name ?? ($row->customer?->username ?? ''),
                        $row->vpsSpec?->name ?? 'AI Workstation',
                        $row->control_panel_label,
                        $row->vpsSpec?->cpu ?? 2,
                        $row->vpsSpec?->ram ?? 4,
                        $row->status,
                        $row->amount,
                        $row->billing_cycle ?? 'monthly',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } elseif ($type === 'database') {
                fputcsv($out, ['ID Order', 'Email Pelanggan', 'Nama Pelanggan', 'Paket Database', 'Database Engine', 'DB Manager', 'Port', 'Status', 'Nominal (IDR)', 'Siklus', 'Tanggal Dibuat']);
                foreach ($data['dbOrders'] as $row) {
                    fputcsv($out, [
                        'ORD-' . str_pad($row->id, 5, '0', STR_PAD_LEFT),
                        $row->customer?->email ?? '',
                        $row->customer?->full_name ?? ($row->customer?->username ?? ''),
                        $row->vpsSpec?->name ?? 'DB Micro Server',
                        strtoupper($row->db_engine ?? 'PostgreSQL'),
                        $row->db_manager ?? 'Direct/CloudBeaver',
                        $row->db_port ?? '5432',
                        $row->status,
                        $row->amount,
                        $row->billing_cycle ?? 'monthly',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } elseif ($type === 'orders') {
                fputcsv($out, ['ID Order', 'Email Pelanggan', 'Nama Pelanggan', 'Kategori Layanan', 'Paket Layanan', 'Stack / Panel', 'Channel', 'Status', 'Nominal (IDR)', 'Siklus Tagihan', 'Dibuat Pada']);
                foreach ($data['orders'] as $row) {
                    $cat = $row->isAiPackage() ? 'AI AGENT' : ($row->isDatabasePackage() ? 'MANAGED DB' : 'CLOUD VPS');
                    fputcsv($out, [
                        'ORD-' . str_pad($row->id, 5, '0', STR_PAD_LEFT),
                        $row->customer?->email ?? '',
                        $row->customer?->full_name ?? ($row->customer?->username ?? ''),
                        $cat,
                        $row->vpsSpec?->name ?? 'Standard Plan',
                        $row->control_panel_label,
                        strtoupper($row->channel ?? 'DIRECT'),
                        $row->status,
                        $row->amount,
                        $row->billing_cycle ?? 'monthly',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } elseif ($type === 'invoices') {
                fputcsv($out, ['Nomor Invoice', 'ID Order', 'Email Pelanggan', 'Status', 'Nominal (IDR)', 'Tanggal Terbit', 'Jatuh Tempo', 'Tanggal Lunas']);
                foreach ($data['invoices'] as $row) {
                    fputcsv($out, [
                        $row->invoice_number ?? ('INV-' . $row->id),
                        $row->order_id ? 'ORD-' . str_pad($row->order_id, 5, '0', STR_PAD_LEFT) : '',
                        $row->order?->customer?->email ?? '',
                        $row->status,
                        $row->amount,
                        $row->issued_at ? $row->issued_at->format('Y-m-d H:i:s') : '',
                        $row->due_at ? $row->due_at->format('Y-m-d H:i:s') : '',
                        $row->paid_at ? $row->paid_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } elseif ($type === 'tickets') {
                fputcsv($out, ['ID Tiket', 'Email Pelanggan', 'Nama Pelanggan', 'Subjek', 'Prioritas', 'Status', 'SLA Due At', 'First Response At', 'Dibuat Pada']);
                foreach ($data['tickets'] as $row) {
                    fputcsv($out, [
                        'TCK-' . str_pad($row->id, 4, '0', STR_PAD_LEFT),
                        $row->customer?->email ?? '',
                        $row->customer?->full_name ?? ($row->customer?->username ?? ''),
                        $row->subject,
                        $row->priority,
                        $row->status,
                        $row->sla_due_at ? $row->sla_due_at->format('Y-m-d H:i:s') : '',
                        $row->first_response_at ? $row->first_response_at->format('Y-m-d H:i:s') : '',
                        $row->created_at ? $row->created_at->format('Y-m-d H:i:s') : '',
                    ]);
                }
            } else {
                // Executive summary CSV
                fputcsv($out, ['METRIK LAPORAN EKSEKUTIF VEXAHOST', 'NILAI', 'KETERANGAN']);
                fputcsv($out, ['Periode Laporan', $data['periodLabel'], '']);
                fputcsv($out, ['Nomor Referensi', $data['reportRef'], '']);
                fputcsv($out, ['Tanggal Cetak', now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . ' WIB', '']);
                fputcsv($out, ['Dicetak Oleh', $data['generatedBy'], '']);
                fputcsv($out, ['Total Omzet Lunas', $data['revenue'], 'IDR']);
                fputcsv($out, ['Omzet Cloud VPS', $data['vpsRevenue'], 'IDR']);
                fputcsv($out, ['Omzet AI Agent & Combo', $data['aiRevenue'], 'IDR']);
                fputcsv($out, ['Omzet Managed Database', $data['dbRevenue'], 'IDR']);
                fputcsv($out, ['Jumlah Invoice Lunas', $data['paidInvoices'], 'Transaksi Berhasil']);
                fputcsv($out, ['Total Jumlah Pesanan', $data['orders']->count(), 'Pesanan']);
                fputcsv($out, ['Pesanan Cloud VPS', $data['vpsOrders']->count(), 'Pesanan']);
                fputcsv($out, ['Pesanan AI Agent', $data['aiOrders']->count(), 'Pesanan']);
                fputcsv($out, ['Pesanan Managed DB', $data['dbOrders']->count(), 'Pesanan']);
                fputcsv($out, ['Pesanan Aktif Berjalan', $data['orders']->where('status', 'active')->count(), 'Layanan Aktif']);
                fputcsv($out, ['Rata-rata Respon SLA', $data['avgFirstResponse'] ? round($data['avgFirstResponse'] / 60, 2) . ' Jam' : 'N/A', 'Waktu Respons Pertama']);
                fputcsv($out, []);

                fputcsv($out, ['TANGGAL', 'OMZET HARIAN (IDR)', 'JUMLAH INVOICE']);
                foreach ($data['dailyRevenue'] as $date => $amount) {
                    $cnt = $data['invoices']->where('status', 'paid')->filter(fn ($i) => $i->created_at->format('Y-m-d') === $date)->count();
                    fputcsv($out, [$date, $amount, $cnt]);
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
