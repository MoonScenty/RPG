<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CraftingJob extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'workshop', 'recipe_type', 'recipe_id', 'finishes_at', 'created_at'];

    protected $casts = ['finishes_at' => 'datetime', 'created_at' => 'datetime'];
}
