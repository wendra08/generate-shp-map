<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapController;

Route::get('/', [MapController::class, 'index'])->name('map.index');
Route::post('/map/load', [MapController::class, 'loadShapefile'])->name('map.load');
Route::post('/map/save-image', [MapController::class, 'saveMapImage'])->name('map.save');
Route::get('/map/download/{filename}', [MapController::class, 'downloadImage'])->name('map.download');
