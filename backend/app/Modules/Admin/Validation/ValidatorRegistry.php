<?php

namespace App\Modules\Admin\Validation;

use Illuminate\Contracts\Container\Container;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Registre des validateurs de ressources du back-office (B13.2).
 *
 * Fait correspondre une clé de type (`property`, `vehicle`, `experience`,
 * `provider`) à son ResourceValidator, résolu via le conteneur pour bénéficier
 * de l'injection de dépendances (VehicleComplianceChecker,
 * ProviderValidationService…). Ajouter un type validable = ajouter sa classe ici.
 */
class ValidatorRegistry
{
    /**
     * Classes de validateurs enregistrées.
     *
     * @var list<class-string<ResourceValidator>>
     */
    private const VALIDATORS = [
        PropertyValidator::class,
        VehicleValidator::class,
        ExperienceValidator::class,
        ProviderValidator::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Tous les validateurs, indexés par clé de type.
     *
     * @return array<string, ResourceValidator>
     */
    public function all(): array
    {
        $validators = [];

        foreach (self::VALIDATORS as $class) {
            $validator = $this->container->make($class);
            $validators[$validator->type()] = $validator;
        }

        return $validators;
    }

    /**
     * Le validateur d'un type donné, ou une 404 si le type est inconnu.
     */
    public function for(string $type): ResourceValidator
    {
        return $this->all()[$type]
            ?? throw new NotFoundHttpException("Type de ressource inconnu : {$type}.");
    }

    /**
     * Clés de types validables (pour documentation/validation d'entrée).
     *
     * @return list<string>
     */
    public function types(): array
    {
        return array_keys($this->all());
    }
}
