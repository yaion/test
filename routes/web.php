<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

/*Route::get('/', function () {
    return view('welcome');
});*/



//Route::get("/user/register",[\App\Http\Controllers\UserController::class,'register']);
//Route::get("/user/login",[\App\Http\Controllers\UserController::class,'login']);
//Route::get("/user/complete",[\App\Http\Controllers\UserController::class,'Complete']);

Route::middleware('authToken')->group(function () {
    Route::get("/user/index",[\App\Http\Controllers\UserController::class,'index']);
    Route::post("/user/complete",[\App\Http\Controllers\UserController::class,'complete']);
    Route::post("/user/inviteUsers",[\App\Http\Controllers\UserController::class,'inviteUsers']);
    Route::post('/task/list', [\App\Http\Controllers\TaskController::class, 'list']);
    Route::post('/task/complete', [\App\Http\Controllers\TaskController::class, 'complete']);
    Route::post('/task/addTask', [\App\Http\Controllers\TaskController::class, 'addTask']);
    Route::post('/orange/receive',[\App\Http\Controllers\OrangeController::class, 'receive']);
    Route::post('/orange/list',[\App\Http\Controllers\OrangeController::class, 'list']);
    Route::post('/orange/water',[\App\Http\Controllers\OrangeController::class, 'water']);
    Route::post('/prize/list',[\App\Http\Controllers\PrizeController::class, 'list']);
    Route::post('/prize/exchange',[\App\Http\Controllers\PrizeController::class, 'exchange']);
});

Route::post('/user/register', [\App\Http\Controllers\UserController::class, 'register']);
Route::post('/user/login', [\App\Http\Controllers\UserController::class, 'login']);
