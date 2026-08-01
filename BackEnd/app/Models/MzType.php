<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzType extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'elements', 'skill_types', 'weapon_types', 'armor_types', 'equip_types'];
    protected $casts = [
        'elements' => 'array', 'skill_types' => 'array', 'weapon_types' => 'array',
        'armor_types' => 'array', 'equip_types' => 'array',
    ];

    /** 항상 id=1 한 행만 존재하는 전역 설정(mz_system_audio와 동일 패턴). */
    public static function current(): ?self
    {
        return self::find(1);
    }
}
