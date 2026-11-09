<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * orders.renewal_rejected_at: pembayaran perpanjangan masuk setelah batas akhir
 * sehingga ditolak. Menjaga agar penolakan dan notifikasinya hanya terjadi sekali.
 *
 * orders.payment_received_at: waktu pelanggan benar-benar membayar menurut gateway
 * atau admin. paid_at sengaja tidak diubah artinya (waktu sistem memproses) karena
 * dipakai untuk menghitung masa aktif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'renewal_rejected_at')) {
                $table->timestamp('renewal_rejected_at')->nullable()->after('renewal_applied_at');
            }
            if (!Schema::hasColumn('orders', 'payment_received_at')) {
                $table->timestamp('payment_received_at')->nullable()->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['renewal_rejected_at', 'payment_received_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
