<?php

use App\Http\Controllers\SsoController;
use App\Http\Controllers\UnsubscribeController;
use App\Livewire\Admin;
use App\Livewire\Auth\Login;
use App\Livewire\Feedback;
use App\Livewire\Issues;
use App\Livewire\My;
use App\Livewire\Setup;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', Issues\Index::class)->name('home');
Route::get('/issues/{issue}', Issues\Show::class)->name('issues.show');
Route::get('/feedback', Feedback\Create::class)->name('feedback.create');
Route::get('/feedback/{feedback}', Feedback\Status::class)->where('feedback', '[A-Za-z0-9]{40}')->name('feedback.status');

Route::get('/setup/{token}', Setup::class)->name('setup');

Route::middleware('signed')->group(function () {
    Route::match(['get', 'post'], '/unsubscribe/s/{subscription}', [UnsubscribeController::class, 'subscription'])
        ->whereNumber('subscription')->name('unsubscribe.subscription');
    Route::match(['get', 'post'], '/unsubscribe/w/{watch}', [UnsubscribeController::class, 'watch'])
        ->whereNumber('watch')->name('unsubscribe.watch');
});

Route::middleware('auth')->group(function () {
    Route::get('/my', My\Issues::class)->name('my.issues');
    Route::get('/my/settings', My\Settings::class)->name('my.settings');
    Route::get('/my/feedback', Feedback\Mine::class)->name('feedback.mine');
});

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
    Route::get('/feedback', Admin\Feedback::class)->name('feedback');
});
