<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OrsOmes\RequestLog\Http\Controllers\ViewerController;

Route::get('/', [ViewerController::class, 'index'])->name('index');
Route::get('{entry}', [ViewerController::class, 'show'])->name('show');
Route::get('{entry}/markdown', [ViewerController::class, 'markdown'])->name('markdown');
