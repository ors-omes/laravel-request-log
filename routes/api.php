<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use OrsOmes\RequestLog\Http\Controllers\ApiController;

Route::get('/', [ApiController::class, 'index'])->name('index');
Route::get('suggest', [ApiController::class, 'suggest'])->name('suggest');
Route::get('{entry}', [ApiController::class, 'show'])->name('show');
Route::get('{entry}/markdown', [ApiController::class, 'markdown'])->name('markdown');
