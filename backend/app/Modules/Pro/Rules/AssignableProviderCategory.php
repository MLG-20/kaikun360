<?php

namespace App\Modules\Pro\Rules;

use App\Models\User;
use App\Modules\Pro\Enums\ProviderCategoryStatus;
use App\Modules\Pro\Models\Provider;
use App\Modules\Pro\Models\ProviderCategory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Une catégorie de service est assignable si elle est VALIDE, ou si elle est
 * EN_ATTENTE et a été proposée par le prestataire courant lui-même (F5 —
 * "proposer une catégorie" : utilisable par son auteur en attendant la revue
 * back-office, invisible pour les autres prestataires tant qu'elle n'est pas
 * validée).
 */
class AssignableProviderCategory implements ValidationRule
{
    public function __construct(private readonly ?User $user) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $category = ProviderCategory::where('key', $value)->first();

        if ($category === null) {
            $fail('La catégorie sélectionnée est invalide.');

            return;
        }

        if ($category->status === ProviderCategoryStatus::VALIDE) {
            return;
        }

        $providerId = $this->user
            ? Provider::where('user_id', $this->user->id)->value('id')
            : null;

        if ($category->status === ProviderCategoryStatus::EN_ATTENTE
            && $providerId !== null
            && $category->created_by_provider_id === $providerId) {
            return;
        }

        $fail('La catégorie sélectionnée est invalide.');
    }
}
