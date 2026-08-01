<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzEnemy extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'battler_name', 'note', 'mhp', 'mmp', 'atk', 'def', 'mat', 'mdf', 'agi', 'luk', 'actions', 'traits',
    ];

    protected $casts = ['actions' => 'array', 'traits' => 'array'];
}
