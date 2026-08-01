<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormationSlot extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'preset_number', 'slot', 'unit_id'];
}
