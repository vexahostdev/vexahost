<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $order) {
            if (!$order->organization_id && $order->customer_id) {
                $order->organization_id = User::find($order->customer_id)?->current_organization_id;
            }
        });
    }

    protected $fillable = [
        'customer_id',
        'organization_id',
        'vps_spec_id',
        'subscription_id',
        'control_panel',
        'db_engine',
        'db_manager',
        'db_name',
        'db_user',
        'db_password',
        'db_port',
        'provider',
        'hostname',
        'root_password',
        'datacenter_location',
        'os',
        'billing_cycle',
        'order_type',
        'vps_instance_id',
        'status',
        'channel',
        'shopee_order_id',
        'payment_method',
        'amount',
        'currency',
        'setup_fee',
        'paid_at',
        'starts_at',
        'expires_at',
        'failure_reason',
        'provisioning_attempts',
        'grace_period_ends_at',
        'last_status_change_at',
        // PHASE 2 - versi ketentuan yang disetujui saat pemesanan dibuat.
        'terms_version',
        // PHASE 5 - kemajuan kerja manual di papan fulfillment.
        'fulfillment_stage',
        'delivered_at',
        'sla_alerted_at',
        // Penjaga "setelah lunas" (PaymentCompletionService).
        'paid_notified_at',
        'renewal_applied_at',
        'admin_reminder_count',
        'admin_reminded_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
        'delivered_at' => 'datetime',
        'sla_alerted_at' => 'datetime',
        'paid_notified_at' => 'datetime',
        'renewal_applied_at' => 'datetime',
        'admin_reminded_at' => 'datetime',
        'admin_reminder_count' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'last_status_change_at' => 'datetime',
        'amount' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'provisioning_attempts' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function vpsSpec()
    {
        return $this->belongsTo(VpsSpec::class, 'vps_spec_id');
    }

    public function getControlPanelLabelAttribute(): string
    {
        return self::stackLabels()[$this->control_panel] ?? str_replace('_', ' ', ucfirst((string) $this->control_panel));
    }

    public static function stackLabels(): array
    {
        return [
            'none' => 'Tanpa Control Panel', 'coolify' => 'Coolify', 'dokploy' => 'Dokploy', 'aapanel' => 'aaPanel',
            'cloudpanel' => 'CloudPanel', 'docker' => 'Docker', 'cyberpanel' => 'CyberPanel', 'hestiacp' => 'HestiaCP',
            'hermes_agent' => 'Hermes Agent', 'openclaw' => 'OpenClaw', 'omniroute' => 'OmniRoute', '9router' => '9router',
            'agent_zero' => 'Agent Zero', 'n8n' => 'n8n', 'ollama' => 'Ollama', 'anythingllm' => 'AnythingLLM',
            'librechat' => 'LibreChat', 'vscode_server' => 'Visual Studio Code Server', 'gitea_forgejo' => 'Gitea / Forgejo',
            'uptime_kuma' => 'Uptime Kuma', 'netdata_beszel' => 'Netdata / Beszel', 'wordpress' => 'WordPress', 'ghost' => 'Ghost',
            'strapi_directus' => 'Strapi / Directus', 'prestashop_bagisto' => 'PrestaShop / Bagisto',
            'claude_opencode' => 'Claude Code & OpenCode CLI Stack',
            'dify_ollama' => 'Dify AI + Ollama Private RAG Studio',
            'managed_database' => 'Dedicated Managed Database Server',
        ];
    }

    public function isAiPackage(): bool
    {
        if ($this->vpsSpec) {
            return $this->vpsSpec->isAiPackage();
        }
        return in_array($this->control_panel, ['vscode_server', 'hermes_agent', 'hermes_omniroute', 'claude_opencode', 'dify_ollama', 'anythingllm', 'ollama'], true);
    }

    public function isDatabasePackage(): bool
    {
        if ($this->vpsSpec) {
            return $this->vpsSpec->isDatabasePackage();
        }
        return $this->control_panel === 'managed_database';
    }

    public static function providerLabels(): array
    {
        return ['tencent' => 'Tencent Cloud', 'cloudeka' => 'Cloudeka by Lintasarta'];
    }

    public static function operatingSystems(): array
    {
        return [
            'tencent' => [
                'windows2012r2' => 'Windows Server 2012 R2 DataCenter 64bit EN',
                'windows2016' => 'Windows Server 2016 DataCenter 64bit EN',
                'windows2019' => 'Windows Server 2019 DataCenter 64bit EN',
                'windows2022' => 'Windows Server 2022 DataCenter 64bit EN',
                'ubuntu2404' => 'Ubuntu Server 24.04 LTS 64bit',
                'ubuntu2204' => 'Ubuntu Server 22.04 LTS 64bit',
                'opencloudos9' => 'OpenCloudOS 9', 'opencloudos8' => 'OpenCloudOS 8',
                'centos76' => 'CentOS 7.6 64bit', 'centos_stream9' => 'CentOS Stream 9 64bit',
                'debian12' => 'Debian 12.0 64bit', 'rocky94' => 'Rocky Linux 9.4 64bit',
                'debian11' => 'Debian 11.1 64bit', 'debian10' => 'Debian 10.2 64bit',
            ],
            'cloudeka' => ['ubuntu2404' => 'Ubuntu Server 24.04 LTS 64bit', 'ubuntu2204' => 'Ubuntu Server 22.04 LTS 64bit'],
        ];
    }

    public static function osLabels(): array
    {
        return array_merge(...array_values(self::operatingSystems()));
    }

    /**
     * Label infrastruktur yang tampil ke pelanggan (dasbor, faktur).
     *
     * Nilai yang tidak dikenal TIDAK boleh ditampilkan mentah: kolom provider
     * dapat berisi nama driver internal (mis. supplier/reseller), dan nama itu
     * tidak boleh sampai ke pelanggan.
     */
    public const NEUTRAL_PROVIDER_LABEL = 'VexaHost Cloud';

    public static function customerProviderLabel(?string $provider): string
    {
        return self::providerLabels()[$provider ?? ''] ?? self::NEUTRAL_PROVIDER_LABEL;
    }

    public function getProviderLabelAttribute(): string
    {
        return self::customerProviderLabel($this->provider);
    }

    public function getOsLabelAttribute(): string
    {
        return self::operatingSystems()[$this->provider][$this->os]
            ?? self::osLabels()[$this->os]
            ?? (string) $this->os;
    }

    /**
     * PHASE 5 - butir daftar periksa setup.
     */
    public function fulfillmentChecklists()
    {
        return $this->hasMany(FulfillmentChecklist::class);
    }

    /**
     * PHASE 5 - catatan pembelian di Supplier untuk order ini.
     */
    public function supplierPurchases()
    {
        return $this->hasMany(SupplierPurchase::class);
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class, 'order_id');
    }

    public function vpsInstance()
    {
        return $this->hasOne(VpsInstance::class, 'order_id');
    }

    public function statusHistories()
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function paymentTransactions()
    {
        return $this->hasMany(PaymentTransaction::class)->orderBy('created_at', 'desc');
    }

    public function successfulPayment()
    {
        return $this->hasOne(PaymentTransaction::class)->where('status', 'settled');
    }

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    public function renewalVpsInstance()
    {
        return $this->belongsTo(VpsInstance::class, 'vps_instance_id');
    }

    public function getResolvedInstanceAttribute(): ?VpsInstance
    {
        return $this->vps_instance_id ? $this->renewalVpsInstance : $this->vpsInstance;
    }

    /**
     * Awal masa aktif layanan = waktu pelunasan, BUKAN waktu admin selesai setup.
     * Keterlambatan setup tidak boleh menggeser tanggal jatuh tempo pelanggan.
     * Cadangan now() hanya untuk order lama tanpa paid_at.
     */
    public function activationStartsAt(): \Illuminate\Support\Carbon
    {
        $paidAt = $this->paid_at ? \Illuminate\Support\Carbon::parse($this->paid_at) : null;

        return ($paidAt && $paidAt->lessThanOrEqualTo(now())) ? $paidAt->copy() : now();
    }

    /** Janji waktu setup ke pelanggan (Ketentuan Layanan pasal 5.5). */
    public const SETUP_SLA_HOURS = 24;

    /**
     * Awal masa aktif saat admin menyerahkan server (order baru).
     * - Diserahkan ≤ 24 jam sejak lunas → dihitung sejak lunas.
     * - Lewat 24 jam (SLA dilanggar) → dihitung sejak diserahkan, sebagai kompensasi
     *   otomatis sehingga pelanggan tidak kehilangan masa aktif karena keterlambatan kita.
     */
    public function provisioningStartsAt(): \Illuminate\Support\Carbon
    {
        $paidAt = $this->activationStartsAt();

        return $this->setupSlaBreached($paidAt) ? now() : $paidAt;
    }

    public function setupSlaBreached(?\Illuminate\Support\Carbon $paidAt = null): bool
    {
        $paidAt ??= $this->activationStartsAt();

        return $paidAt->copy()->addHours(self::SETUP_SLA_HOURS)->lessThan(now());
    }

    public function isRenewal(): bool
    {
        return $this->order_type === 'renewal' || !empty($this->vps_instance_id);
    }

    public function isNew(): bool
    {
        return !$this->isRenewal();
    }

    public function provisioningTasks()
    {
        return $this->hasMany(ProvisioningTask::class)->orderBy('created_at', 'desc');
    }

    public function latestProvisioningTask()
    {
        return $this->hasOne(ProvisioningTask::class)->latestOfMany();
    }

    public function getPaymentMethodNameAttribute(): string
    {
        $names = [
            'lynk' => 'Lynk.id Checkout',
            'qris' => 'QRIS Manual',
            'midtrans_snap' => 'QRIS Otomatis',
            'mandiri_va' => 'Mandiri Virtual Account',
            'bni_va' => 'BNI Virtual Account',
            'bri_va' => 'BRI Virtual Account',
            'cimb_va' => 'CIMB Niaga VA',
            'permata_va' => 'Permata Bank VA',
            'other_va' => 'Virtual Account Bank Lainnya',
            'gopay' => 'GoPay',
            'bca_va' => 'BCA Virtual Account',
            'bsi_va' => 'BSI Virtual Account',
            'danamon_va' => 'Danamon Virtual Account',
            'seabank_va' => 'SeaBank Virtual Account',
            'credit_card' => 'Kartu Kredit / Debit',
            'ovo' => 'OVO',
            'dana' => 'DANA',
            'shopeepay' => 'ShopeePay',
        ];

        return $names[$this->payment_method] ?? strtoupper((string) $this->payment_method);
    }
}
