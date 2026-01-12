<?php

use App\Http\Controllers\API\V1\DailyHealthSummaryController;
use App\Http\Controllers\API\V1\UserGoalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');


Route::post('/daily-summary', [DailyHealthSummaryController::class, 'store']);
Route::get('/weekly-summary', [DailyHealthSummaryController::class, 'weeklySummary']);

Route::prefix('goal')->group(function () {
    Route::post('/', [UserGoalController::class, 'store']);
    Route::get('/', [UserGoalController::class, 'index']);
});
