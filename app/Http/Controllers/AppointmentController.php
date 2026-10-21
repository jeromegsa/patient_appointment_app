<?php

namespace App\Http\Controllers;

use App\AppointmentStatus;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentStatusRequest;
use App\Models\Appointment;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Couche HTTP : reçoit les requêtes, délègue les règles au service, renvoie du JSON.
 */
class AppointmentController extends Controller
{
    // Laravel injecte automatiquement le service.
    public function __construct(private readonly AppointmentService $service)
    {
    }

    /**
     * POST /api/appointments : prend un rendez-vous.
     * La FormRequest a déjà validé R1 à R4 avant d'arriver ici.
     */
    public function store(StoreAppointmentRequest $request): JsonResponse
    {
        $appointment = $this->service->create($request->validated());

        // 201 Created : une ressource vient d'être créée.
        return response()->json($appointment, 201);
    }

    /**
     * GET /api/patients/{phone}/appointments?status= : liste les rendez-vous d'un patient.
     */
    public function index(Request $request, string $phone): JsonResponse
    {
        // Le téléphone vient de l'URL, la FormRequest ne le couvre pas :
        // on applique R1 ici pour qu'un numéro invalide renvoie 422 (et non une liste vide).
        // Le filtre "status" est facultatif, mais s'il est fourni il doit être un statut connu.
        Validator::make(
            ['phone' => $phone, 'status' => $request->query('status')],
            [
                'phone' => ['regex:/^01\d{8}\z/'],
                'status' => ['nullable', Rule::enum(AppointmentStatus::class)],
            ],
            [
                'phone.regex' => 'Le téléphone doit contenir exactement 10 chiffres et commencer par 01.',
                'status.enum' => 'Statut inconnu.',
            ]
        )->validate();

        $appointments = Appointment::query()
            ->where('patient_phone', $phone)
            // when() n'ajoute le filtre que si "status" est présent dans l'URL.
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            // Date du rendez-vous décroissante, puis création la plus récente d'abord.
            ->orderByDesc('appointment_date')
            ->orderByDesc('created_at')
            // id en dernier recours : deux créations dans la même seconde auraient le même created_at.
            ->orderByDesc('id')
            ->get();

        return response()->json($appointments);
    }

    /**
     * PATCH /api/appointments/{appointment}/status : fait évoluer le statut.
     * {appointment} est résolu par Laravel : id inconnu => 404 automatique.
     */
    public function updateStatus(
        UpdateAppointmentStatusRequest $request,
        Appointment $appointment
    ): JsonResponse {
        $updated = $this->service->changeStatus(
            $appointment,
            // Le statut est déjà validé : from() convertit la chaîne en enum.
            AppointmentStatus::from($request->validated('status')),
            $request->validated('cancel_reason')
        );

        return response()->json($updated);
    }
}
