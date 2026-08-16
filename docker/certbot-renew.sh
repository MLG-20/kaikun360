#!/bin/sh
# Renouvellement du certificat Let's Encrypt, à lancer périodiquement (cron)
# sur le VPS — pas dans docker-compose.yml, car c'est une tâche ponctuelle,
# pas un service qui tourne en continu. Certbot ne renouvelle réellement que
# dans les 30 jours avant expiration ; les autres jours cette commande ne
# fait rien (idempotent, sans risque à lancer tous les jours).
#
# Utilisation (crontab sur le VPS) :
#   17 3 * * * /opt/kaikun360/docker/certbot-renew.sh >> /var/log/certbot-renew.log 2>&1
set -eu

docker run --rm \
  -v kaikun360_certbot-webroot:/var/www/certbot \
  -v kaikun360_certbot-certs:/etc/letsencrypt \
  certbot/certbot renew --webroot -w /var/www/certbot --quiet

# Recharge nginx pour qu'il prenne en compte un éventuel nouveau certificat
# (sans interruption de service, contrairement à un restart).
docker exec kaikun360-nginx-1 nginx -s reload
