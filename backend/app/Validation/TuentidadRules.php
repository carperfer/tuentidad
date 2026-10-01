<?php

namespace App\Validation;

use DateTimeImmutable;

/**
 * Reglas de validación propias de tuentidad.
 */
class TuentidadRules
{
    /**
     * Fecha de nacimiento (Y-m-d) de alguien con la edad mínima configurada
     * y una edad máxima razonable.
     */
    public function valid_birthdate(mixed $value, ?string &$error = null): bool
    {
        $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
        if ($date === false || $date->format('Y-m-d') !== $value) {
            $error = 'La fecha de nacimiento no es válida.';

            return false;
        }

        $age     = $date->diff(new DateTimeImmutable('today'))->y;
        $minimum = config('Tuentidad')->minimumAge;

        if ($date > new DateTimeImmutable('today') || $age > 120) {
            $error = 'La fecha de nacimiento no es válida.';

            return false;
        }

        if ($age < $minimum) {
            $error = "Tienes que tener al menos {$minimum} años para usar tuentidad.";

            return false;
        }

        return true;
    }
}
