<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomFieldController;
use Illuminate\Support\Facades\Route;

Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
// Redirect root to contacts listing
Route::redirect('/', '/contacts');
Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
Route::get('/contacts/{id}', [ContactController::class, 'show'])->name('contacts.show');
Route::put('/contacts/{id}', [ContactController::class, 'update'])->name('contacts.update');
Route::delete('/contacts/{id}', [ContactController::class, 'destroy'])->name('contacts.destroy');

// Routes for managing custom fields
Route::get('/custom-fields', [CustomFieldController::class, 'index']);
Route::post('/custom-fields', [CustomFieldController::class, 'store']);
Route::delete('/custom-fields/{customField}', [CustomFieldController::class, 'destroy']);

Route::post('/contacts/merge/initiate', [ContactController::class, 'initiateMerge'])->name('contacts.merge.initiate');
Route::post('/contacts/merge/preview', [ContactController::class, 'previewMerge'])->name('contacts.merge.preview');
Route::post('/contacts/merge/perform', [ContactController::class, 'performMerge'])->name('contacts.merge.perform');
Route::get('/contacts/merge/exclude/{id}', [ContactController::class, 'getContactsForMerge'])
    ->name('contacts.merge.exclude');
