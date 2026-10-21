<?php

use Illuminate\Support\Facades\Route;
use Modules\PrintManager\Http\Controllers\PrintController;
use Modules\PrintManager\Http\Controllers\PrinterSettingController;
use Modules\PrintManager\Http\Controllers\PrinterTestController;

Route::middleware(['auth:sanctum'])->prefix('print')->name('printmanager.')->group(function () {
    Route::get('settings', [PrinterSettingController::class, 'show'])->name('settings.show');
    Route::post('settings', [PrinterSettingController::class, 'store'])->name('settings.store');
    Route::post('test', [PrinterTestController::class, 'test'])->name('test.run');
    Route::post('receipt', [PrintController::class, 'printReceipt'])->name('print.receipt');
    Route::post('html', [PrintController::class, 'printHtml'])->name('print.html');
    Route::post('raw', [PrintController::class, 'printRaw'])->name('print.raw');
    Route::post('pdf', [PrintController::class, 'printPdf'])->name('print.pdf');
    Route::post('log', [PrintController::class, 'updateLog'])->name('print.log');
});
