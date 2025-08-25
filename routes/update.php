<?php
use Illuminate\Support\Facades\Route;

Route::get('/', 'UpdateController@update_software_index')->name('index');
Route::post('update-system', 'UpdateController@update_software')->name('update-system');

Route::fallback(function () {
    // If the request starts with 'userfood', let Nginx handle it
    if (request()->is('userfood*')) {
        abort(404); // Laravel ignores it, Nginx can serve Flutter
    }

    // Otherwise, redirect to Laravel root
    return redirect('/');
});

