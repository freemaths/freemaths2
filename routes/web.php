<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('react_ajax/csrf', 'ReactAuthController@csrf');
Route::post('react_ajax/login', 'ReactAuthController@login');
Route::get('react_ajax/logout', 'ReactAuthController@logout');
Route::get('react_ajax/help/{topic}', 'ReactAuthController@ajax_help');

Route::post('react_ajax/stats', 'ReactController@ajax_stats');
Route::post('react_ajax/import', 'ReactController@ajax_import');
Route::get('react_ajax/help_list', 'ReactController@ajax_help_list');
Route::post('react_ajax/tests', 'ReactController@ajax_tests');
Route::post('react_ajax/import', 'ReactController@ajax_import');
Route::post('react_ajax/testQs', 'ReactController@ajax_testQs');
Route::post('react_ajax/log_event', 'ReactController@ajax_log_event');

Auth::routes();
Route::get('/home', 'HomeController@index')->name('home');

