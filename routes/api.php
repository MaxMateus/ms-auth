<?php


use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

Route::prefix('auth')->middleware(['validate.json'])->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh']);
});


// Rota protegida (exemplo)
Route::middleware('auth:api')->get('/me', function (Request $request) {
    return $request->user();
});
