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

/*
Route::get('/hello', function () {
    //return view('welcome');
    return "<h1>Hello World</h1>";
});

Route::get('/users/{id}/{name}',function($id,$name){
    return 'This is a user '.$name.' with id:'.$id;
});

*/


Route::get('/', 'PagesController@index');

Route::get('/about','PagesController@about');
Route::get('/services','PagesController@services');


//for the controller controlling migrations
//run php artisan route:list to look for the changes
Route::resource('posts','PostsController');

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');
