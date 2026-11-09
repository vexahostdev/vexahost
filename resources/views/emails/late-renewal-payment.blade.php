@extends('emails.layouts.master', [
    'subject' => $subject,
    'badgeText' => 'PERPANJANGAN DITUTUP',
])

@section('content')
<h1 style="margin: 0 0 12px 0; font-size: 20px; font-weight: 700; color: #000000; line-height: 1.3; letter-spacing: -0.3px;">
    Pembayaran Perpanjangan Tidak Dapat Diproses
</h1>

<p style="margin: 0 0 20px 0; font-size: 14px; line-height: 1.6; color: #52525b;">
    Halo {{ $user->full_name ?? 'Pelanggan' }},<br>
    {{ $bodyText }}
</p>

<table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #fafafa; border: 1px solid #e4e4e7; border-radius: 6px; margin-bottom: 24px; font-size: 13px;">
    <tr>
        <td style="padding: 16px 18px;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                <tr>
                    <td style="padding: 6px 0; color: #71717a; width: 38%;">Layanan</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $serviceName }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Nominal</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $amountText }}</td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #71717a;">Batas Akhir</td>
                    <td style="padding: 6px 0; color: #18181b; font-weight: 600;">{{ $deadlineText }}</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
@endsection
