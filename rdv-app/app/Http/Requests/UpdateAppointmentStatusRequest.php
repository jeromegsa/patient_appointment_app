<?php

namespace App\Http\Requests;

use App\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valide un changement de statut (règle R7 pour le motif d'annulation).
 * Les transitions (R6) et la règle du jour J (R8) sont vérifiées plus tard, dans la logique métier.
 */
class UpdateAppointmentStatusRequest extends FormRequest
{
    // Pas d'authentification dans ce projet : toute requête est autorisée.
    public function authorize(): bool
    {
        return true;
    }

    // S'exécute AVANT la validation : on retire les espaces de début et de fin du motif,
    // car R7 impose 5 à 200 caractères "une fois les espaces retirés".
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('cancel_reason'))) {
            $this->merge(['cancel_reason' => trim($this->input('cancel_reason'))]);
        }
    }

    public function rules(): array
    {
        return [
            // Le nouveau statut doit être une valeur de l'enum AppointmentStatus.
            'status' => ['required', Rule::enum(AppointmentStatus::class)],

            'cancel_reason' => [
                // R7 : le motif est obligatoire uniquement si on annule.
                'required_if:status,' . AppointmentStatus::ANNULE->value,
                // Pour les autres statuts, le champ peut être absent.
                'nullable',
                'string',
                // R7 : entre 5 et 200 caractères (après trim).
                'min:5',
                'max:200',
            ],
        ];
    }

    // Messages d'erreur lisibles renvoyés au client (R9).
    public function messages(): array
    {
        return [
            'status.enum' => 'Statut inconnu.',
            'cancel_reason.required_if' => "Le motif d'annulation est obligatoire.",
            'cancel_reason.min' => "Le motif d'annulation doit contenir au moins 5 caractères.",
            'cancel_reason.max' => "Le motif d'annulation ne doit pas dépasser 200 caractères.",
            'patient_phone.required' => 'Le téléphone est obligatoire.',
            'service.required' => 'Le service est obligatoire.',
            'appointment_date.required' => 'La date du rendez-vous est obligatoire.',
            'slots.required' => 'Le nombre de créneaux est obligatoire.',
            'status.required' => 'Le statut est obligatoire.',

        ];
    }
}
