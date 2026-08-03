<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzState extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'note', 'icon_index', 'priority', 'motion',
        'remove_at_battle_end', 'remove_by_restriction', 'auto_removal_timing', 'min_turns', 'max_turns',
        'remove_by_damage', 'remove_by_damage_chance',
        'message_when_added', 'message_when_added_enemy', 'message_while_active', 'message_when_removed',
        'traits', 'tags', 'is_debuff',
    ];

    protected $casts = [
        'tags' => 'array', 'traits' => 'array',
        'auto_removal_timing' => 'integer', 'min_turns' => 'integer', 'max_turns' => 'integer',
        'motion' => 'integer', 'priority' => 'integer', 'icon_index' => 'integer',
        'remove_at_battle_end' => 'boolean', 'remove_by_restriction' => 'boolean',
        'remove_by_damage' => 'boolean', 'remove_by_damage_chance' => 'integer',
        'is_debuff' => 'boolean',
    ];
}
