<?php

namespace Database\Seeders;

use App\Models\Faq;
use Illuminate\Database\Seeder;

/**
 * Contenu éditorial de DÉMONSTRATION : la FAQ publiée, plus les pages
 * institutionnelles et légales appelées via {@see PublicPagesSeeder}.
 *
 * Alimente les pages du frontend (F2.8 : /faqs, /pages/{slug}, À propos) pour
 * qu'elles s'affichent remplies en développement local. Séparé du DemoSeeder
 * (catalogues) et du DatabaseSeeder (référentiel) pour rester modulaire et ne
 * jamais polluer les tests.
 *
 * ⚠️ **Les pages ne vivent plus ici** (F8.15.e). Elles ont migré dans
 * {@see PublicPagesSeeder} parce qu'elles ne sont PAS de la démonstration : le
 * CDC §4.2 les impose en production. Surtout, la garde d'idempotence ci-dessous
 * est « tout ou rien » — une seule page en base et le seeder s'arrête —, si
 * bien qu'une page ajoutée à la liste n'aurait jamais atteint une base déjà
 * remplie. `PublicPagesSeeder` garde slug par slug, ce qui rend l'ajout d'une
 * page sûr et rejouable. La garde ci-dessous ne porte donc plus que sur la FAQ.
 *
 * Le corps des pages (`body`) est un fragment HTML : le frontend le rend via
 * `[innerHTML]` (Angular assainit automatiquement le balisage). En production,
 * ce contenu est édité depuis le back-office (PATCH /admin/pages, /admin/faqs).
 *
 * À lancer explicitement :
 *   php artisan db:seed --class=ContentSeeder
 *
 * Idempotent : relance sûre (FAQ déjà présente → rien ; pages → par slug).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // Les pages légales/institutionnelles d'abord : elles sont attendues en
        // production et se posent une par une, indépendamment de la FAQ.
        $this->call(PublicPagesSeeder::class);

        // Garde d'idempotence de la DÉMO : FAQ déjà présente → on s'arrête.
        if (Faq::query()->exists()) {
            $this->command?->info('ContentSeeder : FAQ de démonstration déjà présente, rien à faire.');

            return;
        }

        $this->seedFaqs();

        $this->command?->info('ContentSeeder : FAQ de démonstration créée.');
    }

    /**
     * Entrées de FAQ, regroupées par catégorie et ordonnées par `position`.
     */
    private function seedFaqs(): void
    {
        foreach ($this->faqs() as $position => $faq) {
            Faq::query()->create([
                'question' => $faq['question'],
                'answer' => $faq['answer'],
                'category' => $faq['category'],
                'position' => $position,
                'is_published' => true,
            ]);
        }
    }

    /**
     * @return list<array{question: string, answer: string, category: string}>
     */
    private function faqs(): array
    {
        return [
            [
                'category' => 'Général',
                'question' => "Qu'est-ce que Kaikun 360 ?",
                'answer' => "Kaikun 360 est une plateforme sénégalaise qui réunit l'immobilier, les séjours, le tourisme, le transport, la construction et les services aux entreprises, avec un protocole de vérification pour des transactions fiables.",
            ],
            [
                'category' => 'Général',
                'question' => 'La création de compte est-elle gratuite ?',
                'answer' => "Oui, créer un compte et parcourir les annonces est entièrement gratuit. Certains services (mise en relation, réservation) peuvent donner lieu à une commission précisée au moment de la transaction.",
            ],
            [
                'category' => 'Général',
                'question' => 'Comment contacter le support ?',
                'answer' => "Depuis la page Contact, vous pouvez nous écrire par e-mail ou nous joindre directement sur WhatsApp pour une réponse rapide.",
            ],
            [
                'category' => 'Confiance & vérification',
                'question' => 'Comment les biens et prestataires sont-ils vérifiés ?',
                'answer' => "Chaque bien et chaque prestataire fait l'objet d'une vérification documentée avant publication. Les visites et étapes clés sont filmées et datées, et chaque dossier reçoit un numéro de suivi unique.",
            ],
            [
                'category' => 'Confiance & vérification',
                'question' => 'Que signifie le badge « Vérifié » ?',
                'answer' => "Le badge indique que l'annonce ou le prestataire a passé notre processus de contrôle. En son absence, l'annonce reste visible mais n'a pas encore été validée à ce niveau.",
            ],
            [
                'category' => 'Diaspora',
                'question' => 'Puis-je piloter un projet depuis l\'étranger ?',
                'answer' => "Absolument. Kaikun 360 est conçu pour la diaspora : suivi à distance, comptes rendus réguliers, visites filmées et un interlocuteur dédié vous permettent de décider en confiance sans être sur place.",
            ],
            [
                'category' => 'Diaspora',
                'question' => 'Comment éviter les arnaques à distance ?',
                'answer' => "Restez toujours dans le cadre de la plateforme : vérifiez le titre foncier avant tout achat de terrain, privilégiez les annonces vérifiées et n'effectuez jamais de paiement hors du circuit sécurisé de Kaikun 360.",
            ],
            [
                'category' => 'Réservations & paiements',
                'question' => 'Quand ma réservation est-elle confirmée ?',
                'answer' => "Une réservation est confirmée une fois le paiement validé. Vous recevez alors une confirmation et pouvez suivre l'état de votre dossier depuis votre espace.",
            ],
            [
                'category' => 'Réservations & paiements',
                'question' => 'Quels moyens de paiement sont acceptés ?',
                'answer' => "Le paiement mobile (Wave, Orange Money) est pris en charge. Un mode de paiement manuel encadré est également disponible ; les instructions vous sont communiquées lors de l'initiation du paiement.",
            ],
            [
                'category' => 'Prestataires',
                'question' => 'Comment devenir prestataire sur Kaikun 360 ?',
                'answer' => "Rendez-vous sur la page Kaikun Pro puis remplissez le formulaire d'inscription prestataire. Après vérification de votre dossier, votre profil est activé sur la marketplace.",
            ],
            [
                'category' => 'Prestataires',
                'question' => 'Comment déposer un bien immobilier ?',
                'answer' => "Depuis l'univers Immobilier, cliquez sur « Déposer un bien », renseignez la localisation et les caractéristiques. Le bien est publié après validation par nos équipes.",
            ],
        ];
    }
}
