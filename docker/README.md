# `docker/` — conteneurisation de Kaikun360

Sept services orchestrés par `docker-compose.yml` (à la racine du dépôt) :

| Service | Rôle | Image |
| --- | --- | --- |
| `mysql` | Base de données | `mysql:8.0` |
| `redis` | Cache, session, file d'attente | `redis:7-alpine` |
| `backend` | API Laravel (PHP-FPM) | `docker/backend/Dockerfile` |
| `scheduler` | `php artisan schedule:work` — **obligatoire**, sans lui aucune clôture de réservation ni reversement | même image que `backend` |
| `queue-worker` | `php artisan queue:work` — traite les jobs (e-mails, notifications…) postés sur Redis | même image que `backend` |
| `frontend` | Site Angular en **SSR** (obligatoire depuis F9.1, jamais de `dist/` statique seul) | `docker/frontend/Dockerfile` |
| `nginx` | Point d'entrée unique : `/api`, `/storage`, `/sitemap.xml` → backend, tout le reste → frontend | `nginx:alpine` + `docker/nginx/default.conf` |

Le découpage `/api` + `/storage` + `/sitemap.xml` → backend, reste → frontend
reproduit exactement le proxy de développement (`frontend/proxy.conf.json`) :
le visiteur ne voit **qu'une seule origine**, comme en production réelle.

## Démarrage

```bash
cp .env.docker.example .env.docker   # puis remplir les valeurs (voir le fichier, chaque ligne est commentée)
docker compose --env-file .env.docker up -d --build
```

Au premier démarrage, le service `backend` migre la base **et** lance
`DatabaseSeeder` (rôles/permissions + géographie sénégalaise — indispensable,
sans lui l'inscription échoue en 500 faute du rôle `client`). Aucune donnée de
démonstration n'est chargée automatiquement.

Le site est ensuite sur `http://localhost:${HTTP_PORT:-80}`.

## Pièges rencontrés en mettant ça au point (à ne pas refaire)

- ⚠️ **PHP 8.4, pas 8.3** : `composer.json` demande `^8.3`, mais le
  `composer.lock` verrouille des paquets (`symfony/*` v8.1, `laravel/framework`
  v13.17) qui exigent en réalité `>=8.4.1`. Une image `php:8.3-fpm-alpine`
  échoue dès `composer install`.
- ⚠️ **Client `mysql` absent de `php:*-fpm-alpine`** : `php artisan migrate`
  charge le schéma figé (`database/schema/mysql-schema.sql` — squashing des
  migrations mis en place pour accélérer les tests, voir le README backend)
  via un `mysql < fichier.sql` en sous-processus — pas du PDO. Sans le paquet
  `mariadb-client`, ce chargement échoue en silence au tout premier démarrage
  (`sh: mysql: not found`).
- ⚠️ **TLS entre le client mariadb et MySQL 8** : le client mariadb de l'image
  Alpine exige TLS par défaut, MySQL 8 se présente avec un certificat
  auto-signé → `TLS/SSL error: self-signed certificate`. Corrigé des DEUX
  côtés : `mysql` tourne avec `--skip-ssl`, et `/etc/my.cnf.d/no-ssl.cnf`
  force `ssl=0` côté client (l'option moderne `ssl-mode=DISABLED` n'est **pas**
  reconnue par ce client-là — `unknown variable`).
- ⚠️ **`caching_sha2_password`** (authentification par défaut de MySQL 8) :
  le plugin correspondant n'existe pas dans le client mariadb Alpine
  (`Error loading shared library .../caching_sha2_password.so`). Le service
  `mysql` force `--default-authentication-plugin=mysql_native_password`.
- ⚠️ **`storage:link` en écriture, `www-data` n'a pas le droit** : le
  répertoire `public/` appartient à `root` (créé pendant le build, avant
  `USER www-data`). Le lien symbolique `public/storage → ../storage/app/public`
  est donc créé **au build de l'image** (en root, dans le Dockerfile), jamais
  au démarrage du conteneur.
- ⚠️ **nginx met en cache l'IP de `backend`/`frontend` UNE SEULE FOIS**, au
  démarrage — via un `upstream {}` classique, résolu une fois pour toutes.
  Recréer le conteneur `backend` (déploiement, redémarrage après incident)
  change son IP interne et nginx continue de taper sur l'ancienne → **502
  jusqu'à un `docker compose restart nginx` manuel**. Corrigé en passant par
  `resolver 127.0.0.11 valid=10s;` + des `set $backend_fpm …` /
  `set $frontend_ssr …` : nginx redemande l'IP à Docker à chaque expiration
  du TTL, sans jamais avoir besoin d'être relancé lui-même.
- ⚠️ **Le serveur SSR Angular refuse tout `Host` non explicitement autorisé**
  (protection anti-SSRF intégrée à `@angular/ssr`, indépendante de notre
  code) : `curl http://<IP ou domaine du serveur>/` renvoie **400** « Header
  "host" ... is not allowed », visible uniquement dans les logs du conteneur
  `frontend`, AVANT même que le routeur Angular ne s'exécute. Corrigé par la
  variable `NG_ALLOWED_HOSTS` (voir `.env.docker.example`) — à tenir à jour à
  **chaque changement d'adresse publique** (IP de test, sous-domaine, puis
  `kaikun360.com` au jour de la bascule).
