<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzItem extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'item_type_id', 'consumable', 'price', 'icon_index', 'animation_id',
        'scope', 'occasion', 'hit_type', 'speed', 'success_rate', 'repeats',
        'damage_type', 'damage_formula', 'variance', 'element_id', 'description', 'note',
        'crafting_cost', 'crafting_time', 'effects', 'tags',
    ];

    protected $casts = ['consumable' => 'boolean', 'effects' => 'array', 'tags' => 'array'];

    /** scope 코드(MZ 표준) - 이 프로젝트가 만든 아이템은 전부 7(1 아군) 아니면 11(자신). */
    public function targetSide(): string
    {
        return $this->scope === 11 ? 'self' : 'ally';
    }
}
