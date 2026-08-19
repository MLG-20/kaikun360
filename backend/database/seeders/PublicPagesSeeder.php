<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * Pages institutionnelles et légales du site public (F8.15.e).
 *
 * ⚠️ **Ce n'est PAS du contenu de démonstration.** Le CDC §4.2 « Composants web
 * obligatoires » impose six pages légales — « CGU, CGV, confidentialité,
 * cookies, conditions de mandat, politique de remboursement » — et le §13 les
 * classe toutes en priorité **Haute**. Elles doivent donc exister en
 * production, contrairement à la FAQ et aux catalogues de démonstration.
 *
 * D'où un seeder séparé de {@see ContentSeeder}, pour deux raisons :
 *
 * 1. **L'idempotence n'est pas la même.** ContentSeeder s'arrête dès qu'UNE
 *    page existe (garde « contenu déjà présent, rien à faire »). Sur une base
 *    déjà remplie — c'est-à-dire toute base réelle — y ajouter une page neuve
 *    ne l'aurait jamais posée. Ici la garde est **par slug** : les pages
 *    manquantes sont créées, les existantes ne sont **jamais réécrites**.
 * 2. **Le contenu est édité au back-office** (`PATCH /admin/pages`). Une
 *    relance du seeder après une relecture juridique ne doit pas effacer le
 *    texte validé par l'équipe — d'où `firstOrCreate` et non `updateOrCreate`.
 *
 * À lancer explicitement, et **à rejouer après chaque déploiement** qui ajoute
 * une page à la liste ci-dessous :
 *   php artisan db:seed --class=PublicPagesSeeder
 *
 * ⚠️ **CONTENU JURIDIQUE À FAIRE VALIDER.** Les textes ci-dessous sont des
 * versions de travail rédigées à partir du fonctionnement RÉEL du produit
 * (délais d'annulation lus dans le code, taux de commission par mandat, sort de
 * la caution, contraintes de PayTech). Ils ne remplacent pas l'avis d'un
 * **conseil juridique sénégalais**, que le CDC §12 exige explicitement :
 * « Contrats, CGU/CGV, mandats et limites de responsabilité à valider avec un
 * conseil juridique sénégalais. » Chaque page le mentionne en pied de texte.
 *
 * ⚠️ **Le §13 va plus loin que ces pages web** : il liste aussi des documents
 * *contractuels* (mandat de vente/intermédiation, contrat prestataire mobilité,
 * charte qualité, contrat BTP, convention team building) qui ne sont pas des
 * pages de site — ils se signent, ils ne se publient pas. Ils restent à
 * produire hors plateforme.
 */
class PublicPagesSeeder extends Seeder
{
    public function run(): void
    {
        $cree = 0;

        foreach ($this->pages() as $slug => $page) {
            // Garde d'idempotence PAR SLUG : on ne touche jamais à une page
            // déjà en base, son corps ayant pu être réécrit au back-office.
            $modele = Page::query()->firstOrCreate(
                ['slug' => $slug],
                [
                    'title' => $page['title'],
                    'body' => $page['body'],
                    'is_published' => true,
                ],
            );

            if ($modele->wasRecentlyCreated) {
                $cree++;
            }
        }

        $this->command?->info("PublicPagesSeeder : {$cree} page(s) créée(s), les autres étaient déjà en base.");
    }

