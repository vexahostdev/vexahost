<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Pembaca dan penulis pengaturan sistem.
 *
 * Seluruh nilai dibaca sekali lalu disimpan di cache. Cache dibersihkan
 * otomatis setiap kali ada penyimpanan, sehingga pembacaan berulang
 * di dalam satu request maupun antar request tidak menyentuh basis data.
 */
class SettingsService
{
    public const CACHE_KEY = 'vexahost.settings.all';
    public const CACHE_TTL = 86400;

    /** Cache tingkat request agar tidak bolak-balik ke driver cache. */
    protected ?array $memo = null;

    /**
     * Seluruh pengaturan dalam bentuk [key => nilai yang sudah di-cast].
     */
    public function all(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        // Saat migrasi belum jalan (mis. artisan migrate pertama kali),
        // tabel belum ada. Kembalikan array kosong alih-alih melempar galat.
        if (!Schema::hasTable('settings')) {
            return $this->memo = [];
        }

        $this->memo = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return Setting::all()
                ->mapWithKeys(fn (Setting $s) => [$s->key => Setting::castValue($s->value, $s->type)])
                ->toArray();
        });

        return $this->memo;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        if (!array_key_exists($key, $all)) {
            return $default;
        }

        $value = $all[$key];

        // Nilai kosong diperlakukan sebagai belum diisi, kecuali boolean dan angka.
        if ($value === null || $value === '') {
            return $default;
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function int(string $key, int $default = 0): int
    {
        return (int) $this->get($key, $default);
    }

    public function array(string $key, array $default = []): array
    {
        $value = $this->get($key, $default);

        return is_array($value) ? $value : $default;
    }

    /**
     * Simpan satu pengaturan. Baris dibuat bila belum ada.
     */
    public function set(string $key, mixed $value, string $type = 'string', array $attributes = []): Setting
    {
        $setting = Setting::firstOrNew(['key' => $key]);

        $setting->type = $setting->exists ? ($attributes['type'] ?? $setting->type) : $type;
        $setting->value = Setting::serializeValue($value, $setting->type);
        $setting->group = $attributes['group'] ?? $setting->group ?? 'general';
        $setting->label = $attributes['label'] ?? $setting->label;
        $setting->description = $attributes['description'] ?? $setting->description;
        $setting->is_public = $attributes['is_public'] ?? $setting->is_public ?? false;
        $setting->sort_order = $attributes['sort_order'] ?? $setting->sort_order ?? 0;
        $setting->save();

        $this->flush();

        return $setting;
    }

    /**
     * Simpan banyak pengaturan sekaligus, lalu bersihkan cache satu kali.
     */
    public function setMany(array $values, array $types = []): void
    {
        foreach ($values as $key => $value) {
            $type = $types[$key] ?? 'string';

            $setting = Setting::firstOrNew(['key' => $key]);
            $setting->type = $setting->exists ? $setting->type : $type;
            $setting->value = Setting::serializeValue($value, $setting->type);
            $setting->group = $setting->group ?? 'general';
            $setting->save();
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
        $this->flush();
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
        Cache::forget(\App\Support\MailIdentity::CACHE_KEY);
    }

    /**
     * Pengaturan yang boleh dibaca halaman publik.
     */
    public function publicSettings(): array
    {
        if (!Schema::hasTable('settings')) {
            return [];
        }

        return Setting::public()
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => Setting::castValue($s->value, $s->type)])
            ->toArray();
    }
}
