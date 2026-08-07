<?php

namespace App\Modules\Mobility\Http\Requests;

use App\Modules\Mobility\Enums\MobilityServiceType;
use App\Modules\Mobility\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Règles communes au dépôt et à la modification d'un départ programmé (F8.23).
 *
 * Les deux formulaires décrivent le MÊME objet ; seules changent l'obligation
 * des champs (tout est requis au dépôt, tout est facultatif en correction) et
 * l'autorisation. Les garder dans une classe commune évite qu'une règle métier
 * — notamment l'appartenance du véhicule — soit corrigée d'un côté seulement.
 */
abstract class MobilityServiceRequest extends FormRequest
{
    /**
     * Le socle de règles, décliné en « requis » ou « facultatif » par l'enfant.
     *
     * @param  'required'|'sometimes'  $presence
     * @return array<string, mixed>
     */
    protected function champsCommuns(string $presence): array
    {
        return [
            'type' => [$presence, Rule::in(MobilityServiceType::values())],
            'departure' => [$presence, 'string', 'max:255'],
            'destination' => [$presence, 'string', 'max:255'],

            // ⚠️ Un départ programmé se vend PAR AVANCE : une date passée
            // produirait une ligne de catalogue que personne ne peut réserver
            // (la fiche affiche « ce départ a déjà eu lieu ») et que le
            // prestataire croirait pourtant en ligne.
            'departure_at' => [$presence, 'date', 'after:now'],

            'capacity' => [$presence, 'integer', 'min:1', 'max:200'],
            'price_xof' => [$presence, 'integer', 'min:0'],
            'description' => ['nullable', 'string', 'max:5000'],

            // Véhicule opérant le départ : facultatif, mais s'il est fourni il
            // doit exister. Son APPARTENANCE est vérifiée séparément, dans
            // `withValidator()` — une règle `exists` ne sait pas la contrôler.
            'vehicle_id' => ['nullable', 'integer', Rule::exists('vehicles', 'id')],
        ];
    }

    /**
     * Messages en français.
     *
     * ⚠️ Explicites et non délégués à `lang/fr` : ces deux-là ne se devinent
     * pas depuis le libellé du champ, et un prestataire qui lit « le champ
     * departure at doit être une date postérieure à maintenant » ne corrige rien.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'departure_at.after' => 'La date de départ doit être dans le futur : '
                .'un départ déjà passé ne peut plus être réservé.',
            'vehicle_id.exists' => 'Ce véhicule est introuvable.',
        ];
    }

    /**
     * Contrôles qui dépendent de PLUSIEURS champs ou de la base.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->verifierLeVehicule($validator);
        });
    }

    /**
     * Le véhicule rattaché doit appartenir au prestataire qui programme le départ.
     *
     * ⚠️ **Le trou que cette règle ferme est réel** : `vehicle_id` n'est qu'une
     * clé étrangère vers `vehicles`, sans lien avec l'auteur du départ. Sans ce
     * contrôle, n'importe quel prestataire vérifié pourrait rattacher le
     * véhicule d'un CONCURRENT à son propre trajet — et comme un départ hérite
     * des photos de son véhicule (F8.18), il vendrait des places en illustrant
     * son annonce avec le minibus de quelqu'un d'autre.
     *
     * ⚠️ La capacité annoncée ne peut pas dépasser celle du véhicule : le
     * prestataire vendrait des places qui n'existent pas dans la voiture, et le
     * refus n'arriverait qu'au moment de l'embarquement.
     */
    protected function verifierLeVehicule(Validator $validator): void
    {
        $vehicleId = $this->input('vehicle_id');

        if ($vehicleId === null || $validator->errors()->has('vehicle_id')) {
            return;
        }

        $vehicle = Vehicle::find($vehicleId);

        if ($vehicle === null) {
            return; // déjà signalé par la règle `exists`
        }

        if ($vehicle->provider_id !== $this->user()?->id) {
            $validator->errors()->add(
                'vehicle_id',
                'Ce véhicule ne vous appartient pas : vous ne pouvez programmer un départ '
                .'qu\'avec l\'un de vos propres véhicules.',
            );

            return;
        }

        $capacite = $this->capaciteVisee();

        if ($capacite !== null && $capacite > $vehicle->capacity) {
            $validator->errors()->add(
                'capacity',
                "Ce véhicule ne transporte que {$vehicle->capacity} passager(s) : "
                .'vous ne pouvez pas mettre en vente davantage de places.',
            );
        }
    }

    /**
     * La capacité que ce formulaire cherche à obtenir, ou `null` s'il n'y touche pas.
     *
     * En correction, un formulaire peut ne changer que le véhicule : c'est
     * alors la capacité DÉJÀ ENREGISTRÉE qu'il faut confronter, sinon on
     * laisserait passer un minibus de 9 places sur un départ qui en vend 30.
     */
    abstract protected function capaciteVisee(): ?int;
}
