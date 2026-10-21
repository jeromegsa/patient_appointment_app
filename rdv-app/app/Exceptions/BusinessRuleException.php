<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Levée quand une action est syntaxiquement valide mais interdite par une règle de gestion
 * (doublon R5, transition illégale R6, statut posé trop tôt R8).
 * On renvoie 409 pour la distinguer d'une saisie invalide (422) et d'une ressource absente (404).
 */
class BusinessRuleException extends Exception
{
    // Laravel appelle cette méthode automatiquement pour transformer l'exception en réponse HTTP.
    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage()], 409);
    }
}
