<?php

use App\Http\Controllers\V1\Auth\LoginController;
use App\Http\Controllers\V1\Auth\RegisterController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/register' , [RegisterController::class , 'handler']);
Route::post('/login' , [LoginController::class , 'handler']);