    /**
     * Les pages adressées publiquement par slug (`GET /api/v1/pages/{slug}`).
     *
     * L'ordre suit celui du pied de page. Les quatre premières existent depuis
     * B13.4 ; les quatre dernières comblent l'écart CDC §4.2 relevé en F8.15.e.
     *
     * @return array<string, array{title: string, body: string}>
     */
    public function pages(): array
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
<p>Contact : <a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a></p>

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
en écrivant à <a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a>.</p>
HTML,
            ],

            // --- F8.15.e : les quatre pages exigées par le CDC §4.2 -----------

            'cgv' => [
                'title' => 'Conditions générales de vente et de service',
                'body' => <<<'HTML'
<p>Les présentes conditions générales de vente et de service (CGV) encadrent les
réservations, devis, paiements et remboursements réalisés sur Kaikun 360. Elles
complètent les <a href="/pages/cgu">conditions générales d'utilisation</a>, qui
restent applicables.</p>

<h2>1. Le rôle de Kaikun 360</h2>
<p>Kaikun 360 est un <strong>intermédiaire</strong>. La plateforme met en
relation un client avec un propriétaire, un hôte, un transporteur, un
organisateur ou un prestataire, encaisse le règlement pour leur compte, prélève
sa commission et reverse le solde au partenaire.</p>
<p>La prestation elle-même — le logement, le véhicule, le circuit, le trajet, le
chantier — est fournie par le partenaire, qui en demeure responsable. Kaikun 360
n'est pas notaire, géomètre, assureur, ni autorité de transport, et ne se
substitue à aucune profession réglementée.</p>

<h2>2. Comment un contrat se forme</h2>
<p>Deux chemins coexistent selon la nature de l'offre.</p>
<ul>
  <li><strong>Offre standardisée</strong> (nuitées, véhicules, circuits,
  trajets) : vous réservez directement depuis la fiche. La réservation est
  enregistrée <em>en attente</em> et ne devient ferme qu'au paiement.</li>
  <li><strong>Prestation sur mesure</strong> (construction, gestion locative,
  accompagnement diaspora, team building) : vous déposez une demande, un
  conseiller nommé établit un <strong>devis</strong>. Votre acceptation du devis
  vaut accord sur son contenu et fait naître une réservation payable.</li>
</ul>
<p>Tant qu'un devis n'est pas accepté, il ne vous engage pas. Une fois accepté,
son montant devient exigible dans les conditions ci-dessous.</p>

<h2>3. Prix et commission</h2>
<p>Les prix sont affichés en <strong>francs CFA (XOF)</strong>, toutes taxes
comprises lorsqu'elles sont applicables. Le montant présenté avant paiement est
le montant total dû : aucun frais n'est ajouté à l'étape suivante.</p>
<p>La rémunération de Kaikun 360 est une <strong>commission</strong> prélevée sur
la transaction. Elle est <em>figée au moment de la réservation</em> : une
révision ultérieure du barème ne s'applique jamais à un dossier déjà engagé.
Pour les prestations sur mesure chiffrées poste par poste, la rémunération de la
plateforme est la marge portée au devis, que le total signé contient déjà.</p>

<h2>4. Paiement</h2>
<p>Le règlement s'effectue <strong>en ligne</strong>, par carte bancaire ou par
mobile money (Orange Money, Wave, Free Money), via notre prestataire de paiement.
Le paiement porte sur le <strong>montant intégral</strong> de la réservation.</p>
<p>La réservation est confirmée à la <strong>réception de la confirmation du
prestataire de paiement</strong>, et non au clic : un paiement interrompu ou
refusé laisse la réservation en attente, sans engagement de votre part.</p>
<p>Un versement partiel (acompte) ou un règlement par transfert direct ne sont
pas proposés en libre-service. Ils relèvent d'une dérogation accordée au cas par
cas par l'équipe, qui la constate alors elle-même dans le dossier.</p>

<h2>5. Caution</h2>
<p>Certaines prestations — location de véhicule, séjour en logement — sont
assorties d'une <strong>caution</strong>, dont le montant est affiché sur la
fiche avant réservation. Elle garantit les dégradations et les manquements aux
règles d'usage. Son sort à l'issue de la prestation est décrit dans la
<a href="/pages/politique-annulation-remboursement">politique d'annulation, de
remboursement et de caution</a>.</p>

<h2>6. Annulation et remboursement</h2>
<p>Les délais et les conditions de remboursement dépendent de l'univers concerné
et sont détaillés dans la
<a href="/pages/politique-annulation-remboursement">politique d'annulation, de
remboursement et de caution</a>, qui fait partie intégrante des présentes CGV.</p>

<h2>7. Obligations du client</h2>
<p>Vous vous engagez à fournir des informations exactes, à vous présenter aux
dates convenues, à respecter les règles d'usage du bien ou du service et à
signaler sans délai tout incident depuis votre espace ou auprès du support.</p>

<h2>8. Obligations du partenaire</h2>
<p>Le partenaire s'engage à maintenir ses disponibilités à jour, à fournir la
prestation dans les conditions annoncées, à détenir les autorisations,
assurances et documents exigés par la réglementation — notamment pour le
transport de personnes — et à honorer toute réservation confirmée.</p>

<h2>9. Réclamations</h2>
<p>Toute réclamation se dépose depuis votre espace, par la messagerie rattachée
au dossier concerné, ou par écrit à
<a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a>. Un dossier ouvert
reçoit une référence de suivi. Nous nous efforçons d'apporter une première
réponse sous <strong>72 heures ouvrées</strong>.</p>

<h2>10. Responsabilité</h2>
<p>Kaikun 360 répond de l'exploitation de la plateforme, de la sécurité des
paiements qu'elle encaisse et du sérieux de son protocole de vérification. Elle
ne répond pas de l'exécution matérielle de la prestation par le partenaire, ni
des informations inexactes qu'un annonceur aurait fournies malgré ce protocole.</p>

<h2>11. Droit applicable</h2>
<p>Les présentes conditions sont soumises au <strong>droit sénégalais</strong>.
En cas de différend, les parties rechercheront une solution amiable avant toute
action ; à défaut, les juridictions compétentes de Dakar seront saisies.</p>

<p class="page-note"><em>Version de travail. Ce document est en cours de
validation par un conseil juridique sénégalais ; sa version définitive
prévaudra.</em></p>
HTML,
            ],
            'politique-cookies' => [
                'title' => 'Politique de cookies',
                'body' => <<<'HTML'
<p>Cette page explique ce que Kaikun 360 enregistre sur votre appareil lorsque
vous utilisez le site, pourquoi, et comment vous gardez la main. Elle complète la
<a href="/pages/politique-confidentialite">politique de confidentialité</a>.</p>

<h2>Ce dont il s'agit</h2>
<p>Un « cookie » est un petit fichier déposé par un site sur votre navigateur. À
côté des cookies, un site peut aussi utiliser le <em>stockage local</em> du
navigateur, qui remplit le même office. Nous employons ici le mot « cookie » au
sens large, pour ces deux techniques.</p>

<h2>Ce que nous déposons</h2>
<p>Kaikun 360 s'en tient aux éléments <strong>strictement nécessaires</strong> au
fonctionnement du service :</p>
<ul>
  <li><strong>Session et authentification</strong> — vous garder connecté d'une
  page à l'autre et sécuriser les formulaires. Sans eux, il faudrait se
  reconnecter à chaque écran et le paiement ne pourrait pas aboutir.</li>
  <li><strong>Préférences d'usage</strong> — vos favoris et la saisie d'une
  réservation en cours, conservés le temps de votre visite pour ne pas vous
  faire tout ressaisir après une connexion.</li>
  <li><strong>Connexion via Google</strong>, si vous choisissez ce mode
  d'identification : Google dépose alors ses propres cookies, régis par sa
  politique de confidentialité.</li>
  <li><strong>Prestataire de paiement</strong>, pendant la transaction : le
  règlement se déroule sur la page sécurisée de notre prestataire, qui applique
  sa propre politique.</li>
</ul>

<h2>Ce que nous ne faisons pas</h2>
<p>À ce jour, Kaikun 360 <strong>ne dépose aucun cookie publicitaire</strong>, ne
revend aucune donnée de navigation et n'utilise pas de traceur permettant de
vous suivre sur d'autres sites.</p>
<p>Si une mesure d'audience ou un outil publicitaire venait à être ajouté, il ne
serait activé qu'<strong>après votre consentement</strong>, recueilli par un
bandeau dédié, et cette page serait mise à jour au préalable.</p>

<h2>Vos moyens d'action</h2>
<p>Tous les navigateurs permettent d'afficher, de bloquer ou de supprimer les
cookies d'un site, généralement depuis les paramètres de confidentialité.
Attention : bloquer les cookies nécessaires rend la connexion à votre espace et
le paiement impossibles.</p>
<p>Vous déconnecter efface les éléments liés à votre session. Fermer l'onglet
efface les préférences conservées le temps de la visite.</p>

<h2>Questions</h2>
<p>Pour toute question sur cette politique, écrivez à
<a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a>.</p>

<p class="page-note"><em>Version de travail. Ce document est en cours de
validation par un conseil juridique sénégalais ; sa version définitive
prévaudra.</em></p>
HTML,
            ],
            'conditions-de-mandat' => [
                'title' => 'Conditions du mandat de gestion locative',
                'body' => <<<'HTML'
<p>Confier un bien à Kaikun 360 en gestion locative se formalise par un
<strong>mandat</strong>, établi par un conseiller avec le propriétaire. Les
présentes conditions en décrivent le cadre commun ; chaque mandat précise ses
propres clauses, sa durée et son taux de commission, qui prévalent sur le texte
général.</p>

<h2>1. Objet du mandat</h2>
<p>Par le mandat, le propriétaire confie à Kaikun 360 la gestion locative d'un
bien identifié : recherche et sélection du locataire, encaissement des loyers,
délivrance des quittances, suivi des incidents, engagement des dépenses
d'entretien convenues, reddition de comptes et reversement du solde.</p>
<p>Le mandat de gestion ne vaut <strong>pas</strong> mandat de vente. La vente ou
l'intermédiation sur une transaction immobilière fait l'objet d'un document
distinct.</p>

<h2>2. Un seul mandat vivant par bien</h2>
<p>Un bien ne peut être placé sous <strong>qu'un seul mandat en cours à la
fois</strong>. Deux mandats simultanés produiraient deux commissions sur le même
loyer et un compte propriétaire faux. Un mandat arrivé à son terme ne fait
évidemment pas obstacle à un renouvellement.</p>

<h2>3. Durée et fin</h2>
<p>Le mandat court sur la période portée à l'acte. Il prend fin à son terme, ou
par accord des parties. Les sommes encaissées avant la fin du mandat restent
dues au propriétaire, déduction faite de la commission acquise et des dépenses
engagées ; le décompte final lui est remis.</p>

<h2>4. Commission</h2>
<p>La rémunération de Kaikun 360 est un <strong>pourcentage du loyer
effectivement encaissé</strong>, dont le taux est fixé au mandat. Elle n'est due
que sur les loyers réellement perçus : un loyer impayé ne génère aucune
commission.</p>

<h2>5. Ce que Kaikun 360 s'engage à faire</h2>
<ul>
  <li>Encaisser les loyers et charges, et en assurer le suivi échéance par
  échéance, impayés compris.</li>
  <li>Signaler et suivre les <strong>incidents</strong> jusqu'à leur clôture.</li>
  <li>Engager les <strong>dépenses</strong> d'entretien et de réparation dans la
  limite convenue au mandat, et au-delà après accord du propriétaire.</li>
  <li>Établir un <strong>rapport mensuel</strong> : loyers encaissés, loyers
  impayés, dépenses, commission, et net revenant au propriétaire.</li>
  <li><strong>Reverser le net</strong> au propriétaire selon la périodicité
  convenue, chaque reversement étant tracé.</li>
</ul>
<p>Le rapport mensuel et l'historique des reversements sont consultables à tout
moment par le propriétaire depuis son espace.</p>

<h2>6. Ce que le propriétaire s'engage à faire</h2>
<ul>
  <li>Justifier de sa qualité de propriétaire et de la disponibilité juridique
  du bien.</li>
  <li>Remettre le bien en état d'être loué et signaler les vices connus.</li>
  <li>Maintenir les assurances à sa charge.</li>
  <li>Ne pas louer le bien en parallèle ni percevoir directement les loyers
  pendant la durée du mandat, ce qui fausserait les comptes.</li>
  <li>Répondre dans un délai raisonnable aux demandes d'accord sur les dépenses
  qui excèdent la limite convenue.</li>
</ul>

<h2>7. Comptes et solde négatif</h2>
<p>Le net reversé est le total encaissé, diminué de la commission et des
dépenses. Un mois de travaux importants peut produire un <strong>net
négatif</strong> : la somme reste alors due par le propriétaire et se compense
sur les mois suivants, sauf régularisation convenue.</p>

<h2>8. Responsabilité</h2>
<p>Kaikun 360 est tenue d'une obligation de moyens : elle met en œuvre les
diligences d'un gestionnaire professionnel. Elle ne garantit ni l'occupation
continue du bien, ni la solvabilité du locataire, et ne répond pas des dommages
causés par ce dernier au-delà des garanties souscrites.</p>

<h2>9. Droit applicable</h2>
<p>Le mandat est soumis au <strong>droit sénégalais</strong>. Les différends
relèvent, à défaut d'accord amiable, des juridictions compétentes de Dakar.</p>

<p class="page-note"><em>Version de travail. Ce document est en cours de
validation par un conseil juridique sénégalais ; sa version définitive
prévaudra.</em></p>
HTML,
            ],
            'politique-annulation-remboursement' => [
                'title' => "Politique d'annulation, de remboursement et de caution",
                'body' => <<<'HTML'
<p>Cette page décrit ce qui se passe lorsqu'une réservation est annulée : ce qui
est remboursé, dans quel délai, et ce qu'il advient de la caution. Elle fait
partie intégrante des <a href="/pages/cgv">conditions générales de vente et de
service</a>.</p>

<h2>Le principe</h2>
<p>Plus une annulation est précoce, plus elle est sans conséquence. Chaque
univers a son propre délai, parce qu'un départ en circuit ne se replace pas
comme une journée de location.</p>

<h2>Circuits et expériences touristiques</h2>
<p>L'annulation ouvre droit au <strong>remboursement intégral</strong> si elle
intervient <strong>au moins 7 jours avant la date de départ</strong>. Passé ce
délai, le montant reste acquis : les places libérées trop tard ne se revendent
plus et les engagements pris auprès des prestataires sont fermes.</p>
<p>Les places annulées sont immédiatement remises à la disposition d'autres
voyageurs.</p>

<h2>Location de véhicules</h2>
<p>L'annulation est <strong>conforme</strong> si elle intervient <strong>au moins
2 jours avant le début de la location</strong> : la caution est alors
intégralement restituée. En deçà de ce délai, l'annulation est tardive et la
caution est conservée à titre d'indemnité.</p>
<p>Le véhicule est libéré sur la période concernée dès l'annulation enregistrée.</p>

<h2>Nuitées et séjours</h2>
<p>Un séjour ne s'annule pas depuis votre espace : chaque logement ayant ses
propres contraintes d'exploitation, l'annulation passe par notre équipe, qui
arbitre avec l'hôte et vous confirme par écrit la suite donnée. Écrivez à
<a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a> ou depuis la
messagerie rattachée à votre réservation.</p>

<h2>Prestations sur mesure</h2>
<p>Construction, gestion locative, accompagnement diaspora et team building
suivent le calendrier et les jalons portés au <strong>devis accepté</strong>.
Les sommes correspondant aux travaux ou prestations déjà engagés restent dues.
Les conditions particulières du devis prévalent sur la présente page.</p>

<h2>Le sort de la caution</h2>
<p>La caution connaît <strong>trois issues</strong>, et seulement trois :</p>
<ul>
  <li><strong>Bloquée</strong> pendant la prestation.</li>
  <li><strong>Restituée</strong> intégralement en fin de prestation sans
  incident, ou après une annulation conforme.</li>
  <li><strong>Conservée</strong> en cas d'incident ou d'annulation tardive.</li>
</ul>
<p>Il n'existe pas de restitution <em>partielle</em> de caution : lorsqu'un
dommage doit être chiffré, il fait l'objet d'un décompte distinct présenté au
client, la caution étant traitée selon l'une des trois issues ci-dessus.</p>

<h2>Comment un remboursement est exécuté</h2>
<p>Un remboursement est reversé <strong>sur le moyen de paiement d'origine</strong>
— la carte ou le compte mobile money utilisé — et porte sur la
<strong>totalité</strong> de la somme concernée : notre prestataire de paiement
ne permet pas de rembourser une fraction d'une transaction. Un geste commercial
d'un montant différent prend donc une autre forme, convenue avec vous.</p>
<p>Comptez de <strong>3 à 10 jours ouvrés</strong> entre notre ordre de
remboursement et l'apparition des fonds sur votre compte, ce délai dépendant de
votre banque ou de votre opérateur.</p>

<h2>Annulation de notre côté</h2>
<p>Si une prestation est annulée par le partenaire ou par Kaikun 360 — véhicule
immobilisé, logement indisponible, circuit annulé —, vous êtes remboursé
<strong>intégralement</strong>, sans condition de délai, et nous vous proposons
une solution de remplacement lorsque c'est possible.</p>

<h2>Un désaccord ?</h2>
<p>Écrivez-nous depuis la messagerie de votre dossier ou à
<a href="mailto:contact@kaikun360.com">contact@kaikun360.com</a>. Chaque
réclamation reçoit une référence de suivi et une première réponse sous 72 heures
ouvrées.</p>

<p class="page-note"><em>Version de travail. Ce document est en cours de
validation par un conseil juridique sénégalais ; sa version définitive
prévaudra.</em></p>
HTML,
            ],
        ];
    }
}
