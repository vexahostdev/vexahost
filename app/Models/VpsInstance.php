<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class VpsInstance extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $instance) {
            if (!$instance->organization_id && $instance->customer_id) {
                $instance->organization_id = User::find($instance->customer_id)?->current_organization_id;
            }
        });
    }

    public function getControlPanelLabelAttribute(): string
    {
        return Order::stackLabels()[$this->control_panel] ?? str_replace('_', ' ', ucfirst((string) $this->control_panel));
    }

    public function getProviderLabelAttribute(): string
    {
        // Nilai tak dikenal (mis. nama driver supplier) diganti label netral.
        return Order::customerProviderLabel($this->provider);
    }

    public function getOsLabelAttribute(): string
    {
        return Order::osLabels()[$this->os] ?? (string) $this->os;
    }

    protected $fillable = [
        'customer_id',
        'organization_id',
        'order_id',
        'hostname',
        'public_ip',
        'private_ip',
        'ssh_port',
        'ssh_username',
        'initial_root_password',
        'root_password_revealed_at',
        'os',
        'datacenter_location',
        'datacenter_region_id',
        'status',
        'cpu',
        'ram',
        'disk',
        'control_panel',
        'db_engine',
        'db_manager',
        'db_name',
        'db_user',
        'db_password',
        'db_port',
        'provider',
        'provider_resource_id',
        'provider_meta',
        'last_reconciled_at',
        'uptime_percent',
        'app_url',
        'app_name',
        'app_guide',
        'billing_cycle',
        'starts_at',
        'expires_at',
        'grace_period_ends_at',
        'auto_renew',
        'custom_renewal_price',
    ];

    public function isAiPackage(): bool
    {
        if ($this->order && $this->order->vpsSpec) {
            return $this->order->vpsSpec->isAiPackage();
        }
        // Catatan: app_url TIDAK dipakai sebagai penanda paket AI, karena VPS biasa
        // juga menyimpan link control panel (mis. Coolify) di kolom yang sama.
        return in_array($this->control_panel, ['vscode_server', 'hermes_agent', 'hermes_omniroute', 'claude_opencode', 'dify_ollama', 'anythingllm', 'ollama'], true);
    }

    public function isDatabasePackage(): bool
    {
        if ($this->order && $this->order->vpsSpec) {
            return $this->order->vpsSpec->isDatabasePackage();
        }
        return in_array($this->control_panel, ['managed_database'], true)
            || !empty($this->attributes['db_engine'])
            || str_starts_with(strtolower((string)$this->hostname), 'vx-db-');
    }

    public function getDbConnectionDriverAttribute(): string
    {
        return match ($this->db_engine) {
            'mysql' => 'mysql',
            'redis' => 'redis',
            'mongodb' => 'mongodb',
            'vector' => 'qdrant',
            default => 'pgsql',
        };
    }

    public function getDbPortResolvedAttribute(): int
    {
        if ($this->db_port) {
            return (int) $this->db_port;
        }
        return match ($this->db_engine) {
            'mysql' => 3306,
            'redis' => 6379,
            'mongodb' => 27017,
            'vector' => 6333,
            default => 5432,
        };
    }

    public function getDatabaseUrlAttribute(): string
    {
        $driver = match ($this->db_engine) {
            'mysql' => 'mysql',
            'redis' => 'redis',
            'mongodb' => 'mongodb',
            'vector' => 'http',
            default => 'postgresql',
        };
        $user = $this->db_user ?? 'admin_vexa';
        $pass = $this->db_password ?: ($this->initial_root_password ?: 'vexapass123');
        $host = $this->public_ip ?: '127.0.0.1';
        $port = $this->db_port_resolved;
        $name = $this->db_name ?? 'vexadb_production';

        if ($this->db_engine === 'redis') {
            return "redis://:{$pass}@{$host}:{$port}/0";
        }
        if ($this->db_engine === 'vector') {
            return "http://{$host}:{$port}";
        }

        return "{$driver}://{$user}:{$pass}@{$host}:{$port}/{$name}";
    }

    public function getWebManagerUrlResolvedAttribute(): ?string
    {
        if ($this->db_manager === 'cli_only') {
            return null;
        }

        // Link yang diisi admin saat serah terima selalu diutamakan.
        if ($this->panel_url) {
            return $this->panel_url;
        }

        $host = $this->public_ip ?: '127.0.0.1';
        return "https://{$host}:8080";
    }

    /**
     * Link control panel yang diisi admin saat serah terima (kolom app_url).
     *
     * Sengaja tidak menebak dari IP + port: tiap panel memakai port berbeda
     * (Coolify 8000, Dokploy 3000, CloudPanel 8443, aaPanel acak) dan banyak
     * admin memakai domain sendiri. Null berarti link belum diisi admin.
     */
    public function getPanelUrlAttribute(): ?string
    {
        $url = trim((string) ($this->attributes['app_url'] ?? ''));

        return $url !== '' ? $url : null;
    }

    public function getAppUrlAttribute(): ?string
    {
        if (!empty($this->attributes['app_url'])) {
            return $this->attributes['app_url'];
        }

        if (!$this->public_ip) {
            return null;
        }

        return match ($this->control_panel) {
            'vscode_server' => "http://{$this->public_ip}:8443",
            'hermes_agent', 'hermes_omniroute', 'omniroute', '9router' => "http://{$this->public_ip}:8080",
            'anythingllm', 'ollama', 'dify_ollama' => "http://{$this->public_ip}:3000",
            'claude_opencode' => "http://{$this->public_ip}:7681",
            default => ($this->isAiPackage() ? "http://{$this->public_ip}:8000" : null),
        };
    }

    protected $casts = [
        'initial_root_password' => 'encrypted',
        'root_password_revealed_at' => 'datetime',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'last_reconciled_at' => 'datetime',
        'auto_renew' => 'boolean',
        'custom_renewal_price' => 'decimal:2',
        'ssh_port' => 'integer',
        'uptime_percent' => 'float',
        'provider_meta' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * Catatan pembelian di Supplier, satu baris per periode prepaid.
     */
    public function supplierPurchases()
    {
        return $this->hasMany(SupplierPurchase::class)->orderByDesc('purchased_at');
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function activityLogs()
    {
        return $this->hasMany(VpsActivityLog::class, 'vps_instance_id')->latest();
    }

    public function statusHistories()
    {
        return $this->hasMany(VpsStatusHistory::class)->orderBy('created_at', 'desc');
    }

    public function subscription()
    {
        return $this->hasOne(Subscription::class);
    }

    public function datacenterRegion()
    {
        return $this->belongsTo(DatacenterRegion::class);
    }

    public function provisioningTasks()
    {
        return $this->hasMany(ProvisioningTask::class)->orderBy('created_at', 'desc');
    }

    public function latestProvisioningTask()
    {
        return $this->hasOne(ProvisioningTask::class)->latestOfMany();
    }

    public function logActivity(string $action, ?string $description = null, string $status = 'completed', ?int $userId = null): VpsActivityLog
    {
        $resolvedUserId = $userId ?? Auth::id() ?? $this->customer_id;
        $ip = request()?->ip() ?? '127.0.0.1';
        $userAgent = request()?->userAgent();

        return $this->activityLogs()->create([
            'user_id' => $resolvedUserId,
            'action' => $action,
            'description' => $description,
            'status' => $status,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Aturan validasi username SSH (dipakai semua form admin & API).
     * Huruf/angka/titik/garis bawah/strip, diawali huruf atau garis bawah, maks 32.
     */
    public const SSH_USERNAME_RULE = ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z_][A-Za-z0-9._-]{0,31}$/'];

    public const SSH_USERNAME_MESSAGES = [
        'ssh_username.regex' => 'Username SSH hanya boleh huruf, angka, titik, garis bawah, atau strip, dan harus diawali huruf.',
        'ssh_username.max' => 'Username SSH maksimal 32 karakter.',
    ];

    /** Nilai kosong selalu kembali ke 'root' agar tidak pernah tersimpan username kosong. */
    public static function normalizeSshUsername(?string $username): string
    {
        $username = trim((string) $username);

        return $username !== '' ? $username : 'root';
    }

    /** Username login SSH. VPS lama (sebelum kolom ada) otomatis 'root'. */
    public function getSshUserAttribute(): string
    {
        return self::normalizeSshUsername($this->ssh_username ?? null);
    }

    public function getSshCommandAttribute(): string
    {
        $port = $this->ssh_port ?: 22;
        $ip = $this->public_ip ?: '0.0.0.0';
        return "ssh {$this->ssh_user}@{$ip} -p {$port}";
    }

    public function getStatusBadgeAttribute(): array
    {
        return match ($this->status) {
            'running' => [
                'label' => 'Running',
                'color' => 'emerald',
                'bg' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                'dot' => 'bg-emerald-500',
            ],
            'stopped' => [
                'label' => 'Stopped',
                'color' => 'rose',
                'bg' => 'bg-rose-500/10 text-rose-400 border-rose-500/20',
                'dot' => 'bg-rose-500',
            ],
            'rebooting' => [
                'label' => 'Rebooting',
                'color' => 'amber',
                'bg' => 'bg-amber-500/10 text-amber-400 border-amber-500/20',
                'dot' => 'bg-amber-500 animate-pulse',
            ],
            'reinstalling' => [
                'label' => 'Reinstalling OS',
                'color' => 'blue',
                'bg' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                'dot' => 'bg-blue-500 animate-pulse',
            ],
            'provisioning' => [
                'label' => 'Provisioning',
                'color' => 'cyan',
                'bg' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20',
                'dot' => 'bg-cyan-500 animate-pulse',
            ],
            'suspended' => [
                'label' => 'Suspended',
                'color' => 'red',
                'bg' => 'bg-red-500/10 text-red-400 border-red-500/20',
                'dot' => 'bg-red-500',
            ],
            'terminated' => [
                'label' => 'Terminated',
                'color' => 'zinc',
                'bg' => 'bg-zinc-500/10 text-zinc-400 border-zinc-500/20',
                'dot' => 'bg-zinc-500',
            ],
            default => [
                'label' => ucfirst($this->status),
                'color' => 'slate',
                'bg' => 'bg-slate-500/10 text-slate-400 border-slate-500/20',
                'dot' => 'bg-slate-500',
            ],
        };
    }

    public function isExpiringSoon(int $days = 7): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isFuture() && $this->expires_at->diffInDays(now()) <= $days;
    }

    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        return $this->expires_at->isPast();
    }

    public function isInGracePeriod(): bool
    {
        if (!$this->expires_at || !$this->grace_period_ends_at) {
            return false;
        }
        return $this->expires_at->isPast() && $this->grace_period_ends_at->isFuture();
    }

    /**
     * Label status untuk pelanggan.
     *
     * Panel tidak memantau mesin secara langsung (server dibeli retail tanpa
     * API), jadi label ini menggambarkan status LAYANAN, bukan kondisi mesin
     * saat ini. Karena itu tidak memakai kata "Running" maupun animasi live.
     */
    public function getCustomerStatusAttribute(): array
    {
        return match ($this->status) {
            'running' => [
                'label' => 'Aktif',
                'class' => 'border-emerald-300 text-emerald-800 bg-emerald-50',
                'dot' => 'bg-emerald-500',
            ],
            'provisioning' => [
                'label' => 'Sedang Disiapkan',
                'class' => 'border-blue-300 text-blue-800 bg-blue-50',
                'dot' => 'bg-blue-500',
            ],
            'rebooting', 'reinstalling' => [
                'label' => 'Sedang Diproses Tim',
                'class' => 'border-blue-300 text-blue-800 bg-blue-50',
                'dot' => 'bg-blue-500',
            ],
            'stopped' => [
                'label' => 'Dimatikan',
                'class' => 'border-slate-300 text-slate-700 bg-slate-100',
                'dot' => 'bg-slate-400',
            ],
            'suspended' => [
                'label' => 'Ditangguhkan',
                'class' => 'border-amber-300 text-amber-800 bg-amber-50',
                'dot' => 'bg-amber-500',
            ],
            'error' => [
                'label' => 'Dalam Pengecekan',
                'class' => 'border-red-300 text-red-800 bg-red-50',
                'dot' => 'bg-red-500',
            ],
            'terminated' => [
                'label' => 'Dihentikan',
                'class' => 'border-slate-300 text-slate-600 bg-slate-100',
                'dot' => 'bg-slate-400',
            ],
            default => [
                'label' => ucfirst(str_replace('_', ' ', (string) $this->status)),
                'class' => 'border-slate-300 text-slate-700 bg-slate-100',
                'dot' => 'bg-slate-400',
            ],
        };
    }

    /**
     * Sisa hari masa aktif dalam bilangan bulat.
     * Carbon 3 mengembalikan pecahan untuk diffInDays, jadi dibulatkan ke bawah.
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return (int) floor(now()->diffInDays($this->expires_at, false));
    }

    /**
     * Apakah pelanggan boleh mengajukan permintaan layanan (reinstall atau
     * laporan server tidak bisa diakses) untuk server ini.
     */
    public function acceptsServiceRequests(): bool
    {
        if ($this->isExpired()) {
            return false;
        }

        return in_array($this->status, ['running', 'stopped', 'error'], true);
    }

    /**
     * Paket AI Combo dan Managed Database memakai stack bawaan paket,
     * jadi reinstall selalu memasang ulang stack yang sama.
     */
    public function hasFixedStack(): bool
    {
        return $this->isAiPackage() || $this->isDatabasePackage();
    }

    /** Pilihan OS untuk reinstall, mengikuti daftar OS provider server ini. */
    public function reinstallOsOptions(): array
    {
        return Order::operatingSystems()[$this->provider] ?? Order::osLabels();
    }

    /** Pilihan stack untuk reinstall (key => label). */
    public function reinstallStackOptions(): array
    {
        if ($this->hasFixedStack()) {
            return [(string) $this->control_panel => $this->control_panel_label];
        }

        // Stack khusus paket AI/Database tidak ditawarkan untuk VPS biasa.
        return collect(Order::stackLabels())
            ->except(['managed_database', 'claude_opencode', 'dify_ollama'])
            ->all();
    }

    /**
     * Harga perpanjangan efektif:
     * 1. custom_renewal_price jika diisi oleh admin.
     * 2. sell_price paket asli (jika masih renewable).
     * 3. sell_price paket pengganti jika paket asli discontinued/EOL.
     */
    public function getRenewalPriceAttribute(): float
    {
        if ($this->custom_renewal_price !== null) {
            return (float) $this->custom_renewal_price;
        }

        if ($this->order && $this->order->vpsSpec) {
            return $this->order->vpsSpec->effectiveRenewalPrice();
        }

        return 0.0;
    }

    /**
     * Paket spesifikasi efektif untuk perpanjangan.
     */
    public function getRenewalSpecAttribute(): ?VpsSpec
    {
        if ($this->order && $this->order->vpsSpec) {
            return $this->order->vpsSpec->effectiveRenewalSpec();
        }

        return null;
    }

    /**
     * Apakah instance ini dapat diperpanjang secara mandiri oleh pelanggan.
     */
    public function canBeRenewed(): bool
    {
        if (in_array($this->status, ['terminated', 'provisioning'], true)) {
            return false;
        }

        $spec = $this->order?->vpsSpec;
        if ($spec) {
            return $spec->canRenew();
        }

        return true;
    }

    /**
     * Apakah paket lama EOL namun memiliki paket pengganti otomatis.
     */
    public function isEolWithReplacement(): bool
    {
        $spec = $this->order?->vpsSpec;
        return $spec && !$spec->is_renewable && !empty($spec->replacement_spec_id);
    }

    /**
     * Apakah paket lama EOL permanen tanpa paket pengganti (harus hubungi support).
     */
    public function isEolWithoutReplacement(): bool
    {
        $spec = $this->order?->vpsSpec;
        return $spec && !$spec->is_renewable && empty($spec->replacement_spec_id);
    }

    /**
     * Paket pengganti jika dalam kondisi EOL.
     */
    public function getEolReplacementSpecAttribute(): ?VpsSpec
    {
        return $this->order?->vpsSpec?->replacementSpec;
    }

    /**
     * Semua pesanan perpanjangan untuk instance ini.
     */
    public function renewalOrders()
    {
        return $this->hasMany(Order::class, 'vps_instance_id')
            ->where('order_type', 'renewal')
            ->orderBy('created_at', 'desc');
    }
}
