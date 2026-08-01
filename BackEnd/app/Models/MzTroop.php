<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzTroop extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'name'];

    public function members()
    {
        return $this->hasMany(MzTroopMember::class, 'troop_id')->orderBy('position');
    }
}
