<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\OrderController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::get('items/filter', [ItemController::class, 'getItems']);
Route::get('items/all-items', [ItemController::class, 'getAllItems']);
Route::get('orders/all-orders', [OrderController::class, 'getAllOrders']);

Route::resource('shops', ShopController::class);
// Route::resource('users', UserController::class);
Route::resource('items', ItemController::class);
Route::resource('orders', OrderController::class);
