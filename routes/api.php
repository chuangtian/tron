<?php

use Illuminate\Http\Request;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('test', 'IndexController@index');
Route::get('generateaddress', 'IndexController@generateaddress');
Route::get('receiveERC', 'IndexController@receiveERC');
Route::get('updateBlock', 'IndexController@updateBlock');
Route::get('deletedata', 'IndexController@deletedata');