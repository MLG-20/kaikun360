{{--
    GABARIT HTML UNIQUE de tous les e-mails Kaikun 360.

    Il reçoit les données structurées produites par App\Support\Mail\BrandedMail
    et les met en forme. Aucun autre fichier ne contient de HTML d'e-mail : c'est
    ICI, et seulement ici, que se règle l'apparence des 14 messages.

    CONTRAINTES TECHNIQUES DE L'E-MAIL (elles expliquent le style du code) :
      · Mise en page en <table> : Outlook (moteur Word) ne sait pas faire de
        flexbox ni de grid. Une div en colonnes casse.
      · Styles « inline » : Gmail supprime une partie du <style>, et l'ignore
        totalement dans son application mobile Android. Tout ce qui est vital
        (couleurs, marges, tailles) est donc écrit dans l'attribut style.
      · Le <style> ne sert qu'aux AMÉLIORATIONS optionnelles : responsive et
        mode sombre. Si un client l'ignore, l'e-mail reste parfaitement lisible.
      · Aucune image distante : les messageries bloquent les images par défaut.
        Le logo est composé en typographie, donc toujours visible.
      · Largeur 600 px : la largeur sûre historique des volets de lecture.
--}}
@php
    $c = $brand['colors'];

    // Couleur d'accent selon la tonalité du message. Le filet supérieur, le
    // label et le bouton s'y alignent : l'utilisateur identifie la nature du
    // message (info / succès / sécurité) avant même de lire.
    $accent = match ($tone) {
        'success' => $c['green'],
        'premium' => $c['gold'],
        'alert' => $c['danger'],
        default => $c['blue'],
    };

    // Police : uniquement des familles présentes sur les OS. Les polices web
    // (Inter…) ne sont chargées par presque aucun client de messagerie ; on
    // s'appuie donc sur la pile système, très proche visuellement.
    $font = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";
    $mono = "'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace";
