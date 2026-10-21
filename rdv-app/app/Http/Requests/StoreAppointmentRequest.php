<?php

namespace App\Http\Requests;

use App\Service;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide la prise d'un rendez-vous (règles R1, R2, R3, R4).
 */
class StoreAppointmentRequest extends FormRequest
{
    // Pas d'authentification dans ce projet : toute requête est autorisée.
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // R1 : exactement 10 chiffres, commençant par 01.
            // \z marque la fin stricte de la chaîne (contrairement à $, qui tolère un retour à la ligne final).
            'patient_phone' => ['required', 'string', 'regex:/^01\d{8}\z/'],

            // R2 : uniquement les valeurs de l'enum Service.
            'service' => ['required', Rule::enum(Service::class)],

            'appointment_date' => [
                'required',
                // R3 : format strict AAAA-MM-JJ (rejette aussi les dates inexistantes comme 2026-02-30).
                'date_format:Y-m-d',
                // R3 : borne basse, aujourd'hui inclus.
                // "today" suit le fuseau APP_TIMEZONE (Africa/Porto-Novo).
                'after_or_equal:today',
                // R3 : borne haute, aujourd'hui + 60 jours inclus.
                'before_or_equal:' . now()->addDays(60)->toDateString(),
            ],

            // R4 : entier de 1 à 3 (un créneau = 30 minutes).
            'slots' => ['required', 'integer', 'between:1,3'],
        ];
    }

    // Messages d'erreur lisibles renvoyés au client (R9).
    public function messages(): array
    {
        return [
            'patient_phone.regex' => 'Le téléphone doit contenir exactement 10 chiffres et commencer par 01.',
            'service.enum' => 'Le service doit être MEDECINE_GENERALE, PEDIATRIE, GYNECOLOGIE ou DENTISTERIE.',
            'appointment_date.date_format' => 'La date doit être au format AAAA-MM-JJ.',
            'appointment_date.after_or_equal' => 'La date du rendez-vous ne peut pas être dans le passé.',
            'appointment_date.before_or_equal' => 'La date du rendez-vous ne peut pas dépasser 60 jours.',
            'slots.between' => 'Le nombre de créneaux doit être compris entre 1 et 3.',
            'slots.integer' => 'Le nombre de créneaux doit être un entier.',
            'patient_phone.required' => 'Le téléphone est obligatoire.',
            'service.required' => 'Le service est obligatoire.',
            'appointment_date.required' => 'La date du rendez-vous est obligatoire.',
            'slots.required' => 'Le nombre de créneaux est obligatoire.',
            'status.required' => 'Le statut est obligatoire.',

        ];
    }
}
