<?php

use Illuminate\Support\Facades\Route;

Route::view('/docs', 'docs')->name('docs');

Route::get('/', function () {
    return view('welcome');
});
