<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzActor extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'class_id', 'character_name', 'character_index', 'face_name', 'face_index',
        'hud_face_name', 'battler_name', 'initial_level', 'max_level', 'nickname', 'note', 'profile',
        'equips', 'traits',
    ];

    protected $casts = ['equips' => 'array', 'traits' => 'array'];
}
