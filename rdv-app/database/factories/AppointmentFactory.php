<?php

namespace Database\Factories;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
{
    return [
        'patient_phone' => '01' . fake()->numerify('########'), // R1 : 01 + 8 chiffres
        'service' => Service::PEDIATRIE,
        'appointment_date' => now()->addDays(5)->toDateString(),
        'slots' => 1,
        'status' => AppointmentStatus::PLANIFIE,
    ];
}

}
