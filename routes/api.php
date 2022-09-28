<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\WithdraalsController;
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
    Route::get('/access',[ProductsController::class, 'access']);
    Route::get('/products',[ProductsController::class, 'products']);
    Route::get('/replace',[ProductsController::class, 'replace']);
    Route::get('/este',[ProductsController::class, 'este']);
});

Route::prefix('withdrawals')->group(function(){
    Route::get('/',[WithdraalsController::class,'replywithdrawals']);
    Route::get('/index',[WithdraalsController::class,'index']);
});