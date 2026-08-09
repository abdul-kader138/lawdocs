<?php

use App\Http\Controllers\DownloadDocumentRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['web', 'auth'])
    ->get('/document-requests/{documentRequest}/download', DownloadDocumentRequestController::class)
    ->name('document-requests.download');
