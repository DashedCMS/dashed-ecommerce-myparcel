<?php

use Dashed\DashedEcommerceMyParcel\Controllers\MyParcelController;
use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\AdminMiddleware;

Route::middleware(['web', AdminMiddleware::class])->prefix('dashed/myparcel')->group(function () {
    Route::get('/download-labels', [MyParcelController::class, 'downloadLabels'])->name('dashed.myparcel.download-labels');
});
