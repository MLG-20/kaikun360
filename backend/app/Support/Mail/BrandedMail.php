<?php

namespace App\Support\Mail;

use Illuminate\Notifications\Messages\MailMessage;

/**
 * Constructeur des e-mails de marque Kaikun 360.
 *
 * POURQUOI CETTE CLASSE ?
 * -----------------------
 * Par défaut, Laravel envoie ses notifications avec un gabarit générique
 * (« Hello! … Regards, Laravel ») qui ne porte AUCUNE identité. Or l'e-mail est
 * aujourd'hui notre seul canal de communication réellement maîtrisé : sa qualité
 * conditionne directement la confiance que l'utilisateur accorde à la
 * plateforme. On remplace donc ce gabarit par un système maison.
 *
 * PRINCIPE : chaque notification ne décrit QUE son CONTENU (des données
 * structurées : un titre, des faits, un bouton, des étapes…). La MISE EN FORME
 * est faite une seule fois, par deux gabarits :
 *   - resources/views/emails/branded.blade.php       (version HTML)
 *   - resources/views/emails/branded-text.blade.php  (version texte brut)
 *
 * Conséquences directes :
 *   1. Les 14 e-mails de la plateforme sont VISUELLEMENT IDENTIQUES, sans avoir
 *      à recopier 14 fois le même HTML.
 *   2. Retoucher la charte = modifier UN fichier.
 *   3. Chaque e-mail part en « multipart » (HTML + texte). C'est une exigence
 *      forte de délivrabilité : un envoi HTML-seul est un signal de spam pour
 *      Gmail, et certains clients (montres, lecteurs d'écran, messageries
 *      d'entreprise verrouillées) n'affichent QUE la version texte.
 *
 * USAGE TYPE (dans un toMail()) :
 *
 *   return BrandedMail::make()
 *       ->subject('Réservation confirmée')
 *       ->preheader('Votre séjour est réservé, voici le récapitulatif.')
 *       ->eyebrow('Réservation')
 *       ->tone('success')
 *       ->heading('C\'est confirmé.')
 *       ->intro('Bonjour Awa, votre réservation est enregistrée.')
 *       ->facts(['Référence' => 'BK-2031', 'Montant' => '120 000 FCFA'])
 *       ->action('Voir ma réservation', '/mon-espace/reservations')
 *       ->toMailMessage();
 */
class BrandedMail
{
    /** Objet de l'e-mail (ligne de sujet). */
    private string $subject = '';

    /**
     * Texte d'aperçu (« preheader »).
     *
     * C'est la ligne grise affichée par Gmail/Outlook JUSTE APRÈS l'objet, dans
     * la liste des messages. Non renseignée, elle est remplie automatiquement
     * par le début du HTML — souvent illisible (« Voir dans le navigateur… »).
     * La maîtriser augmente nettement le taux d'ouverture : c'est un second
     * objet, gratuit.
     */
    private string $preheader = '';

    /** Petit label en capitales au-dessus du titre (« RÉSERVATION »). */
    private string $eyebrow = '';

    /**
     * Tonalité, qui pilote la couleur d'accent : 'brand' (bleu, défaut),
     * 'success' (vert), 'premium' (or), 'alert' (rouge).
     */
    private string $tone = 'brand';

    /** Titre principal, en gros. */
    private string $heading = '';

    /** Paragraphes d'introduction, avant les blocs de données. */
    private array $intro = [];

    /** Code à usage unique mis en évidence (vérification, 2FA). */
    private ?string $code = null;

    /** Légende sous le code (durée de validité…). */
    private string $codeCaption = '';

    /** Tableau clé → valeur (« Référence », « Montant »…). */
    private array $facts = [];

    /** Bouton d'action principal : ['label' => ..., 'url' => ...]. */
    private ?array $action = null;

    /** Lien secondaire discret sous le bouton. */
    private ?array $secondaryAction = null;

    /** Étapes numérotées (« Ce qui se passe maintenant »). */
    private array $steps = [];

    /** Titre du bloc d'étapes. */
    private string $stepsTitle = 'Ce qui se passe maintenant';

    /** Blocs mis en avant : [['title' => ..., 'body' => ...], …]. */
    private array $highlights = [];

    /** Titre du bloc de mises en avant. */
    private string $highlightsTitle = '';

    /** Encart d'information discret (fond sable). */
    private string $note = '';

    /** Encart de sécurité (bordure rouge) : à réserver aux vrais avertissements. */
    private string $security = '';

    /** Paragraphes de conclusion, après les blocs. */
    private array $outro = [];

    /** Affiche ou non le bandeau « protocole de confiance ». */
    private bool $trust = false;

    /**
     * Phrase du pied de page expliquant POURQUOI l'utilisateur reçoit cet e-mail.
     * Obligatoire d'un point de vue conformité (RGPD/anti-spam) et rassurante.
     */
    private string $reason = 'Vous recevez cet e-mail car vous disposez d\'un compte Kaikun 360.';

    /**
     * Lien « Gérer mes notifications » du pied de page. Il diffère selon
     * l'espace du destinataire, d'où le réglage via {@see self::forRecipient()}.
     */
    private string $preferencesUrl = '/mon-espace/notifications';

    public static function make(): self
    {
        return new self;
    }

    /**
     * Formate un montant en francs CFA pour un affichage lisible.
     *
     * « 120000 » n'est pas un prix : c'est une suite de chiffres que l'œil doit
     * décompter. « 120 000 FCFA » se lit d'un coup. L'espace utilisé est une
     * espace insécable, pour que le montant ne soit jamais coupé en fin de
     * ligne. Cette mise en forme vaut pour TOUS les e-mails : un même montant
     * ne doit jamais s'écrire de deux façons différentes selon le message.
     */
    public static function money(int|float|null $amount): ?string
    {
        if ($amount === null) {
            return null;
        }

        // Espace FINE insécable entre les milliers (typographie française),
        // espace insécable normale avant l'unité pour qu'elle respire.
        return number_format((float) $amount, 0, ',', "\u{202F}")."\u{00A0}FCFA";
    }

