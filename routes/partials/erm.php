<?php

use Illuminate\Support\Facades\Route;

// Route untuk mengambil detail ERM berdasarkan no_rawat
Route::get('/erm/details/{no_sep}', [\App\Http\Controllers\v2\ErmController::class, 'showDetails']);

// FHIR ValueSet expansion endpoint - publicly accessible for SNOMED searches
// Support both escaped and unescaped $ for compatibility
Route::get('/fhir/ValueSet/$expand', [\App\Http\Controllers\v2\CodingCasemixController::class, 'expandValueSet'])
    ->withoutMiddleware(['auth', 'auth:api', 'claim', 'user-aes']);

Route::get('/fhir/ValueSet/\$expand', [\App\Http\Controllers\v2\CodingCasemixController::class, 'expandValueSet'])
    ->withoutMiddleware(['auth', 'auth:api', 'claim', 'user-aes']);

// NEW: Coding Casemix routes
Route::prefix('casemix')->group(function () {
    Route::get('/queue-by-patient', [\App\Http\Controllers\v2\CodingCasemixController::class, 'getQueueByPatient']);
    Route::get('/visit/{no_sep}', [\App\Http\Controllers\v2\CodingCasemixController::class, 'getDetailForCoding']);
    Route::post('/coding', [\App\Http\Controllers\v2\CodingCasemixController::class, 'saveCoding']);
    Route::post('/mapping/add', [\App\Http\Controllers\v2\CodingCasemixController::class, 'addSingleMapping']);

    // Move SNOMED search inside casemix group (karena perlu auth)
    Route::post('/snomed/search', [\App\Http\Controllers\v2\CodingCasemixController::class, 'searchSnomed']);
});

?>