- ⚠️ **`MAIL_MAILER=log` n'écrit RIEN, en silence, dès que `LOG_LEVEL` dépasse
  `debug`.** `Illuminate\Mail\Transport\LogTransport` écrit le contenu de
  chaque e-mail avec `Logger::debug(...)` — avec `LOG_LEVEL=info` (le réglage
  de test VPS), ce niveau est filtré et l'e-mail disparaît sans erreur nulle
  part. Trouvé le 2026-08-15 en cherchant un code de vérification 2FA
  introuvable dans `storage/logs/laravel.log`. Corrigé par un canal dédié à
  niveau `debug` **figé** (`config/logging.php → mail_debug`,
  `storage/logs/mail.log`), branché via `MAIL_LOG_CHANNEL=mail_debug` (voir
  `.env.docker.example`) — indépendant de `LOG_LEVEL`, sans rendre toute
  l'application bavarde pour autant.

## Un vrai bug de production, trouvé grâce à Docker (pas un défaut Docker)

Premier regard jamais posé sur `server.mjs` (le build de production) dans un
navigateur réel — jusqu'ici, tout le monde ne voyait le site qu'à travers
`ng serve` (dev). Résultat : **toute la mise en page était cassée** (liens
soulignés partout, styles quasi absents) alors que le fichier CSS se
chargeait bien (`200`, bon `Content-Type`, octet pour octet identique au
build local).

Cause : la CSP posée dans `server.ts` (revue de sécurité 2026-08) ne porte
pas `'unsafe-inline'` sur `script-src` — volontairement, pour limiter ce
qu'une XSS résiduelle pourrait faire. Or Angular, en production, injecte par
défaut le CSS non critique de façon **asynchrone** :

```html
<link rel="stylesheet" href="styles-xxx.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="styles-xxx.css"></noscript>
```

Le `onload` **inline** ne s'exécute jamais sous cette CSP → la feuille reste
en `media="print"` pour toujours, donc ignorée à l'écran. Le `<noscript>` de
secours ne sert à rien puisque JavaScript est actif (c'est une SPA Angular).

**Corrigé dans `angular.json`**, configuration `production` :
`optimization.styles.inlineCritical: false` — Angular émet alors un `<link>`
synchrone classique, sans handler inline, compatible avec la CSP stricte
telle quelle (pas d'affaiblissement de la CSP pour contourner le problème).

## TLS (Let's Encrypt) — FAIT (2026-08-16)

`nginx` écoute en 80 **et** 443, avec un certificat Let's Encrypt réel pour
`kaikun360.com`/`www.kaikun360.com`. Deux volumes dédiés, montés en lecture
seule dans `nginx` : `certbot-webroot` (fichier de vérification du défi
HTTP, `location /.well-known/acme-challenge/` dans `default.conf`) et
`certbot-certs` (`/etc/letsencrypt`, où Certbot dépose le certificat).

Obtention initiale, **manuelle et ponctuelle** (pas un service
docker-compose — certbot ne tourne pas en continu) :

```bash
docker run --rm \
  -v kaikun360_certbot-webroot:/var/www/certbot \
  -v kaikun360_certbot-certs:/etc/letsencrypt \
  certbot/certbot certonly --webroot -w /var/www/certbot \
  -d kaikun360.com -d www.kaikun360.com \
  --email <email-du-dev> --agree-tos --no-eff-email --non-interactive
```

⚠️ **Ordre important** : le bloc `server { listen 443 ssl; ... }` dans
`default.conf` référence les fichiers de certificat — nginx refuse de
démarrer si le port 443 est activé avant que le certificat existe. D'où le
déploiement en deux temps : d'abord le port 80 seul avec la location du défi
ACME, obtention du certificat, *puis* activation du 443.

**Renouvellement automatique** : `docker/certbot-renew.sh`, à lancer par
cron sur le VPS (certificat valable 90 jours, Certbot ne renouvelle
réellement que dans les 30 derniers jours — sans risque de le lancer tous
les jours) :

```
17 3 * * * /opt/kaikun360/docker/certbot-renew.sh >> /var/log/certbot-renew.log 2>&1
```

Côté Cloudflare, passer le mode SSL/TLS de "Flexible" à **"Full (strict)"**
une fois le certificat en place — Cloudflare vérifie alors un vrai
certificat public sur le VPS plutôt que de faire confiance à n'importe quoi.