@endphp
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    {{-- Autorise le rendu en mode sombre au lieu d'une inversion automatique brutale. --}}
    <meta name="color-scheme" content="light dark" />
    <meta name="supported-color-schemes" content="light dark" />
    <title>{{ $subject }}</title>
    <style type="text/css">
        /* Réinitialisations indispensables selon les clients de messagerie. */
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; }
        table { border-collapse: collapse !important; }
        img { border: 0; outline: none; text-decoration: none; }
        /* Empêche iOS de transformer dates et numéros en liens bleus. */
        a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
        /* Outlook.com force sa propre police : on la reprend. */
        .ExternalClass { width: 100%; }

        /* --- Téléphone : on resserre les marges et on réduit le titre. --- */
        @media only screen and (max-width: 620px) {
            .kk-shell { width: 100% !important; }
            .kk-pad { padding-left: 24px !important; padding-right: 24px !important; }
            .kk-title { font-size: 26px !important; line-height: 32px !important; }
            .kk-code { font-size: 30px !important; letter-spacing: 8px !important; }
            /* Les colonnes du tableau récapitulatif passent l'une sous l'autre. */
            .kk-stack, .kk-stack td { display: block !important; width: 100% !important; }
            .kk-stack td { padding: 2px 0 !important; text-align: left !important; }
            .kk-btn a { display: block !important; }
        }

        /* --- Mode sombre : on repeint plutôt que de laisser le client inverser. --- */
        @media (prefers-color-scheme: dark) {
            .kk-bg { background-color: #0B1526 !important; }
            .kk-card { background-color: #101E33 !important; border-color: #1E3050 !important; }
            .kk-text { color: #E8EDF5 !important; }
            .kk-muted { color: #9AA8BF !important; }
            .kk-soft { background-color: #16263F !important; border-color: #24395C !important; }
            .kk-footer { background-color: #0B1526 !important; }
            .kk-hr { border-color: #24395C !important; }
        }
    </style>
</head>
<body class="kk-bg" style="margin:0; padding:0; background-color:{{ $c['cream'] }}; -webkit-font-smoothing:antialiased;">

{{--
    Texte d'aperçu : invisible dans le message, mais lu par la boîte de
    réception juste après l'objet. Les caractères invisibles qui suivent
    « poussent » le contenu HTML hors de l'aperçu, sinon Gmail y afficherait
    les premiers mots du gabarit.
--}}
<div style="display:none; font-size:1px; color:{{ $c['cream'] }}; line-height:1px; max-height:0; max-width:0; opacity:0; overflow:hidden;">
    {{ $preheader ?: $heading }}
    &#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kk-bg" style="background-color:{{ $c['cream'] }};">
    <tr>
        <td align="center" style="padding:32px 12px 40px 12px;">

            <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" class="kk-shell" style="width:600px; max-width:600px;">

                {{-- ============ EN-TÊTE DE MARQUE ============ --}}
                <tr>
                    <td style="padding:0 0 18px 0;" align="center">
                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                {{-- Monogramme : carré navy au « K » doré. Composé en HTML, donc
                                     jamais bloqué par le filtre d'images de la messagerie. --}}
                                <td style="background-color:{{ $c['navy'] }}; width:38px; height:38px; border-radius:10px; text-align:center; vertical-align:middle; font-family:{{ $font }}; font-size:19px; font-weight:700; color:{{ $c['gold'] }}; line-height:38px;">K</td>
                                <td style="padding-left:11px; font-family:{{ $font }}; font-size:17px; font-weight:700; letter-spacing:0.5px; color:{{ $c['navy'] }};" class="kk-text">
                                    KAIKUN&nbsp;360
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ============ CARTE PRINCIPALE ============ --}}
                <tr>
                    <td class="kk-card" style="background-color:{{ $c['white'] }}; border:1px solid {{ $c['sand'] }}; border-radius:18px; overflow:hidden;">

                        {{-- Filet d'accent en haut de la carte : la seule touche de couleur
                             franche, qui signe la nature du message. --}}
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr><td style="background-color:{{ $accent }}; height:4px; line-height:4px; font-size:0;">&nbsp;</td></tr>
                        </table>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                            <tr>
                                <td class="kk-pad" style="padding:36px 40px 8px 40px;">

                                    @if ($eyebrow)
                                        <p style="margin:0 0 12px 0; font-family:{{ $mono }}; font-size:11px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:{{ $accent }};">
                                            {{ $eyebrow }}
                                        </p>
                                    @endif

                                    <h1 class="kk-title kk-text" style="margin:0 0 18px 0; font-family:{{ $font }}; font-size:30px; line-height:37px; font-weight:700; letter-spacing:-0.5px; color:{{ $c['ink'] }};">
                                        {{ $heading }}
                                    </h1>

                                    @foreach ($intro as $paragraph)
                                        <p class="kk-text" style="margin:0 0 16px 0; font-family:{{ $font }}; font-size:16px; line-height:26px; color:{{ $c['ink'] }};">
                                            {!! $paragraph !!}
                                        </p>
                                    @endforeach
                                </td>
                            </tr>

                            {{-- ---- CODE À USAGE UNIQUE ---- --}}
                            @if ($code)
                                <tr>
                                    <td class="kk-pad" style="padding:12px 40px 8px 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kk-soft" style="background-color:{{ $c['cream'] }}; border:1px solid {{ $c['sand'] }}; border-radius:14px;">
                                            <tr>
                                                <td align="center" style="padding:26px 16px 22px 16px;">
                                                    {{-- Chiffres très espacés : l'utilisateur recopie
                                                         le code à la main, la lisibilité prime. --}}
                                                    <div class="kk-code kk-text" style="font-family:{{ $mono }}; font-size:38px; font-weight:700; letter-spacing:11px; color:{{ $c['navy'] }}; padding-left:11px;">
                                                        {{ $code }}
                                                    </div>
                                                    @if ($codeCaption)
                                                        <p class="kk-muted" style="margin:14px 0 0 0; font-family:{{ $font }}; font-size:13px; line-height:20px; color:{{ $c['muted'] }};">
                                                            {{ $codeCaption }}
                                                        </p>
                                                    @endif
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- RÉCAPITULATIF CLÉ / VALEUR ---- --}}
                            @if (! empty($facts))
                                <tr>
                                    <td class="kk-pad" style="padding:12px 40px 8px 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kk-soft" style="background-color:{{ $c['cream'] }}; border:1px solid {{ $c['sand'] }}; border-radius:14px;">
                                            <tr>
                                                <td style="padding:8px 22px;">
                                                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                                        @foreach ($facts as $label => $value)
                                                            <tr class="kk-stack">
                                                                <td class="kk-muted" style="padding:11px 0; font-family:{{ $font }}; font-size:14px; line-height:20px; color:{{ $c['muted'] }}; border-bottom:{{ $loop->last ? 'none' : '1px solid '.$c['sand'] }};">
                                                                    {{ $label }}
                                                                </td>
                                                                <td align="right" class="kk-text" style="padding:11px 0; font-family:{{ $font }}; font-size:14px; line-height:20px; font-weight:600; color:{{ $c['ink'] }}; border-bottom:{{ $loop->last ? 'none' : '1px solid '.$c['sand'] }};">
                                                                    {{ $value }}
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- BOUTON D'ACTION ---- --}}
                            @if ($action)
                                <tr>
                                    <td class="kk-pad" align="center" style="padding:26px 40px 6px 40px;">
                                        {{-- Bouton « à toute épreuve » : une cellule de tableau colorée
                                             plutôt qu'un <a> stylé, seule technique qui tienne sur
                                             Outlook. Forme pilule (rayon 999px) = charte du site. --}}
                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0" class="kk-btn">
                                            <tr>
                                                <td align="center" style="background-color:{{ $accent }}; border-radius:999px;">
                                                    <a href="{{ $action['url'] }}" target="_blank" style="display:inline-block; padding:15px 38px; font-family:{{ $font }}; font-size:15px; font-weight:600; color:#FFFFFF; text-decoration:none; border-radius:999px;">
                                                        {{ $action['label'] }}
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>

                                @if ($secondaryAction)
                                    <tr>
                                        <td class="kk-pad" align="center" style="padding:14px 40px 0 40px;">
                                            <a href="{{ $secondaryAction['url'] }}" target="_blank" class="kk-muted" style="font-family:{{ $font }}; font-size:14px; color:{{ $c['muted'] }}; text-decoration:underline;">
                                                {{ $secondaryAction['label'] }}
                                            </a>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Lien en toutes lettres : indispensable quand le client de
                                     messagerie n'affiche pas les boutons, et rassurant (on voit
                                     où mène le lien avant de cliquer — réflexe anti-hameçonnage). --}}
                                <tr>
                                    <td class="kk-pad" align="center" style="padding:16px 40px 0 40px;">
                                        <p class="kk-muted" style="margin:0; font-family:{{ $font }}; font-size:12px; line-height:19px; color:{{ $c['muted'] }}; word-break:break-all;">
                                            Le bouton ne fonctionne pas ? Copiez ce lien :<br />
                                            <span style="color:{{ $c['muted'] }};">{{ $action['url'] }}</span>
                                        </p>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- BLOCS MIS EN AVANT ---- --}}
                            @if (! empty($highlights))
                                <tr>
                                    <td class="kk-pad" style="padding:28px 40px 4px 40px;">
                                        @if ($highlightsTitle)
                                            <p class="kk-text" style="margin:0 0 16px 0; font-family:{{ $font }}; font-size:15px; font-weight:700; color:{{ $c['ink'] }};">
                                                {{ $highlightsTitle }}
                                            </p>
                                        @endif
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            @foreach ($highlights as $highlight)
                                                <tr>
                                                    <td style="padding:0 0 10px 0;">
                                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kk-soft" style="background-color:{{ $c['cream'] }}; border:1px solid {{ $c['sand'] }}; border-radius:12px;">
                                                            <tr>
                                                                <td style="padding:16px 20px;">
                                                                    <p class="kk-text" style="margin:0 0 5px 0; font-family:{{ $font }}; font-size:15px; font-weight:700; color:{{ $c['navy'] }};">
                                                                        {{ $highlight['title'] }}
                                                                    </p>
                                                                    <p class="kk-muted" style="margin:0; font-family:{{ $font }}; font-size:14px; line-height:22px; color:{{ $c['muted'] }};">
                                                                        {{ $highlight['body'] }}
                                                                    </p>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- ÉTAPES NUMÉROTÉES ---- --}}
                            @if (! empty($steps))
                                <tr>
                                    <td class="kk-pad" style="padding:30px 40px 4px 40px;">
                                        <p class="kk-text" style="margin:0 0 16px 0; font-family:{{ $font }}; font-size:15px; font-weight:700; color:{{ $c['ink'] }};">
                                            {{ $stepsTitle }}
                                        </p>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            @foreach ($steps as $index => $step)
                                                <tr>
                                                    {{-- Pastille numérotée : repère visuel du parcours. --}}
                                                    <td width="30" valign="top" style="padding:0 12px 16px 0;">
                                                        <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                                                            <tr>
                                                                <td width="24" height="24" align="center" style="background-color:{{ $c['navy'] }}; border-radius:12px; font-family:{{ $font }}; font-size:12px; font-weight:700; color:{{ $c['gold_soft'] }}; line-height:24px;">
                                                                    {{ $index + 1 }}
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    </td>
                                                    <td valign="top" class="kk-text" style="padding:0 0 16px 0; font-family:{{ $font }}; font-size:15px; line-height:24px; color:{{ $c['ink'] }};">
                                                        {!! $step !!}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- ENCART D'INFORMATION ---- --}}
                            @if ($note)
                                <tr>
                                    <td class="kk-pad" style="padding:20px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" class="kk-soft" style="background-color:{{ $c['cream'] }}; border-left:3px solid {{ $c['gold'] }}; border-radius:0 10px 10px 0;">
                                            <tr>
                                                <td class="kk-muted" style="padding:14px 18px; font-family:{{ $font }}; font-size:14px; line-height:22px; color:{{ $c['muted'] }};">
                                                    {{ $note }}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- ENCART DE SÉCURITÉ ---- --}}
                            @if ($security)
                                <tr>
                                    <td class="kk-pad" style="padding:20px 40px 0 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#FDF3F2; border:1px solid #F3D9D6; border-radius:10px;">
                                            <tr>
                                                <td style="padding:14px 18px; font-family:{{ $font }}; font-size:14px; line-height:22px; color:{{ $c['danger'] }};">
                                                    {!! $security !!}
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- CONCLUSION ---- --}}
                            @if (! empty($outro))
                                <tr>
                                    <td class="kk-pad" style="padding:24px 40px 0 40px;">
                                        @foreach ($outro as $paragraph)
                                            <p class="kk-text" style="margin:0 0 14px 0; font-family:{{ $font }}; font-size:15px; line-height:25px; color:{{ $c['ink'] }};">
                                                {!! $paragraph !!}
                                            </p>
                                        @endforeach
                                    </td>
                                </tr>
                            @endif

                            {{-- ---- SIGNATURE ---- --}}
                            <tr>
                                <td class="kk-pad" style="padding:22px 40px 36px 40px;">
                                    <p class="kk-muted" style="margin:0; font-family:{{ $font }}; font-size:15px; line-height:24px; color:{{ $c['muted'] }};">
                                        L'équipe {{ $brand['name'] }}
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- ============ BANDEAU « PROTOCOLE DE CONFIANCE » ============
                     Réservé aux e-mails d'accueil : c'est le positionnement de la
                     plateforme (lutte contre les arnaques immobilières, enjeu
                     majeur pour la diaspora). Il n'a pas sa place sur un simple
                     accusé de réception, sous peine de devenir du bruit. --}}
                @if ($trust)
                    <tr>
                        <td style="padding:18px 0 0 0;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:{{ $c['navy'] }}; border-radius:18px;">
                                <tr>
                                    <td class="kk-pad" style="padding:30px 40px 12px 40px;">
                                        <p style="margin:0 0 6px 0; font-family:{{ $mono }}; font-size:11px; font-weight:700; letter-spacing:1.6px; text-transform:uppercase; color:{{ $c['gold'] }};">
                                            Notre engagement
                                        </p>
                                        <p style="margin:0 0 20px 0; font-family:{{ $font }}; font-size:20px; line-height:29px; font-weight:700; color:#FFFFFF;">
                                            La confiance n'est pas une promesse.<br />C'est un protocole.
                                        </p>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="kk-pad" style="padding:0 40px 32px 40px;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            @foreach ([
                                                ['Vérification documentée', 'Titres, actes et identités contrôlés avec notaire et géomètre avant toute mise en ligne.'],
                                                ['Tout est filmé, daté, archivé', 'Visites et étapes de chantier documentées : vous voyez ce qui se passe, où que vous soyez.'],
                                                ['Un numéro de suivi unique', 'Chaque dossier a sa référence. Vous suivez son avancement à tout moment depuis votre espace.'],
                                            ] as $pillar)
                                                <tr>
                                                    <td width="18" valign="top" style="padding:0 12px 14px 0; font-family:{{ $font }}; font-size:15px; color:{{ $c['gold'] }}; line-height:23px;">&#10003;</td>
                                                    <td valign="top" style="padding:0 0 14px 0;">
                                                        <p style="margin:0 0 3px 0; font-family:{{ $font }}; font-size:15px; font-weight:600; color:#FFFFFF; line-height:23px;">{{ $pillar[0] }}</p>
                                                        <p style="margin:0; font-family:{{ $font }}; font-size:14px; line-height:22px; color:#B9C6DC;">{{ $pillar[1] }}</p>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif

                {{-- ============ PIED DE PAGE ============ --}}
                <tr>
                    <td class="kk-footer kk-pad" style="padding:26px 40px 0 40px;" align="center">
                        <p class="kk-muted" style="margin:0 0 10px 0; font-family:{{ $font }}; font-size:13px; line-height:21px; color:{{ $c['muted'] }};">
                            Une question ? Répondez simplement à cet e-mail, ou écrivez-nous à
                            <a href="mailto:{{ $brand['support_email'] }}" style="color:{{ $c['blue'] }}; text-decoration:none;">{{ $brand['support_email'] }}</a>
                            &nbsp;·&nbsp; {{ $brand['support_phone'] }}
                        </p>
                        <p class="kk-muted" style="margin:0 0 14px 0; font-family:{{ $font }}; font-size:12px; line-height:20px; color:{{ $c['muted'] }};">
                            {{ $reason }}
                            <br />
                            <a href="{{ $preferencesUrl }}" style="color:{{ $c['muted'] }}; text-decoration:underline;">Gérer mes notifications</a>
                            &nbsp;·&nbsp;
                            <a href="{{ $brand['frontend'] }}/pages/politique-confidentialite" style="color:{{ $c['muted'] }}; text-decoration:underline;">Confidentialité</a>
                            &nbsp;·&nbsp;
                            <a href="{{ $brand['frontend'] }}/pages/mentions-legales" style="color:{{ $c['muted'] }}; text-decoration:underline;">Mentions légales</a>
                        </p>
                        <p class="kk-muted" style="margin:0; font-family:{{ $font }}; font-size:12px; line-height:20px; color:{{ $c['muted'] }};">
                            {{ $brand['name'] }} — {{ $brand['address'] }}<br />
                            &copy; {{ date('Y') }} {{ $brand['name'] }}. Tous droits réservés.
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
