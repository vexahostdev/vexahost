@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => $badgeText,
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    {{ $headingText }}
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo {{ $user->full_name ?? 'Pelanggan' }},<br>
    {{ $bodyText }}
    @if(!$isTerminal)
        <br><br>Jika ingin melanjutkan, segera klik tombol di bawah ini.
    @endif
</p>

<!-- Rincian Layanan -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 12px 18px; border-bottom: 1px solid #e4e4e7; background-color: #f4f4f5; border-top-left-radius: 6px; border-top-right-radius: 6px;">
            <strong style="color: #000000; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Rincian Layanan</strong>
        </td>
    </tr>
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 38%;">Hostname</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $instance->hostname ?? '-' }}</td>
                </tr>
                @if(!empty($instance->public_ip))
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Alamat IP</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $instance->public_ip }}</td>
                </tr>
                @endif
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Jatuh Tempo</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $dueText }}</td>
                </tr>
                @if(!$isTerminal)
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Akan Dihapus Pada</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $graceEndText }}</td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>

@if($stage === 'grace_final')
<!-- Peringatan batas pemulihan -->
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fffbeb; border: 1px solid #fde68a; border-radius: 6px; margin-bottom: 24px;">
    <tr>
        <td style="padding: 14px 18px; font-size: 13px; line-height: 1.6; color: #92400e;">
            <strong>Penting:</strong> setelah {{ $graceEndText }}, seluruh data pada layanan ini akan dihapus permanen dan tidak dapat dipulihkan. Mohon lakukan pencadangan bila Anda belum memperpanjang.
        </td>
    </tr>
</table>
@endif

@if($isTerminal)
<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; margin-bottom: 24px;">
    <tr>
        <td style="padding: 14px 18px; font-size: 13px; line-height: 1.6; color: #991b1b;">
            <strong>Data telah dihapus permanen.</strong> Layanan ini tidak dapat dipulihkan. Silakan lakukan pemesanan baru bila Anda ingin melanjutkan.
        </td>
    </tr>
</table>
@endif

<!-- Tombol Aksi -->
<table border="0" cellpadding="0" cellspacing="0" style="margin-bottom: 8px;">
    <tr>
        <td align="center" style="border-radius: 6px; background-color: #000000;">
            <a href="{{ $isTerminal ? rtrim(config('app.url', 'https://vexahostcloud.my.id'), '/') . '/' : $renewUrl }}"
               target="_blank" rel="noopener noreferrer" class="action-button"
               style="display: inline-block; padding: 12px 28px; font-size: 13px; font-weight: 700; color: #ffffff; text-decoration: none; border-radius: 6px;">
                {{ $isTerminal ? 'Pesan Layanan Baru' : 'Perpanjang Sekarang' }}
            </a>
        </td>
    </tr>
</table>
@endsection
