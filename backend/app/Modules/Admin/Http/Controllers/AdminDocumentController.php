<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Modules\Core\Models\UserDocument;
use App\Modules\Immo\Models\PropertyDocument;
use App\Modules\Manage\Models\ManagementMandate;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Pro\Models\ProviderCertification;
use App\Support\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gestion documentaire transverse du back-office (B13.7.3).
 *
 * Point d'accès centralisé aux pièces éparpillées dans les modules. Sensible
 * (KYC, contrats) → réservé à `gerer:utilisateurs`.
 *
 * Vue d'ensemble (compteurs par type) sans `?type` ; liste normalisée paginée
 * avec `?type=`.
 *
 * **F7.4.c — couverture des six items du CDC §6 (module Documents)** :
 * « Mandats, contrats, preuves, pièces, rapports, pièces prestataires ».
 * Il en manquait deux, et ils existaient pourtant en base :
 *   - `mandate`  → mandats de gestion (les « mandats/contrats ») ;
 *   - `report`   → rapports de suivi photo/vidéo (chantiers ET diaspora, le
 *                  modèle `Report` étant polymorphe depuis B5.2).
 *
 * ⚠️ Écart assumé sur les mandats : un `ManagementMandate` porte ses clauses en
 * TEXTE (colonne `terms`), il n'y a pas de PDF signé téléversé — la plateforme
 * n'a jamais modélisé de pièce jointe de contrat. La ligne renvoie donc vers la
 * fiche du mandat, où les clauses se lisent et s'éditent, plutôt que vers un
 * fichier. Y ajouter un contrat scanné demanderait une table de pièces dédiée.
 */
class AdminDocumentController extends Controller
{
    /**
     * Types documentaires exposés et leur compteur.
     *
     * @var list<string>
     */
    private const TYPES = ['kyc', 'property', 'certification', 'payout_proof', 'mandate', 'report'];

    /**
     * GET /api/v1/admin/documents  (overview)
     * GET /api/v1/admin/documents?type=kyc  (liste paginée normalisée)
     */
    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');

        if ($type === null) {
            return ApiResponse::success([
                'documents' => [
                    'kyc' => UserDocument::count(),
                    'property' => PropertyDocument::count(),
                    'certification' => ProviderCertification::count(),
                    'payout_proof' => OwnerPayout::whereNotNull('proof_path')->count(),
                    'mandate' => ManagementMandate::count(),
                    'report' => Report::count(),
                ],
            ]);
        }

        if (! in_array($type, self::TYPES, true)) {
            throw new NotFoundHttpException("Type de document inconnu : {$type}.");
        }

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        return match ($type) {
            'kyc' => $this->paginate(
                UserDocument::query()->latest(),
                $perPage,
                fn (UserDocument $d) => [
                    'doc_type' => 'kyc',
                    'id' => $d->id,
                    'subject_type' => 'user',
                    'subject_id' => $d->user_id,
                    'label' => $d->type,
                    'original_name' => $d->original_name,
                    'status' => $d->status,
                    'created_at' => $d->created_at,
                ],
            ),
            'property' => $this->paginate(
                PropertyDocument::query()->latest(),
                $perPage,
                fn (PropertyDocument $d) => [
                    'doc_type' => 'property',
                    'id' => $d->id,
                    'subject_type' => 'property',
                    'subject_id' => $d->property_id,
                    'label' => $d->type,
                    'original_name' => $d->original_name,
                    'status' => $d->validation_status,
                    'created_at' => $d->created_at,
                ],
            ),
            'certification' => $this->paginate(
                ProviderCertification::query()->latest(),
                $perPage,
                fn (ProviderCertification $c) => [
                    'doc_type' => 'certification',
                    'id' => $c->id,
                    'subject_type' => 'provider',
                    'subject_id' => $c->provider_id,
                    'label' => $c->name.($c->issuer ? ' — '.$c->issuer : ''),
                    'original_name' => $c->file_path,
                    'status' => $c->verified ? 'verifie' : 'non_verifie',
                    'created_at' => $c->created_at,
                ],
            ),
            'payout_proof' => $this->paginate(
                OwnerPayout::query()->whereNotNull('proof_path')->latest(),
                $perPage,
                fn (OwnerPayout $p) => [
                    'doc_type' => 'payout_proof',
                    'id' => $p->id,
                    'subject_type' => 'owner',
                    'subject_id' => $p->owner_id,
                    'label' => $p->reference,
                    'original_name' => $p->proof_path,
                    'status' => $p->status->value,
                    'created_at' => $p->created_at,
                ],
            ),
            // F7.4.c — Les MANDATS/CONTRATS du CDC. Pas de fichier joint (cf.
            // en-tête) : `original_name` reste null et l'écran renvoie vers la
            // fiche du mandat. Le bien est chargé pour intituler la ligne —
            // « MND-0007 » seul ne dit rien à qui cherche un contrat.
            'mandate' => $this->paginate(
                ManagementMandate::query()->with('property:id,title')->latest(),
                $perPage,
                fn (ManagementMandate $m) => [
                    'doc_type' => 'mandate',
                    'id' => $m->id,
                    'subject_type' => 'mandate',
                    'subject_id' => $m->id,
                    'label' => $m->reference.($m->property ? ' — '.$m->property->title : ''),
                    'original_name' => null,
                    'status' => $m->status->value,
                    'created_at' => $m->created_at,
                ],
            ),
            // F7.4.c — Les RAPPORTS de suivi. Le modèle est polymorphe : la même
            // liste couvre les comptes rendus de chantier et ceux des dossiers
            // diaspora, ce qui est exactement ce que le CDC entend par
            // « rapports ». Le nombre de photos tient lieu de volumétrie.
            'report' => $this->paginate(
                Report::query()->latest(),
                $perPage,
                fn (Report $r) => [
                    'doc_type' => 'report',
                    'id' => $r->id,
                    // Le sujet est polymorphe : on renvoie le type porteur pour
                    // que l'écran sache vers quelle fiche pointer.
                    'subject_type' => class_basename($r->reportable_type),
                    'subject_id' => $r->reportable_id,
                    'label' => $r->reference.' — '.$r->type->value,
                    'original_name' => count($r->photos ?? []).' photo(s)'
                        .($r->video_url ? ' + vidéo' : ''),
                    'status' => null,
                    'created_at' => $r->created_at,
                ],
            ),
        };
    }

    /**
     * Pagine une requête et normalise chaque élément via `$map`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  callable(Model): array<string, mixed>  $map
     */
    private function paginate($query, int $perPage, callable $map): JsonResponse
    {
        return ApiResponse::paginated($query->paginate($perPage)->through($map));
    }
}
