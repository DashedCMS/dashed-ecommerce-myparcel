<?php

use Illuminate\Support\Facades\Route;
use Dashed\DashedCore\Middleware\AdminMiddleware;
use Dashed\DashedEcommerceMyParcel\Controllers\MyParcelController;

Route::middleware(['web', AdminMiddleware::class])->prefix('dashed/my-parcel')->group(function () {
    Route::get('/download-labels', [MyParcelController::class, 'downloadLabels'])->name('dashed.my-parcel.download-labels');
});
