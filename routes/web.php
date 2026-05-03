<?php

use Alihaiderx\LaravelSpool\Controllers\TestController;
use Alihaiderx\LaravelSpool\Middleware\EnsureDevEnv;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureDevEnv::class])->group(function () {
  Route::get('/spool/test/debug', [TestController::class, 'debugRequest']);
});
