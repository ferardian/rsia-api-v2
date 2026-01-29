<?php

use Illuminate\Support\Facades\Route;

Route::prefix('user')->group(function ($router) {
  $router->prefix('auth')->group(function ($router) {
    $router->post('login', [\App\Http\Controllers\v2\UserAuthController::class, 'login'])->name('api.user.auth.login');
    $router->get('captcha', [\App\Http\Controllers\v2\PasswordResetController::class, 'captcha'])->name('api.user.auth.captcha');
    $router->post('forgot-password', [\App\Http\Controllers\v2\PasswordResetController::class, 'forgotPassword'])->name('api.user.auth.forgot-password');
    $router->post('change-password', [\App\Http\Controllers\v2\PasswordResetController::class, 'changePassword'])->name('api.user.auth.change-password');

    $router->middleware(['auth:aes', 'claim:role,pegawai|dokter'])->group(function ($router) {
      $router->get('logout', [\App\Http\Controllers\v2\UserAuthController::class, 'logout'])->name('api.user.auth.logout');
      $router->get('refresh', [\App\Http\Controllers\v2\UserAuthController::class, 'refresh'])->name('api.user.auth.refresh');
      $router->get('detail', [\App\Http\Controllers\v2\UserAuthController::class, 'detail'])->name('api.user.auth.detail');
    });
  });
});
