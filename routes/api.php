<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\WithdraalsController;
use App\Http\Controllers\ClientsContoller;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/



Route::prefix('products')->group(function(){
    Route::get('/',[ProductsController::class, 'index']);
    Route::get('/reply',[ProductsController::class, 'reply']);
    Route::get('/products',[ProductsController::class, 'products']);
    Route::get('/replace',[ProductsController::class, 'replace']);
    Route::get('/minmax',[ProductsController::class, 'minmax']);
});

Route::prefix('withdrawals')->group(function(){
    Route::get('/',[WithdraalsController::class,'replywithdrawals']);
    Route::get('/index',[WithdraalsController::class,'index']);
});

Route::prefix('clients')->group(function(){
    Route::get('/c',[ClientsContoller::class,'index']);
});