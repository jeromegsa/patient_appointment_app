<?php

namespace App;

enum AppointmentStatus: string
{
    case PLANIFIE = 'planifie';
    case CONFIRME = 'confirme';
    case ANNULE = 'annule';
    case TERMINE = 'termine';
    case ABSENT = 'absent';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::PLANIFIE => [self::CONFIRME, self::ANNULE],
            self::CONFIRME => [self::TERMINE, self::ABSENT, self::ANNULE],
            default => [],
        };
    }

    public function canBecome(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function isActive(): bool
    {
        return in_array($this, [self::PLANIFIE, self::CONFIRME], true);
    }
}
