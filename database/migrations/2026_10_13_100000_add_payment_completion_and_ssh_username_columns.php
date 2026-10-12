<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Penjaga "setelah lunas" + username SSH per VPS.
 *
 * orders.paid_notified_at     : notifikasi lunas (pelanggan + admin) sudah dikirim.
 * orders.renewal_applied_at   : masa aktif perpanjangan sudah ditambahkan (anti dobel).
 * orders.admin_reminder_count : jumlah pengingat admin untuk order yang belum diserahkan.
 * orders.admin_reminded_at    : waktu pengingat admin terakhir.
 * vps_instances.ssh_username  : username login SSH; default 'root' sehingga VPS lama tidak berubah.
 *
 * Backfill aman untuk data production:
 *  - Order lama yang sudah lunas dianggap sudah dinotifikasi (tidak ada kiriman ulang).
 *  - Perpanjangan yang sudah diproses (status bukan 'paid' atau sudah delivered) ditandai
 *    applied. Perpanjangan lunas yang masih 'paid' & belum delivered sengaja dibiarkan
 *    kosong agar bisa diperiksa lewat `php artisan renewal:repair`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'paid_notified_at')) {
                $table->timestamp('paid_notified_at')->nullable()->after('paid_at');
            }
            if (!Schema::hasColumn('orders', 'renewal_applied_at')) {
                $table->timestamp('renewal_applied_at')->nullable()->after('paid_notified_at');
            }
            if (!Schema::hasColumn('orders', 'admin_reminder_count')) {
                $table->unsignedSmallInteger('admin_reminder_count')->default(0)->after('renewal_applied_at');
            }
            if (!Schema::hasColumn('orders', 'admin_reminded_at')) {
                $table->timestamp('admin_reminded_at')->nullable()->after('admin_reminder_count');
            }
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            if (!Schema::hasColumn('vps_instances', 'ssh_username')) {
                $table->string('ssh_username', 32)->default('root')->after('ssh_port');
            }
        });

        DB::table('orders')
            ->whereNotNull('paid_at')
            ->whereNull('paid_notified_at')
            ->update(['paid_notified_at' => DB::raw('paid_at')]);

        DB::table('orders')
            ->where(fn ($q) => $q->where('order_type', 'renewal')->orWhereNotNull('vps_instance_id'))
            ->whereNotNull('paid_at')
            ->whereNull('renewal_applied_at')
            ->where(function ($q) {
                $q->where('status', '!=', 'paid')->orWhereNotNull('delivered_at');
            })
            ->update(['renewal_applied_at' => DB::raw('COALESCE(delivered_at, paid_at)')]);

        DB::table('vps_instances')
            ->whereNull('ssh_username')
            ->update(['ssh_username' => 'root']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['admin_reminded_at', 'admin_reminder_count', 'renewal_applied_at', 'paid_notified_at'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('vps_instances', function (Blueprint $table) {
            if (Schema::hasColumn('vps_instances', 'ssh_username')) {
                $table->dropColumn('ssh_username');
            }
        });
    }
};
