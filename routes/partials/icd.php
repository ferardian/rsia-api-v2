<?php

use Orion\Facades\Orion;
use Illuminate\Support\Facades\Route;



  Route::post('icd10_idrg', [\App\Http\Controllers\v2\DiagnosaICD10_IDRGController::class, 'index']);
  Route::post('icd9_idrg', [\App\Http\Controllers\v2\ProcedureICD9_IDRGController::class, 'index']);
  Route::post('icd10_inacbg', [\App\Http\Controllers\v2\DiagnosaICD10_InacbgController::class, 'index']);
  Route::post('icd9_inacbg', [\App\Http\Controllers\v2\ProcedureICD9_InacbgController::class, 'index']);

  
