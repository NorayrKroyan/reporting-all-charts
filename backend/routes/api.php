<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ReportController;

Route::get('/reports/daily-summary', [ReportController::class, 'dailySummary']);
