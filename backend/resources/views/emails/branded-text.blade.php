{{--
    VERSION TEXTE BRUT, rendue à partir des MÊMES données que la version HTML.

    Pourquoi la maintenir ? Un e-mail envoyé en HTML seul est un signal de spam
    reconnu (Gmail, Outlook), et une partie des destinataires ne verra jamais le
    HTML : messageries d'entreprise verrouillées, lecteurs d'écran, montres
    connectées, clients en mode « texte seul ». Comme elle est générée
    automatiquement à partir du même contenu, elle ne peut pas se désynchroniser.

    ⚠️ Tout passe par le helper $plain et la syntaxe {!! !!}, JAMAIS {{ }} :
    Blade échapperait alors les caractères en entités HTML, et l'apostrophe de
    « c'est » s'afficherait « c&#039;est » en plein milieu du texte. Ici, il n'y
    a aucun HTML à protéger — l'échappement ne fait que du dégât.
--}}
@php
    // Convertit un fragment (éventuellement enrichi de <strong> ou <br />) en
    // texte lisible : les <br> deviennent des retours à la ligne, les balises
    // sautent, les entités sont décodées.
    $plain = function (?string $value): string {
        $value = preg_replace('/<br\s*\/?>/i', "\n", (string) $value);

        return trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    };

    $line = str_repeat('-', 58);
@endphp
KAIKUN 360
{{ $line }}

@if ($eyebrow)
[{!! mb_strtoupper($plain($eyebrow)) !!}]

@endif
{!! $plain($heading) !!}

@foreach ($intro as $paragraph)
{!! $plain($paragraph) !!}

@endforeach
@if ($code)
Votre code : {{ $code }}
@if ($codeCaption)
{!! $plain($codeCaption) !!}
@endif

@endif
@if (! empty($facts))
{{ $line }}
@foreach ($facts as $label => $value)
{!! $plain($label) !!} : {!! $plain($value) !!}
@endforeach
{{ $line }}

@endif
@if ($action)
{!! $plain($action['label']) !!} :
{{ $action['url'] }}

@if ($secondaryAction)
{!! $plain($secondaryAction['label']) !!} : {{ $secondaryAction['url'] }}

@endif
@endif
@if (! empty($highlights))
@if ($highlightsTitle)
{!! $plain($highlightsTitle) !!}

@endif
@foreach ($highlights as $highlight)
* {!! $plain($highlight['title']) !!} — {!! $plain($highlight['body']) !!}
@endforeach

@endif
@if (! empty($steps))
{!! $plain($stepsTitle) !!}

@foreach ($steps as $index => $step)
{{ $index + 1 }}. {!! $plain($step) !!}
@endforeach

@endif
@if ($note)
{!! $plain($note) !!}

@endif
@if ($security)
/!\ {!! $plain($security) !!}

@endif
@foreach ($outro as $paragraph)
{!! $plain($paragraph) !!}

@endforeach
@if ($trust)
NOTRE ENGAGEMENT — La confiance n'est pas une promesse. C'est un protocole.
* Vérification documentée : titres, actes et identités contrôlés avec notaire et géomètre.
* Tout est filmé, daté, archivé : visites et étapes de chantier documentées.
* Un numéro de suivi unique : chaque dossier se suit depuis votre espace.

@endif
L'équipe {{ $brand['name'] }}

{{ $line }}
Une question ? Répondez à cet e-mail ou écrivez à {{ $brand['support_email'] }}
Téléphone : {{ $brand['support_phone'] }}

{!! $plain($reason) !!}
Gérer mes notifications : {{ $preferencesUrl }}
Confidentialité : {{ $brand['frontend'] }}/pages/politique-confidentialite

{{ $brand['name'] }} — {{ $brand['address'] }}
(c) {{ date('Y') }} {{ $brand['name'] }}. Tous droits réservés.
