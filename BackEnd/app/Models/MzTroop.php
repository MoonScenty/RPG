<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MzTroop extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'int';

    protected $fillable = [
        'id', 'name', 'battleback1', 'battleback2',
        'front_top_enemy_id', 'front_middle_enemy_id', 'front_bottom_enemy_id',
        'back_top_enemy_id', 'back_middle_enemy_id', 'back_bottom_enemy_id',
        'battle_bgm', 'victory_me', 'defeat_me',
    ];

    protected $casts = ['battle_bgm' => 'array', 'victory_me' => 'array', 'defeat_me' => 'array'];

    /** 슬롯 1-6 순서대로 [position => enemy_id](비어있는 슬롯은 제외) - mz_troop_members를 대신함. */
    public function memberSlots(): array
    {
        $columns = [
            1 => 'front_top_enemy_id', 2 => 'front_middle_enemy_id', 3 => 'front_bottom_enemy_id',
            4 => 'back_top_enemy_id', 5 => 'back_middle_enemy_id', 6 => 'back_bottom_enemy_id',
        ];

        $slots = [];
        foreach ($columns as $position => $column) {
            if ($this->$column !== null) {
                $slots[$position] = $this->$column;
            }
        }

        return $slots;
    }
}
