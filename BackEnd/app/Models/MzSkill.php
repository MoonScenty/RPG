<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzSkill extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'class_id', 'name', 'mp_cost', 'tp_cost', 'scope', 'hit_type', 'stype_id', 'occasion',
        'damage_type', 'critical', 'damage_formula', 'variance', 'element_id', 'repeats', 'success_rate', 'tags',
    ];

    protected $casts = [
        'tags' => 'array',
        'scope' => 'integer', 'hit_type' => 'integer', 'stype_id' => 'integer', 'occasion' => 'integer', 'damage_type' => 'integer',
        'variance' => 'integer', 'element_id' => 'integer', 'repeats' => 'integer', 'success_rate' => 'integer',
        'mp_cost' => 'integer', 'tp_cost' => 'integer', 'critical' => 'boolean',
    ];

    public function mzClass()
    {
        return $this->belongsTo(MzClass::class, 'class_id');
    }

    /** scope 코드(MZ 표준)로 대상 진영을 유도 - 1=적1인, 7/9=아군1인, 11=자신, 2/8/10은 광역이지만 진영 자체는 단일 대상과 동일. */
    public function targetSide(): string
    {
        return match ($this->scope) {
            1, 2 => 'enemy',
            7, 8, 9, 10 => 'ally',
            11 => 'self',
            default => 'enemy',
        };
    }

    /** MZ 표준 광역 scope(전체 적/전체 아군/전체 아군(전투불능)) 여부. */
    public function isAoe(): bool
    {
        return in_array($this->scope, [2, 8, 10], true);
    }
}
