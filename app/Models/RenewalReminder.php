<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Pencatat tahap pengingat perpanjangan yang sudah terkirim per instance.
 *
 * Kombinasi (vps_instance_id, stage, period_expires_at) bersifat unik.
 * Indeks itulah yang mencegah surel ganda saat scheduler berjalan dua kali.
 */
class RenewalReminder extends Model
{
    protected $fillable = [
        'vps_instance_id',
        'stage',
        'period_expires_at',
        'sent_at',
    ];

    protected $casts = [
        'period_expires_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public const STAGE_H7 = 'h_minus_7';
    public const STAGE_H3 = 'h_minus_3';
    public const STAGE_H1 = 'h_minus_1';
    public const STAGE_H0 = 'h_zero';
    public const STAGE_GRACE_ENDED = 'grace_ended';

    public const STAGES = [
        self::STAGE_H7,
        self::STAGE_H3,
        self::STAGE_H1,
        self::STAGE_H0,
        self::STAGE_GRACE_ENDED,
    ];

    public function vpsInstance()
    {
        return $this->belongsTo(VpsInstance::class);
    }

    public static function stageLabel(string $stage): string
    {
        return match ($stage) {
            self::STAGE_H3 => 'H-3',
            self::STAGE_H1 => 'H-1',
            self::STAGE_H0 => 'Jatuh Tempo',
            self::STAGE_GRACE_ENDED => 'Tenggang Berakhir',
            default => $stage,
        };
    }
}
