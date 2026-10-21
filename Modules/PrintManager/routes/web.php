<?php

use Illuminate\Support\Facades\Route;
use Modules\PrintManager\Http\Controllers\PrintController;
use Modules\PrintManager\Http\Controllers\PrintManagerController;
use Modules\PrintManager\Http\Controllers\PrinterSettingController;
use Modules\PrintManager\Http\Controllers\PrinterTestController;
use Modules\PrintManager\Http\Controllers\QzTraySecurityController;

Route::middleware(['auth', 'verified'])->prefix('print')->name('printmanager.')->group(function () {
    Route::get('/', [PrintManagerController::class, 'index'])
        ->name('index')
        ->middleware('permission:print_manager-dashboard');

    Route::middleware('permission:print_manager-settings')->group(function () {
        Route::get('settings', [PrinterSettingController::class, 'index'])->name('settings.index');
        Route::get('settings/data', [PrinterSettingController::class, 'show'])->name('settings.show');
        Route::post('settings', [PrinterSettingController::class, 'store'])->name('settings.store');
    });

    Route::get('printers', [PrinterSettingController::class, 'list'])
        ->name('printers.index')
        ->middleware('permission:print_manager-printers');

    Route::middleware('permission:print_manager-test')->group(function () {
        Route::get('test', [PrinterTestController::class, 'index'])->name('test.index');
        Route::post('test', [PrinterTestController::class, 'test'])->name('test.run');
    });

    // POS / runtime print endpoints — keep auth-only (no print_manager gate)
    Route::post('receipt', [PrintController::class, 'printReceipt'])->name('print.receipt');
    Route::post('html', [PrintController::class, 'printHtml'])->name('print.html');
    Route::post('raw', [PrintController::class, 'printRaw'])->name('print.raw');
    Route::post('pdf', [PrintController::class, 'printPdf'])->name('print.pdf');
    Route::post('log', [PrintController::class, 'updateLog'])->name('print.log');

    Route::get('qz/certificate', [QzTraySecurityController::class, 'certificate'])->name('qz.certificate');
    Route::post('qz/sign', [QzTraySecurityController::class, 'sign'])->name('qz.sign');
    Route::get('qz/status', [QzTraySecurityController::class, 'status'])->name('qz.status');
});
