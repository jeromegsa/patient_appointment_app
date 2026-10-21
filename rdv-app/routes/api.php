<?php

use App\Http\Controllers\AppointmentController;
use Illuminate\Support\Facades\Route;

// Toutes les routes de ce fichier sont automatiquement préfixées par /api.

// Prendre un rendez-vous.
Route::post('/appointments', [AppointmentController::class, 'store']);

// Lister les rendez-vous d'un patient (filtre facultatif : ?status=).
Route::get('/patients/{phone}/appointments', [AppointmentController::class, 'index']);

// Changer le statut. whereNumber : un id non numérique donne directement un 404.
Route::patch('/appointments/{appointment}/status', [AppointmentController::class, 'updateStatus'])
    ->whereNumber('appointment');
