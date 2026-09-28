<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $spaIndexPath = public_path('index.html');
    if (file_exists($spaIndexPath)) {
        return file_get_contents($spaIndexPath);
    }
    return view('welcome');
});
