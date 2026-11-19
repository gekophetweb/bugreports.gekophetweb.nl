<?php

use App\Http\Controllers\SsoController;
use App\Livewire\Admin;
use App\Livewire\Auth\Login;
use App\Livewire\Setup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('home');

Route::get('/setup/{token}', Setup::class)->name('setup');

Route::get('/login', Login::class)->middleware('guest')->name('login');
Route::middleware('guest')->group(function () {
    Route::get('/auth/{provider}/redirect', [SsoController::class, 'redirect'])->name('sso.redirect');
    Route::get('/auth/{provider}/callback', [SsoController::class, 'callback'])->name('sso.callback');
});
Route::post('/logout', function () {
    Auth::logout();
    session()->invalidate();
    session()->regenerateToken();

    return redirect()->route('home');
})->middleware('auth')->name('logout');

Route::middleware(['auth', 'superadmin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/mail', Admin\Mail::class)->name('mail');
    Route::get('/sign-in', Admin\SignIn::class)->name('sign-in');
    Route::get('/users', Admin\Users::class)->name('users');
    Route::get('/languages', Admin\Languages::class)->name('languages');
    Route::get('/sync', Admin\Sync::class)->name('sync');
});
