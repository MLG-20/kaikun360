<?php

namespace App\Modules\Admin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Core\Models\UserDocument;
use App\Modules\Immo\Models\PropertyDocument;
use App\Modules\Manage\Models\OwnerPayout;
use App\Modules\Pro\Models\ProviderCertification;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gestion documentaire transverse du back-office (B13.7.3).
 *
 * Point d'accès centralisé aux pièces éparpillées dans les modules : pièces
 * d'identité (KYC), documents de biens, certifications prestataires et preuves
 * de reversement. Sensible (KYC, contrats) → réservé à `gerer:utilisateurs`.
 *
 * Vue d'ensemble (compteurs par type) sans `?type` ; liste normalisée paginée
 * avec `?type=`.
 */
class AdminDocumentController extends Controller
{
    /**
     * Types documentaires exposés et leur compteur.
     *
     * @var list<string>
     */
    private const TYPES = ['kyc', 'property', 'certification', 'payout_proof'];

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
        };
    }

    /**
     * Pagine une requête et normalise chaque élément via `$map`.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $query
     * @param  callable(\Illuminate\Database\Eloquent\Model): array<string, mixed>  $map
     */
    private function paginate($query, int $perPage, callable $map): JsonResponse
    {
        return ApiResponse::paginated($query->paginate($perPage)->through($map));
    }
}
