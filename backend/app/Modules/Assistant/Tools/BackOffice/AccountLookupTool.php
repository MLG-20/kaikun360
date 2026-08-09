<?php

namespace App\Modules\Assistant\Tools\BackOffice;

use App\Models\User;
use App\Modules\Admin\Enums\AdminPermission;
use App\Modules\Assistant\Contracts\ProvidesInputSchema;
use App\Modules\Assistant\Support\AssistantAction;
use App\Modules\Assistant\Support\AssistantContext;
use App\Modules\Assistant\Support\ToolResult;
use App\Modules\Core\Enums\UserRole;

/**
 * « Retrouve-moi le compte de… » (phase F10.3).
 *
 * Le geste le plus répété du back-office, et celui qui se fait le plus souvent
 * avec quelqu'un au téléphone : un client rappelle, il donne un nom ou un
 * numéro, et il faut ouvrir sa fiche avant qu'il ne raccroche. Trois clics et un
 * champ de recherche sur l'annuaire — ou une phrase.
 *
 * ── Ce qui sort d'ici, et pourquoi ─────────────────────────────────────────
 * ⚠️ Cet outil est **le plus bavard du module en données personnelles** : il
 * renvoie un nom, un statut, un rôle et une adresse e-mail. C'est assumé et
 * borné :
 *
 *   - il est gardé par `gerer:utilisateurs`, la permission de GOUVERNANCE qui
 *     ouvre déjà l'annuaire `GET /admin/users` — dont l'écran affiche
 *     exactement ces colonnes. L'assistant ne montre donc rien de neuf à
 *     personne : il évite un détour, il n'élargit pas un accès. Les agents en
 *     sont exclus par défaut (la permission ne s'obtient que déléguée, et
 *     seulement par un super administrateur) ;
 *   - le **téléphone et l'adresse postale n'en sortent pas**, alors qu'on peut
 *     chercher dessus. Chercher « le 77… » et confirmer que c'est bien Untel
 *     est légitime ; recracher les coordonnées complètes d'un client dans une
 *     bulle qui reste affichée à l'écran ne l'est pas. La fiche, elle, les
 *     montre — derrière un clic délibéré.
 *
 * ── Le terme est exigé, et sa longueur avec ────────────────────────────────
 * ⚠️ Sans terme, ou avec une lettre, la requête `LIKE '%a%'` remonterait
 * l'annuaire entier trié par date : un listing de comptes obtenu sans l'avoir
 * demandé. Deux caractères minimum, et un refus explicite sinon.
 */
class AccountLookupTool extends BackOfficeTool implements ProvidesInputSchema
{
    /**
     * Longueur minimale du terme recherché.
     */
    private const MIN_TERME = 2;

    public function name(): string
    {
        return 'rechercher_compte';
    }

    public function description(): string
    {
        return 'Retrouve un compte utilisateur à partir d\'un nom, d\'une adresse e-mail ou d\'un '
            .'numéro de téléphone, et renvoie son statut, son rôle et le lien vers sa fiche. '
            .'À utiliser quand un membre de l\'équipe cherche qui est un client, veut ouvrir sa '
            .'fiche ou vérifier l\'état de son compte. Paramètre obligatoire : `terme` '
            .'(au moins 2 caractères). ⚠️ Lecture seule : cet outil ne modifie aucun compte.';
    }

    /**
     * Paramètres offerts au modèle (F10.4).
     *
     * Le modèle remplit `terme` là où le cerveau déterministe l'extrayait par
     * découpage du message — et c'est précisément ce découpage qui avait
     * produit les deux défauts trouvés en curl en F10.3 (« retrouve-moi » pris
     * pour un nom, référence à deux tirets tronquée). Le modèle n'a pas ce
     * problème : il sait quel bout de la phrase est le nom cherché.
     *
     * La garde de longueur reste dans `run()` : un schéma n'est pas une
     * validation, et un `terme` d'un caractère ferait remonter la moitié de
     * l'annuaire.
     */
    public function inputSchema(): array
    {
        return [
            'properties' => [
                'terme' => [
                    'type' => 'string',
                    'description' => 'Nom, prénom, adresse e-mail ou numéro de téléphone recherché. '
                        ."Ne transmettre que l'identité cherchée, sans les mots de la question "
                        .'(« retrouve-moi le compte de Anne-Marie Fall » → « Anne-Marie Fall »).',
                ],
            ],
            'required' => ['terme'],
        ];
    }

    protected function permission(): AdminPermission
    {
        return AdminPermission::GERER_UTILISATEURS;
    }

    public function run(array $input, AssistantContext $context): ToolResult
    {
        $url = $this->boUrl('comptes');
        $terme = trim((string) ($input['terme'] ?? ''));

        if (mb_strlen($terme) < self::MIN_TERME) {
            return $this->nothing(
                'Précisez un nom, une adresse e-mail ou un numéro — au moins deux caractères.',
                'Ouvrir l\'annuaire des comptes',
                $url,
            );
        }

        // Cloisonnement RECOPIÉ de `AdminUserController::index` : mêmes trois
        // colonnes, même `LIKE` encadré. Chercher « à peu près pareil » ici
        // donnerait des résultats que l'écran ne sait pas reproduire, donc un
        // agent qui ne retrouve pas ce que l'assistant vient de lui montrer.
        $motif = '%'.$terme.'%';

        $comptes = User::query()
            ->where(fn ($requete) => $requete->where('name', 'like', $motif)
                ->orWhere('email', 'like', $motif)
                ->orWhere('phone', 'like', $motif))
            ->with('roles')
            ->latest()
            ->limit($this->limit())
            ->get();

        if ($comptes->isEmpty()) {
            return $this->nothing(
                'Aucun compte ne correspond à « '.$terme.' ».',
                'Chercher dans l\'annuaire',
                $url,
            );
        }

        return new ToolResult(
            summary: $comptes->count() === 1
                ? 'Un compte correspond à « '.$terme.' » :'
                : $comptes->count().' comptes correspondent à « '.$terme.' » :',
            items: $comptes->map(fn (User $compte) => [
                'titre' => $compte->name,
                'statut' => $compte->status?->label(),
                'detail' => $this->roleLabel($compte),
                // L'adresse sert à LEVER L'AMBIGUÏTÉ entre deux homonymes, ce
                // qui est le cas d'usage même de l'outil. Elle est en petit,
                // comme une référence, parce que c'est ce qu'elle est ici.
                'reference' => $compte->email,
                'url' => $url.'/'.$compte->id,
            ])->all(),
            actions: [AssistantAction::link('Ouvrir l\'annuaire des comptes', $url)],
        );
    }

    /**
     * Rôle lisible d'un compte.
     *
     * Un compte peut en cumuler plusieurs (un propriétaire est souvent client) :
     * on les affiche tous, dans l'ordre de la base, plutôt que d'en élire un.
     * Ce n'est pas ici qu'on arbitre une hiérarchie de rôles — `AssistantContext`
     * le fait pour composer une trousse, et pour cela seulement.
     */
    private function roleLabel(User $compte): ?string
    {
        $libelles = $compte->roles
            ->map(fn ($role) => UserRole::tryFrom($role->name)?->label() ?? $role->name)
            ->all();

        return $libelles === [] ? null : implode(', ', $libelles);
    }
}
