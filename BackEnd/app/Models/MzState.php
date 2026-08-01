<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzState extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = ['id', 'name', 'note', 'is_buff', 'auto_removal_timing', 'min_turns', 'max_turns', 'motion', 'priority', 'traits', 'tags'];

    protected $casts = [
        'is_buff' => 'boolean', 'tags' => 'array', 'traits' => 'array',
        'auto_removal_timing' => 'integer', 'min_turns' => 'integer', 'max_turns' => 'integer',
        'motion' => 'integer', 'priority' => 'integer',
    ];
}
