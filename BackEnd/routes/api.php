<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BattleController;
use App\Http\Controllers\Api\CraftingController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\FormationController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\MercenaryController;
use App\Http\Controllers\Api\MercenaryGambitController;
use Illuminate\Support\Facades\Route;

// Actual endpoints (battles, ...) land here as the game is rebuilt on this stack.

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/mercenaries', [MercenaryController::class, 'index']);
    Route::post('/mercenaries/{id}/purchase', [MercenaryController::class, 'purchase']);

    Route::get('/items', [InventoryController::class, 'items']);
    Route::post('/items/{id}/purchase', [InventoryController::class, 'purchaseItem']);
    Route::get('/weapons', [InventoryController::class, 'weapons']);
    Route::post('/weapons/{id}/purchase', [InventoryController::class, 'purchaseWeapon']);
    Route::get('/armors', [InventoryController::class, 'armors']);
    Route::post('/armors/{id}/purchase', [InventoryController::class, 'purchaseArmor']);

    Route::get('/crafting', [CraftingController::class, 'index']);
    Route::get('/crafting/{workshop}/recipes', [CraftingController::class, 'recipes']);
    Route::post('/crafting/{workshop}/start', [CraftingController::class, 'start']);
    Route::post('/crafting/jobs/{id}/collect', [CraftingController::class, 'collect']);

    Route::get('/formation', [FormationController::class, 'show']);
    Route::put('/formation', [FormationController::class, 'update']);
    Route::put('/formation/active', [FormationController::class, 'setActive']);

    Route::get('/mercenaries/{unitId}/equipment', [EquipmentController::class, 'show']);
    Route::put('/mercenaries/{unitId}/equipment', [EquipmentController::class, 'update']);

    Route::get('/mercenaries/{unitId}/gambits/catalog', [MercenaryGambitController::class, 'catalog']);
    Route::get('/mercenaries/{unitId}/gambits', [MercenaryGambitController::class, 'show']);
    Route::put('/mercenaries/{unitId}/gambits', [MercenaryGambitController::class, 'update']);
    Route::put('/mercenaries/{unitId}/gambits/active', [MercenaryGambitController::class, 'setActive']);

    Route::get('/battle-audio', [BattleController::class, 'audio']);
    Route::get('/battle-animations', [BattleController::class, 'animations']);
    // /battles/active는 /battles/{id}보다 먼저 등록해야 한다 - 안 그러면 "active"가
    // {id}로 매칭돼서 이 라우트에 절대 안 걸린다.
    Route::get('/battles/active', [BattleController::class, 'active']);
    Route::post('/battles', [BattleController::class, 'store']);
    Route::get('/battles/{id}', [BattleController::class, 'show']);
    Route::post('/battles/{id}/turn', [BattleController::class, 'turn']);
});
