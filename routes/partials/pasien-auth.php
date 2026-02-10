<?php

use Illuminate\Support\Facades\Route;

Route::prefix('pasien')->group(function ($router) {
  $router->prefix('auth')->group(function ($router) {
    $router->post('login', [\App\Http\Controllers\v2\PasienAuthController::class, 'login']);
    
    // Self Registration
    $router->prefix('register')->middleware(['throttle:registration'])->group(function ($router) {
      $router->post('cek-nik', [\App\Http\Controllers\v2\PasienRegistrationController::class, 'cekNik']);
      $router->post('send-otp', [\App\Http\Controllers\v2\PasienRegistrationController::class, 'sendOtp']);
      $router->post('verify-otp', [\App\Http\Controllers\v2\PasienRegistrationController::class, 'verifyOtp']);
      $router->post('/', [\App\Http\Controllers\v2\PasienRegistrationController::class, 'register']);
    });
    
    $router->middleware(['auth:pasien', 'claim:role,pasien'])->group(function ($router) {
      $router->get('logout', [\App\Http\Controllers\v2\PasienAuthController::class, 'logout']);
      $router->get('refresh', [\App\Http\Controllers\v2\PasienAuthController::class, 'refresh']);
      $router->get('detail', [\App\Http\Controllers\v2\PasienAuthController::class, 'detail']);
    });
  });
});
