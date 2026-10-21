<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Fabrique des rendez-vous valides : sans option, on obtient un rendez-vous "planifié"
 * qui respecte R1 à R4. Les états (confirme(), annule()...) servent à créer
 * directement un rendez-vous dans un statut précis, sans passer par l'API.
 *
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            // R1 : "01" suivi de 8 chiffres aléatoires.
            'patient_phone' => '01' . fake()->numerify('########'),
            // R2 : un service au hasard parmi l'enum.
            'service' => fake()->randomElement(Service::cases()),
            // R3 : une date entre aujourd'hui et +60 jours (bornes incluses).
            'appointment_date' => now()->addDays(fake()->numberBetween(0, 60))->toDateString(),
            // R4 : 1 à 3 créneaux.
            'slots' => fake()->numberBetween(1, 3),
            'status' => AppointmentStatus::PLANIFIE,
            'cancel_reason' => null,
        ];
    }

    // ---------- États : statut ----------

    public function planifie(): static
    {
        return $this->state(['status' => AppointmentStatus::PLANIFIE]);
    }

    public function confirme(): static
    {
        return $this->state(['status' => AppointmentStatus::CONFIRME]);
    }

    // Un rendez-vous annulé a toujours un motif (R7).
    public function annule(string $motif = 'Patient indisponible'): static
    {
        return $this->state([
            'status' => AppointmentStatus::ANNULE,
            'cancel_reason' => $motif,
        ]);
    }

    public function termine(): static
    {
        return $this->state(['status' => AppointmentStatus::TERMINE]);
    }

    public function absent(): static
    {
        return $this->state(['status' => AppointmentStatus::ABSENT]);
    }

    // ---------- États : date et patient ----------

    // Rendez-vous d'aujourd'hui : utile pour tester R8 (terminé / absent le jour J).
    public function aujourdhui(): static
    {
        return $this->state(['appointment_date' => now()->toDateString()]);
    }

    // Rendez-vous de demain : "terminé" doit y être refusé (R8).
    public function demain(): static
    {
        return $this->state(['appointment_date' => now()->addDay()->toDateString()]);
    }

    // Rendez-vous passé, donc "terminé" ou "absent" y est permis (R8).
    public function passe(int $jours = 3): static
    {
        return $this->state(['appointment_date' => now()->subDays($jours)->toDateString()]);
    }

    public function pourPatient(string $telephone): static
    {
        return $this->state(['patient_phone' => $telephone]);
    }

    public function pourService(Service $service): static
    {
        return $this->state(['service' => $service]);
    }

    public function le(string $date): static
    {
        return $this->state(['appointment_date' => $date]);
    }
}
