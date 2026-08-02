<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzAnimation extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'animation1_name', 'animation1_hue', 'animation2_name', 'animation2_hue',
        'position', 'frames', 'timings',
    ];

    protected $casts = [
        'frames' => 'array',
        'timings' => 'array',
    ];
}
