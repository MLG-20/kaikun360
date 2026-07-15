<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Contenu éditorial de DÉMONSTRATION (hors production) : FAQ publiée et pages
 * institutionnelles / légales servies publiquement par slug.
 *
 * Alimente les pages du frontend (F2.8 : /faqs, /pages/{slug}, À propos) pour
 * qu'elles s'affichent remplies en développement local. Séparé du DemoSeeder
 * (catalogues) et du DatabaseSeeder (référentiel) pour rester modulaire et ne
 * jamais polluer les tests.
 *
 * Le corps des pages (`body`) est un fragment HTML : le frontend le rend via
 * `[innerHTML]` (Angular assainit automatiquement le balisage). En production,
 * ce contenu est édité depuis le back-office (PATCH /admin/pages, /admin/faqs).
 *
 * À lancer explicitement :
 *   php artisan db:seed --class=ContentSeeder
 *
 * Idempotent : si des pages existent déjà, on ne réinsère rien (relance sûre).
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // Garde d'idempotence : contenu déjà présent → on s'arrête.
        if (Page::query()->exists() || Faq::query()->exists()) {
            $this->command?->info('ContentSeeder : contenu éditorial déjà présent, rien à faire.');

            return;
        }

        $this->seedPages();
        $this->seedFaqs();

        $this->command?->info('ContentSeeder : pages et FAQ de démonstration créées.');
    }

    /**
     * Pages institutionnelles et légales, adressées par slug.
     */
    private function seedPages(): void
    {
        foreach ($this->pages() as $slug => $page) {
            Page::query()->create([
                'slug' => $slug,
                'title' => $page['title'],
                'body' => $page['body'],
                'is_published' => true,
            ]);
        }
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
     * @return array<string, array{title: string, body: string}>
     */
    private function pages(): array
    {
        return [
            'a-propos' => [
                'title' => 'À propos de Kaikun 360',
                'body' => <<<'HTML'
<p>Kaikun 360 est la plateforme sénégalaise qui réunit en un seul endroit
l'immobilier, les séjours, le tourisme, le transport, la construction et les
services aux entreprises. Notre mission : rendre chaque transaction <strong>simple,
vérifiée et fiable</strong>, que vous soyez au Sénégal ou dans la diaspora.</p>

<h2>Notre raison d'être</h2>
<p>Trop de projets — un terrain acheté à distance, une villa louée pour les
vacances, un chantier confié depuis l'étranger — se heurtent au même obstacle :
le manque de confiance. Kaikun 360 est né pour lever cet obstacle avec un
protocole clair et des acteurs identifiés.</p>

<h2>Le protocole de confiance</h2>
<ul>
  <li><strong>Vérification documentée</strong> des biens et des prestataires
  avant publication.</li>
  <li><strong>Tout est filmé et daté</strong> : visites, états des lieux et
  étapes de chantier sont tracés.</li>
  <li><strong>Un numéro de suivi unique</strong> par dossier, pour savoir en
  permanence où en est votre projet.</li>
</ul>

<h2>Pensé pour la diaspora</h2>
<p>Piloter un projet à distance ne devrait pas rimer avec inquiétude. Nos outils
de suivi, nos comptes rendus réguliers et notre accompagnement humain permettent
de décider en confiance, où que vous soyez.</p>

<h2>Nos huit univers</h2>
<p>Immobilier, Nuitées &amp; séjours, Tourisme, Transport, Construction, Gestion
locative, Diaspora et Team building : une même exigence de sérieux sur chacun.</p>

<p>Une question ? Notre équipe est disponible sur la page
<a href="/contact">Contact</a>.</p>
HTML,
            ],
            'mentions-legales' => [
                'title' => 'Mentions légales',
                'body' => <<<'HTML'
<h2>Éditeur du site</h2>
<p>Le présent site est édité par Kaikun 360, plateforme de mise en relation de
services immobiliers, touristiques et de construction au Sénégal.</p>
<p>Contact : <a href="mailto:support@kaikun360.sn">support@kaikun360.sn</a></p>

<h2>Hébergement</h2>
<p>Le site est hébergé sur une infrastructure sécurisée. Les coordonnées de
l'hébergeur sont disponibles sur simple demande auprès du support.</p>

<h2>Propriété intellectuelle</h2>
<p>L'ensemble des contenus (textes, visuels, logos, marque « Kaikun 360 ») est
protégé. Toute reproduction sans autorisation préalable est interdite.</p>

<h2>Responsabilité</h2>
<p>Kaikun 360 agit comme intermédiaire de confiance entre utilisateurs et
prestataires. Les informations publiées par les annonceurs restent sous leur
responsabilité ; Kaikun 360 met en œuvre un processus de vérification mais ne
saurait être tenu responsable d'un usage contraire aux présentes conditions.</p>
HTML,
            ],
            'cgu' => [
                'title' => "Conditions générales d'utilisation",
                'body' => <<<'HTML'
<p>Les présentes conditions générales d'utilisation (CGU) encadrent l'accès et
l'usage de la plateforme Kaikun 360. En créant un compte, vous les acceptez.</p>

<h2>1. Objet</h2>
<p>Kaikun 360 met en relation des utilisateurs recherchant un bien ou un service
avec des propriétaires et prestataires vérifiés.</p>

<h2>2. Compte utilisateur</h2>
<p>Vous êtes responsable de l'exactitude des informations de votre compte et de
la confidentialité de vos identifiants. Certaines actions (dépôt de bien,
paiement) requièrent un compte vérifié.</p>

<h2>3. Engagements des prestataires</h2>
<p>Les prestataires s'engagent à fournir des informations exactes, à respecter
le protocole de vérification et à honorer les réservations confirmées.</p>

<h2>4. Réservations et paiements</h2>
<p>Une réservation n'est confirmée qu'après validation du paiement. Les modalités
d'annulation sont précisées au moment de la réservation.</p>

<h2>5. Comportements interdits</h2>
<p>Sont notamment interdits : la publication d'annonces frauduleuses, l'usurpation
d'identité et toute tentative de contournement du protocole de confiance.</p>

<h2>6. Modification des CGU</h2>
<p>Kaikun 360 peut faire évoluer les présentes conditions ; les utilisateurs sont
informés des changements significatifs.</p>
HTML,
            ],
            'politique-confidentialite' => [
                'title' => 'Politique de confidentialité',
                'body' => <<<'HTML'
<p>Kaikun 360 attache une grande importance à la protection de vos données
personnelles. Cette politique explique quelles données nous collectons et comment
nous les utilisons.</p>

<h2>Données collectées</h2>
<ul>
  <li>Données de compte : nom, e-mail, téléphone, ville.</li>
  <li>Données de projet : demandes, réservations, échanges avec les prestataires.</li>
  <li>Données techniques : informations de connexion nécessaires au bon
  fonctionnement du service.</li>
</ul>

<h2>Utilisation des données</h2>
<p>Vos données servent à fournir le service, sécuriser les transactions, vous
tenir informé de l'avancement de vos projets et améliorer la plateforme. Elles ne
sont jamais vendues à des tiers.</p>

<h2>Conservation</h2>
<p>Les données sont conservées le temps nécessaire à la fourniture du service et
au respect de nos obligations légales.</p>

<h2>Vos droits</h2>
<p>Vous pouvez accéder à vos données, les corriger ou en demander la suppression
en écrivant à <a href="mailto:support@kaikun360.sn">support@kaikun360.sn</a>.</p>
HTML,
            ],
        ];
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
