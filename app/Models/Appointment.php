<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    /** @use HasFactory<\Database\Factories\AppointmentFactory> */
    use HasFactory;
    
    protected $fillable = ['patient_phone', 'service', 'appointment_date', 'slots', 'status', 'cancel_reason'];

    protected function casts(): array
    {
        return [
            'service' => \App\Service::class,
            'status' => \App\AppointmentStatus::class,
            'appointment_date' => 'date:Y-m-d',
        ];
    }

}
