<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use JustinHoltWeb\Bunting\Http\Controllers\AuditController;
use JustinHoltWeb\Bunting\Http\Controllers\FlagsController;
use JustinHoltWeb\Bunting\Http\Controllers\UserFlagsController;
use JustinHoltWeb\Bunting\Plugin;

// The cp group already applies auth + can:accessCp (and CSRF via `web`).
Route::middleware('can:accessPlugin-bunting')->prefix('bunting')->group(function () {
    Route::get('', [FlagsController::class, 'index']);
    Route::get('flags/{handle}', [FlagsController::class, 'edit'])->where('handle', '[a-z][a-z0-9\-_.]{0,63}');
    Route::get('users/{userId}', [UserFlagsController::class, 'show'])->whereNumber('userId');
    Route::get('audit', AuditController::class)->middleware('can:'.Plugin::PERMISSION_AUDIT);

    Route::middleware('can:'.Plugin::PERMISSION_EDIT)->group(function () {
        Route::get('new', [FlagsController::class, 'create']);
        Route::post('flags/save', [FlagsController::class, 'save']);
        Route::post('flags/delete', [FlagsController::class, 'delete']);
        Route::post('flags/purge', [FlagsController::class, 'purge']);
        Route::post('flags/override', [FlagsController::class, 'override']);
        Route::post('users/save', [UserFlagsController::class, 'save']);
    });
});
