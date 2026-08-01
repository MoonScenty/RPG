<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'mz_item_id', 'quantity'];

    public function item()
    {
        return $this->belongsTo(MzItem::class, 'mz_item_id');
    }
}
