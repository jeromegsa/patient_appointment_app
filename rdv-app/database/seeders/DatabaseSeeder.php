<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Service;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration pour tester l'API à la main :
 *   php artisan migrate:fresh --seed
 *
 * Le patient 0123456789 possède un rendez-vous dans chaque statut,
 * et plusieurs cas prêts pour tester R5, R6 et R8.
 */
class DatabaseSeeder extends Seeder
{
    private const PATIENT = '0123456789';

    public function run(): void
    {
        // Un rendez-vous dans chaque statut, tous dans des services ou jours différents.
        Appointment::factory()->pourPatient(self::PATIENT)->planifie()
            ->pourService(Service::PEDIATRIE)->le(now()->addDays(10)->toDateString())->create();

        Appointment::factory()->pourPatient(self::PATIENT)->confirme()
            ->pourService(Service::GYNECOLOGIE)->le(now()->addDays(20)->toDateString())->create();

        Appointment::factory()->pourPatient(self::PATIENT)->annule('Patient malade')
            ->pourService(Service::DENTISTERIE)->le(now()->addDays(15)->toDateString())->create();

        Appointment::factory()->pourPatient(self::PATIENT)->termine()->passe(7)
            ->pourService(Service::MEDECINE_GENERALE)->create();

        Appointment::factory()->pourPatient(self::PATIENT)->absent()->passe(14)
            ->pourService(Service::MEDECINE_GENERALE)->create();

        // Pour tester le tri : deux rendez-vous à la même date, créés l'un après l'autre.
        $memeJour = now()->addDays(30)->toDateString();
        Appointment::factory()->pourPatient(self::PATIENT)->planifie()
            ->pourService(Service::PEDIATRIE)->le($memeJour)->create();
        Appointment::factory()->pourPatient(self::PATIENT)->planifie()
            ->pourService(Service::DENTISTERIE)->le($memeJour)->create();

        // Pour tester R8 : un patient avec un rendez-vous confirmé aujourd'hui (terminé accepté)
        // et un confirmé demain (terminé refusé).
        Appointment::factory()->pourPatient('0111111111')->confirme()->aujourdhui()
            ->pourService(Service::PEDIATRIE)->create();
        Appointment::factory()->pourPatient('0111111111')->confirme()->demain()
            ->pourService(Service::DENTISTERIE)->create();

        // Quelques rendez-vous aléatoires pour d'autres patients (téléphones tous distincts : pas de doublon R5).
        Appointment::factory()->count(5)->create();
    }
}
