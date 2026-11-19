<?php

use App\Livewire\Setup;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/setup/{token}', Setup::class)->name('setup');

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    // Replaced by the Livewire admin pages in Task 6.
    Route::get('/mail', fn () => 'Mail settings')->name('mail');
});
