<?php

namespace App\Services;

use App\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Contient les règles de gestion des rendez-vous.
 * Le contrôleur ne fait que passer les données validées à cette classe.
 */
class AppointmentService
{
    // R8 : "aujourd'hui" se calcule toujours à l'heure du Bénin (UTC+1), quel que soit le fuseau du serveur.
    private const TIMEZONE = 'Africa/Porto-Novo';

    /**
     * Prend un rendez-vous (R5). Le statut initial est "planifié".
     *
     * @param  array{patient_phone: string, service: string, appointment_date: string, slots: int}  $data
     */
    public function create(array $data): Appointment
    {
        // La transaction rend la vérification et l'insertion atomiques.
        return DB::transaction(function () use ($data) {
            // R5 : un patient ne peut pas avoir deux rendez-vous actifs
            // (planifié ou confirmé) dans le même service le même jour.
            // Les rendez-vous annulés, terminés ou absents ne bloquent pas.
            $doublon = Appointment::query()
                ->where('patient_phone', $data['patient_phone'])
                ->where('service', $data['service'])
                ->where('appointment_date', $data['appointment_date'])
                ->whereIn('status', [
                    AppointmentStatus::PLANIFIE->value,
                    AppointmentStatus::CONFIRME->value,
                ])
                ->exists();

            if ($doublon) {
                throw new BusinessRuleException(
                    'Ce patient a déjà un rendez-vous actif dans ce service à cette date.'
                );
            }

            return Appointment::create([
                ...$data,
                'status' => AppointmentStatus::PLANIFIE,
            ]);
        });
    }

    /**
     * Fait évoluer le statut d'un rendez-vous (R6, R7, R8).
     * Le format du motif (R7) est déjà validé par la FormRequest.
     */
    public function changeStatus(
        Appointment $appointment,
        AppointmentStatus $target,
        ?string $reason = null
    ): Appointment {
        $current = $appointment->status;

        // R6 : seules les transitions prévues par l'enum sont permises.
        if (! $current->canBecome($target)) {
            // Message distinct pour un état final, plus clair pour l'utilisateur.
            throw new BusinessRuleException(
                $current->allowedTransitions() === []
                    ? "Ce rendez-vous est {$current->value} : son statut ne peut plus changer."
                    : "Transition interdite : un rendez-vous {$current->value} ne peut pas passer à {$target->value}."
            );
        }

        // R8 : terminé ou absent ne se posent qu'à partir du jour du rendez-vous (jour inclus).
        if (in_array($target, [AppointmentStatus::TERMINE, AppointmentStatus::ABSENT], true)) {
            // On compare des chaînes AAAA-MM-JJ : l'ordre alphabétique suit l'ordre chronologique.
            $today = Carbon::now(self::TIMEZONE)->toDateString();

            if (Carbon::parse($appointment->appointment_date)->toDateString() > $today) {
                throw new BusinessRuleException(
                    "Impossible de marquer ce rendez-vous {$target->value} avant le jour du rendez-vous."
                );
            }
        }

        $appointment->status = $target;
        // Le motif n'est conservé que pour une annulation.
        $appointment->cancel_reason = $target === AppointmentStatus::ANNULE ? $reason : null;
        $appointment->save();

        return $appointment;
    }
}
