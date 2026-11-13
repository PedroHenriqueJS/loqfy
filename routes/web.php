<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $index = public_path('spa/index.html');

    return file_exists($index)
        ? response()->file($index)
        : response('Interface not built yet. Run "npm run build" inside frontend/, or use "npm run dev".', 503);
});
