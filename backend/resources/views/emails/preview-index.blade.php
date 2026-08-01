{{--
    Sommaire des aperçus d'e-mails (local uniquement, cf. routes/web.php).
    Page d'outillage interne : volontairement sobre, elle ne doit pas détourner
    l'attention de ce qu'on vient juger — les e-mails eux-mêmes.
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Aperçu des e-mails — Kaikun 360</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0; padding: 48px 24px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #F7F4EB; color: #11213C;
        }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 { font-size: 28px; margin: 0 0 6px; letter-spacing: -0.5px; }
        p.lead { margin: 0 0 32px; color: #66738B; font-size: 15px; line-height: 24px; }
        ul { list-style: none; margin: 0; padding: 0; }
        li {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            background: #fff; border: 1px solid #EFE8D8; border-radius: 12px;
            padding: 14px 18px; margin-bottom: 8px;
        }
        a { color: #0348FB; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        .txt { color: #66738B; font-weight: 400; font-size: 13px; }
        @media (prefers-color-scheme: dark) {
            body { background: #0B1526; color: #E8EDF5; }
            li { background: #101E33; border-color: #1E3050; }
            p.lead, .txt { color: #9AA8BF; }
        }
    </style>
</head>
<body>
<div class="wrap">
    <h1>Aperçu des e-mails</h1>
    <p class="lead">
        Chaque lien ouvre l'e-mail tel qu'il sera reçu, avec des données fictives.
        Réduisez la fenêtre pour vérifier le rendu mobile, et basculez le thème de
        votre système pour contrôler le mode sombre.
    </p>
    <ul>
        @foreach ($items as $key => $label)
            <li>
                <a href="/apercu-emails/{{ $key }}">{{ $label }}</a>
                <a class="txt" href="/apercu-emails/{{ $key }}?texte=1">version texte</a>
            </li>
        @endforeach
    </ul>
</div>
</body>
</html>
