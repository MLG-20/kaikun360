#!/bin/sh
# Point d'entrée commun aux 3 services backend (php-fpm, scheduler, worker de
# file). Seul le service "backend" doit migrer et lier le disque public — les
# deux autres démarrent avec RUN_MIGRATIONS non défini et sautent ce bloc,
# sinon 3 conteneurs migreraient en même temps au premier démarrage.
set -e

# Le lien public/storage est créé au BUILD de l'image (voir Dockerfile) : il
# ne dépend d'aucune donnée d'exécution et www-data n'a pas le droit d'écrire
# dans public/ pour le refaire ici.
if [ "$RUN_MIGRATIONS" = "true" ]; then
    php artisan migrate --force
    # DatabaseSeeder ne pose que les rôles/permissions + la géographie
    # sénégalaise — indispensable au fonctionnement (l'inscription échoue en
    # 500 sans le rôle "client"), jamais de données de démonstration. Idempotent
    # (firstOrCreate), donc sûr à rejouer à chaque démarrage.
    php artisan db:seed --force
fi

exec "$@"
