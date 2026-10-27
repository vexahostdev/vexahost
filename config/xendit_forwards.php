<?php

/**
 * Tabel routing webhook sentral Xendit: prefix external_id => aplikasi tujuan.
 * (WAG- dan BC- masih ditangani langsung di PaymentWebhookController::handleXendit.)
 *
 * Tambah aplikasi baru dengan SATU entri di bawah ini, mis.:
 *   'NTF-' => ['name' => 'Notifora', 'url' => env('NOTIFORA_WEBHOOK_URL', 'https://…/api/payment/xendit/callback')],
 * Aplikasi tujuan memverifikasi header X-Internal-Token (= INTERNAL_WEBHOOK_SECRET milik VexaHost).
 */
return [
    'PSV-' => [
        'name' => 'Postivo',
        'url' => env('POSTIVO_WEBHOOK_URL', 'https://postivo.my.id/api/payment/xendit/callback'),
    ],
    'NTF-' => [
        'name' => 'Notifora',
        'url' => env('NOTIFORA_WEBHOOK_URL', 'https://notifora.my.id/api/payment/xendit/callback'),
    ],
];
