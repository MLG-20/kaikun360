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

## Ce qui reste hors de ce chantier

- **TLS** : nginx écoute en clair sur le port 80 — le VPS (Contabo) termine
  HTTPS en amont (reverse proxy / certificat), voir la mémoire de
  déploiement.
- **CD** (publication automatique sur le VPS) : pas encore fait, c'est
  l'étape suivante une fois ce socle Docker validé.
