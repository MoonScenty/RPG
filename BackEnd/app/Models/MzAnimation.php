<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzAnimation extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'effect_name', 'display_type', 'offset_x', 'offset_y',
        'scale', 'speed', 'rotation', 'flash_timings', 'sound_timings',
    ];

    protected $casts = [
        'scale' => 'integer',
        'rotation' => 'array',
        'flash_timings' => 'array',
        'sound_timings' => 'array',
    ];
}