    /**
     * Formate une date en toutes lettres (« 14 septembre 2026 »).
     *
     * Le format numérique est ambigu à l'international : 09/07 se lit
     * « 9 juillet » en France et « 7 septembre » aux États-Unis. Nos
     * utilisateurs de la diaspora vivent précisément dans ces deux mondes ;
     * le mois écrit en toutes lettres supprime le risque de contresens.
     */
    public static function date(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($date)->locale('fr')->translatedFormat('j F Y');
    }

    public function subject(string $subject): self
    {
        // On suffixe systématiquement la marque : dans une boîte de réception
        // chargée, l'expéditeur ne suffit pas toujours à identifier l'émetteur.
        $this->subject = $subject.' · Kaikun 360';

        return $this;
    }

    public function preheader(string $preheader): self
    {
        $this->preheader = $preheader;

        return $this;
    }

    public function eyebrow(string $eyebrow): self
    {
        $this->eyebrow = $eyebrow;

        return $this;
    }

    public function tone(string $tone): self
    {
        $this->tone = $tone;

        return $this;
    }

    public function heading(string $heading): self
    {
        $this->heading = $heading;

        return $this;
    }

    /** Ajoute un paragraphe d'introduction (appelable plusieurs fois). */
    public function intro(string ...$paragraphs): self
    {
        foreach ($paragraphs as $paragraph) {
            $this->intro[] = $paragraph;
        }

        return $this;
    }

    public function code(string $code, string $caption = ''): self
    {
        $this->code = $code;
        $this->codeCaption = $caption;

        return $this;
    }

    /**
     * Tableau récapitulatif clé → valeur.
     *
     * @param  array<string, string|null>  $facts  Les valeurs nulles sont ignorées,
     *                                             ce qui permet d'écrire des lignes
     *                                             conditionnelles sans `if`.
     */
    public function facts(array $facts): self
    {
        foreach ($facts as $label => $value) {
            if ($value !== null && $value !== '') {
                $this->facts[$label] = (string) $value;
            }
        }

        return $this;
    }

    /**
     * Bouton d'action principal.
     *
     * Un chemin relatif (« /mon-espace ») est automatiquement préfixé par l'URL
     * publique du site : les notifications n'ont ainsi jamais à connaître le
     * domaine, qui change entre local, recette et production.
     */
    public function action(string $label, string $url): self
    {
        $this->action = ['label' => $label, 'url' => $this->absolute($url)];

        return $this;
    }

    public function secondaryAction(string $label, string $url): self
    {
        $this->secondaryAction = ['label' => $label, 'url' => $this->absolute($url)];

        return $this;
    }

    /**
     * @param  array<int, string>  $steps
     */
    public function steps(array $steps, string $title = 'Ce qui se passe maintenant'): self
    {
        $this->steps = $steps;
        $this->stepsTitle = $title;

        return $this;
    }

    /**
     * @param  array<int, array{title: string, body: string}>  $highlights
     */
    public function highlights(array $highlights, string $title = ''): self
    {
        $this->highlights = $highlights;
        $this->highlightsTitle = $title;

        return $this;
    }

    public function note(string $note): self
    {
        $this->note = $note;

        return $this;
    }

    public function security(string $security): self
    {
        $this->security = $security;

        return $this;
    }

    public function outro(string ...$paragraphs): self
    {
        foreach ($paragraphs as $paragraph) {
            $this->outro[] = $paragraph;
        }

        return $this;
    }

    public function trust(bool $trust = true): self
    {
        $this->trust = $trust;

        return $this;
    }

    public function reason(string $reason): self
    {
        $this->reason = $reason;

        return $this;
    }

    /**
     * Adapte le pied de page au destinataire.
     *
     * Concrètement : le lien « Gérer mes notifications » pointe vers l'écran de
     * SON espace (client, propriétaire, prestataire ou entreprise). Proposer un
     * lien de désabonnement qui tombe à côté est pire que de ne pas en proposer.
     */
    public function forRecipient(object $notifiable): self
    {
        $this->preferencesUrl = SpaceLink::to($notifiable, 'notifications');

        return $this;
    }

    /**
     * Transforme un chemin relatif en URL absolue vers le site public.
     */
    private function absolute(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return config('branding.frontend').'/'.ltrim($url, '/');
    }

    /**
     * Données transmises aux deux gabarits (HTML et texte).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'subject' => $this->subject,
            'preheader' => $this->preheader,
            'eyebrow' => $this->eyebrow,
            'tone' => $this->tone,
            'heading' => $this->heading,
            'intro' => $this->intro,
            'code' => $this->code,
            'codeCaption' => $this->codeCaption,
            'facts' => $this->facts,
            'action' => $this->action,
            'secondaryAction' => $this->secondaryAction,
            'steps' => $this->steps,
            'stepsTitle' => $this->stepsTitle,
            'highlights' => $this->highlights,
            'highlightsTitle' => $this->highlightsTitle,
            'note' => $this->note,
            'security' => $this->security,
            'outro' => $this->outro,
            'trust' => $this->trust,
            'reason' => $this->reason,
            'preferencesUrl' => $this->absolute($this->preferencesUrl),
            'brand' => config('branding'),
        ];
    }

    /**
     * Rend le MailMessage final : HTML + texte brut, même contenu.
     */
    public function toMailMessage(): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view(
                ['emails.branded', 'emails.branded-text'],
                $this->payload(),
            );
    }
}
