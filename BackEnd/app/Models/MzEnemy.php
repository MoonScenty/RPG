<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzEnemy extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'note', 'hp', 'mp', 'str', 'vit', 'mnd', 'dex', 'agi', 'luk', 'int',
        'reward_exp', 'reward_gold', 'drops', 'actions', 'image_type', 'image', 'face_name',
        'attack_animation_id', 'scale', 'motion_map', 'traits',
    ];

    protected $casts = ['actions' => 'array', 'traits' => 'array'];
}
