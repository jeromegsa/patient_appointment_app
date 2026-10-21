<?php

namespace Tests\Feature;

use App\AppointmentStatus;
use App\Models\Appointment;
use App\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    // Recrée la base vide avant chaque test : les tests sont indépendants.
    use RefreshDatabase;

    /** Données valides de base, que chaque test modifie sur un seul champ. */
    private function payload(array $override = []): array
    {
        return array_merge([
            'patient_phone' => '0123456789',
            'service' => 'PEDIATRIE',
            'appointment_date' => now()->addDays(5)->toDateString(),
            'slots' => 2,
        ], $override);
    }

    protected function tearDown(): void
    {
        // Remet l'horloge à l'heure réelle après les tests qui la figent.
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ---------- Prise de rendez-vous ----------

    public function test_creation_valide_renvoie_201_et_statut_planifie(): void
    {
        $this->postJson('/api/appointments', $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', 'planifie')
            ->assertJsonStructure(['id']);
    }

    // R1 : téléphone invalide (trop court, mauvais préfixe, lettres, trop long).
    public function test_r1_telephone_invalide(): void
    {
        foreach (['012345678', '0223456789', '01234567a9', '01234567890', ''] as $phone) {
            $this->postJson('/api/appointments', $this->payload(['patient_phone' => $phone]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('patient_phone');
        }
    }

    // R2 : service hors liste.
    public function test_r2_service_inconnu(): void
    {
        $this->postJson('/api/appointments', $this->payload(['service' => 'CHIRURGIE']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('service');
    }

    // R3 : bornes incluses (aujourd'hui et +60 jours acceptés, hier et +61 refusés).
    public function test_r3_bornes_de_date(): void
    {
        $this->postJson('/api/appointments', $this->payload(['appointment_date' => now()->toDateString()]))
            ->assertCreated();

        // Téléphone différent pour ne pas déclencher R5 entre les cas.
        $this->postJson('/api/appointments', $this->payload([
            'patient_phone' => '0111111111',
            'appointment_date' => now()->addDays(60)->toDateString(),
        ]))->assertCreated();

        $this->postJson('/api/appointments', $this->payload(['appointment_date' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('appointment_date');

        $this->postJson('/api/appointments', $this->payload(['appointment_date' => now()->addDays(61)->toDateString()]))
            ->assertUnprocessable()->assertJsonValidationErrors('appointment_date');
    }

    // R3 : formats et dates inexistantes.
    public function test_r3_date_invalide(): void
    {
        foreach (['2026-02-30', '25/10/2026', '2026-13-01', 'demain', '2026-1-5'] as $date) {
            $this->postJson('/api/appointments', $this->payload(['appointment_date' => $date]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('appointment_date');
        }
    }

    // R4 : créneaux de 1 à 3, entiers.
    public function test_r4_creneaux(): void
    {
        foreach ([0, 4, -1, 'deux', 1.5] as $slots) {
            $this->postJson('/api/appointments', $this->payload(['slots' => $slots]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors('slots');
        }

        foreach ([1, 3] as $i => $slots) {
            $this->postJson('/api/appointments', $this->payload([
                'slots' => $slots,
                'service' => $i === 0 ? 'PEDIATRIE' : 'DENTISTERIE', // évite R5
            ]))->assertCreated();
        }
    }

    // ---------- R5 : unicité ----------

    public function test_r5_doublon_actif_renvoie_409(): void
    {
        $this->postJson('/api/appointments', $this->payload())->assertCreated();

        $this->postJson('/api/appointments', $this->payload())
            ->assertStatus(409)
            ->assertJsonStructure(['message']);
    }

    public function test_r5_autre_service_ou_autre_jour_ou_autre_patient_est_accepte(): void
    {
        $this->postJson('/api/appointments', $this->payload())->assertCreated();

        $this->postJson('/api/appointments', $this->payload(['service' => 'DENTISTERIE']))->assertCreated();
        $this->postJson('/api/appointments', $this->payload(['appointment_date' => now()->addDays(6)->toDateString()]))->assertCreated();
        $this->postJson('/api/appointments', $this->payload(['patient_phone' => '0199999999']))->assertCreated();
    }

    // Un rendez-vous annulé ne bloque plus le créneau.
    public function test_r5_un_rdv_annule_ne_bloque_pas(): void
    {
        Appointment::factory()->create([
            'patient_phone' => '0123456789',
            'appointment_date' => now()->addDays(5)->toDateString(),
            'status' => AppointmentStatus::ANNULE,
        ]);

        $this->postJson('/api/appointments', $this->payload())->assertCreated();
    }

    // ---------- Liste ----------

    public function test_liste_triee_par_date_desc_puis_creation_desc(): void
    {
        $phone = '0123456789';
        $ancien = Appointment::factory()->create(['patient_phone' => $phone, 'appointment_date' => now()->addDays(10)->toDateString(), 'service' => Service::PEDIATRIE]);
        $recent = Appointment::factory()->create(['patient_phone' => $phone, 'appointment_date' => now()->addDays(10)->toDateString(), 'service' => Service::DENTISTERIE]);
        $loin = Appointment::factory()->create(['patient_phone' => $phone, 'appointment_date' => now()->addDays(20)->toDateString()]);
        $proche = Appointment::factory()->create(['patient_phone' => $phone, 'appointment_date' => now()->addDays(2)->toDateString()]);
        Appointment::factory()->create(['patient_phone' => '0100000000']); // autre patient : exclu

        $ids = $this->getJson("/api/patients/{$phone}/appointments")
            ->assertOk()
            ->json('*.id');

        // Date décroissante ; à date égale, le plus récemment créé d'abord.
        $this->assertSame([$loin->id, $recent->id, $ancien->id, $proche->id], $ids);
    }

    public function test_liste_filtre_par_statut(): void
    {
        $phone = '0123456789';
        Appointment::factory()->create(['patient_phone' => $phone, 'status' => AppointmentStatus::PLANIFIE]);
        Appointment::factory()->create(['patient_phone' => $phone, 'status' => AppointmentStatus::CONFIRME, 'appointment_date' => now()->addDays(6)->toDateString()]);

        $this->getJson("/api/patients/{$phone}/appointments?status=confirme")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.status', 'confirme');
    }

    public function test_liste_telephone_ou_statut_invalide_renvoie_422(): void
    {
        $this->getJson('/api/patients/123/appointments')->assertUnprocessable();
        $this->getJson('/api/patients/0123456789/appointments?status=nimporte')->assertUnprocessable();
    }

    public function test_liste_patient_sans_rdv_renvoie_liste_vide(): void
    {
        $this->getJson('/api/patients/0123456789/appointments')->assertOk()->assertExactJson([]);
    }

    // ---------- R6 : cycle de vie ----------

    private function changer(Appointment $rdv, array $body)
    {
        return $this->patchJson("/api/appointments/{$rdv->id}/status", $body);
    }

    public function test_r6_transitions_autorisees(): void
    {
        $rdv = Appointment::factory()->create();
        $this->changer($rdv, ['status' => 'confirme'])->assertOk()->assertJsonPath('status', 'confirme');

        $annulable = Appointment::factory()->create();
        $this->changer($annulable, ['status' => 'annule', 'cancel_reason' => 'Patient indisponible'])->assertOk();

        $confirmeAnnulable = Appointment::factory()->create(['status' => AppointmentStatus::CONFIRME]);
        $this->changer($confirmeAnnulable, ['status' => 'annule', 'cancel_reason' => 'Patient indisponible'])->assertOk();
    }

    public function test_r6_transitions_interdites_renvoient_409(): void
    {
        // planifié ne peut pas aller directement à terminé ou absent.
        $planifie = Appointment::factory()->create(['appointment_date' => now()->toDateString()]);
        $this->changer($planifie, ['status' => 'termine'])->assertStatus(409);
        $this->changer($planifie, ['status' => 'absent'])->assertStatus(409);

        // Pas de retour en arrière.
        $confirme = Appointment::factory()->create(['status' => AppointmentStatus::CONFIRME]);
        $this->changer($confirme, ['status' => 'planifie'])->assertStatus(409);

        // Même statut : transition non prévue.
        $this->changer($confirme, ['status' => 'confirme'])->assertStatus(409);
    }

    public function test_r6_etats_finaux_ne_changent_plus(): void
    {
        foreach ([AppointmentStatus::TERMINE, AppointmentStatus::ABSENT, AppointmentStatus::ANNULE] as $final) {
            $rdv = Appointment::factory()->create(['status' => $final, 'appointment_date' => now()->toDateString()]);

            foreach (['planifie', 'confirme', 'termine', 'absent'] as $target) {
                $this->changer($rdv, ['status' => $target])->assertStatus(409);
            }
            $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => 'Motif valide'])->assertStatus(409);
        }
    }

    // ---------- R7 : motif d'annulation ----------

    public function test_r7_motif_obligatoire_et_borne(): void
    {
        $rdv = Appointment::factory()->create();

        // Absent, vide, que des espaces, trop court (4 après trim) : 422.
        $this->changer($rdv, ['status' => 'annule'])->assertUnprocessable()->assertJsonValidationErrors('cancel_reason');
        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => ''])->assertUnprocessable();
        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => '        '])->assertUnprocessable();
        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => '  abcd  '])->assertUnprocessable();

        // Trop long (201) : 422.
        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => str_repeat('a', 201)])->assertUnprocessable();

        // Aucun des essais ci-dessus n'a modifié le rendez-vous.
        $this->assertSame(AppointmentStatus::PLANIFIE, $rdv->fresh()->status);

        // 5 caractères après trim : accepté, et le motif est enregistré sans espaces.
        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => '  abcde  '])
            ->assertOk()
            ->assertJsonPath('cancel_reason', 'abcde');
    }

    public function test_r7_motif_de_200_caracteres_accepte(): void
    {
        $rdv = Appointment::factory()->create();

        $this->changer($rdv, ['status' => 'annule', 'cancel_reason' => str_repeat('a', 200)])->assertOk();
    }

    // ---------- R8 : jour J pour terminé / absent ----------

    public function test_r8_termine_refuse_avant_le_jour_du_rdv(): void
    {
        $rdv = Appointment::factory()->create([
            'status' => AppointmentStatus::CONFIRME,
            'appointment_date' => now()->addDay()->toDateString(),
        ]);

        $this->changer($rdv, ['status' => 'termine'])->assertStatus(409);
        $this->changer($rdv, ['status' => 'absent'])->assertStatus(409);
    }

    public function test_r8_termine_ou_absent_accepte_le_jour_du_rdv_et_apres(): void
    {
        foreach ([0, -3] as $decalage) {
            foreach (['termine', 'absent'] as $statut) {
                $rdv = Appointment::factory()->create([
                    'status' => AppointmentStatus::CONFIRME,
                    'appointment_date' => now()->addDays($decalage)->toDateString(),
                ]);

                $this->changer($rdv, ['status' => $statut])->assertOk();
            }
        }
    }

    // La date du jour s'entend à l'heure du Bénin (UTC+1) : 23h30 UTC le 24 = 00h30 le 25 au Bénin.
    public function test_r8_utilise_l_heure_du_benin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 23:30:00', 'UTC'));

        $rdv = Appointment::factory()->create([
            'status' => AppointmentStatus::CONFIRME,
            'appointment_date' => '2026-10-25',
        ]);

        $this->changer($rdv, ['status' => 'termine'])->assertOk();
    }

    // Contre-exemple : 22h30 UTC le 24 = 23h30 le 24 au Bénin, le rdv du 25 est encore dans le futur.
    public function test_r8_refuse_la_veille_a_l_heure_du_benin(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-24 22:30:00', 'UTC'));

        $rdv = Appointment::factory()->create([
            'status' => AppointmentStatus::CONFIRME,
            'appointment_date' => '2026-10-25',
        ]);

        $this->changer($rdv, ['status' => 'termine'])->assertStatus(409);
    }

    // ---------- R9 : 404, 422, 409 distincts ----------

    public function test_r9_ressource_inexistante_renvoie_404(): void
    {
        $this->patchJson('/api/appointments/999/status', ['status' => 'confirme'])
            ->assertNotFound()
            ->assertJsonStructure(['message']);

        $this->patchJson('/api/appointments/abc/status', ['status' => 'confirme'])->assertNotFound();
    }

    public function test_r9_statut_inconnu_renvoie_422(): void
    {
        $rdv = Appointment::factory()->create();

        $this->changer($rdv, ['status' => 'nimporte'])->assertUnprocessable();
        $this->changer($rdv, [])->assertUnprocessable();
    }
}
