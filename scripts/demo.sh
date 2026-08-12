#!/usr/bin/env bash
#
# =============================================================================
# Kaikun 360 — démarrage d'une DÉMONSTRATION en une commande
#
#   ./scripts/demo.sh              → site accessible publiquement (ngrok)
#   ./scripts/demo.sh --local      → uniquement sur cette machine
#   ./scripts/demo.sh --lan        → sur le réseau local (téléphone, même wifi)
#   ./scripts/demo.sh --domaine mon-site.ngrok-free.app
#
#   Tout arrêter quand Ctrl+C n'est pas possible (script lancé en arrière-plan,
#   terminal fermé) — les crochets évitent que le motif se reconnaisse lui-même :
#   pkill -f "[d]emo.sh"; pkill -f "[a]rtisan serve"; pkill -f "[n]g serve"; pkill -f "[n]grok http"
#
# Lance l'API Laravel, le site Angular et l'ouverture publique ngrok, puis
# affiche L'ADRESSE À PARTAGER. Ctrl+C arrête tout d'un coup.
#
# -----------------------------------------------------------------------------
# Pourquoi UNE seule ouverture ngrok et non deux
# -----------------------------------------------------------------------------
# On pourrait exposer séparément le site (:4200) et l'API (:8000). On ne le fait
# pas : deux adresses publiques, c'est deux domaines différents, donc du CORS à
# rouvrir, deux URL à replacer dans la configuration à chaque lancement — et le
# forfait ngrok gratuit ne tient qu'une session d'agent.
#
# Ici, TOUT passe par l'adresse du site : le serveur de développement Angular
# relaie déjà `/api` et `/storage` vers Laravel (`frontend/proxy.conf.json`).
# Le visiteur distant ne voit donc qu'une seule origine, exactement comme en
# production où Laravel sert le site et l'API ensemble. C'est aussi ce qui rend
# la démonstration FIDÈLE : on montre l'architecture de production, pas un
# montage propre au portable de développement.
#
# -----------------------------------------------------------------------------
# Les trois réglages qui font que ça marche à distance (et qu'on oublie tous)
# -----------------------------------------------------------------------------
# 1. `apiUrl` doit être RELATIVE (`/api/v1`). En développement elle vaut
#    `http://localhost:8000/api/v1` : parfait ici, absurde depuis le téléphone
#    d'un client. D'où l'environnement `demo` généré plus bas.
# 2. `APP_URL` de Laravel doit porter l'adresse PUBLIQUE : c'est elle qui
#    fabrique les URL des photos (`Storage::url()`). Restée sur localhost, le
#    site s'affiche mais toutes les images sont cassées — le pire des défauts
#    pour une présentation.
# 3. `API_ORIGIN` doit rester sur `http://localhost:8000` : c'est le rendu
#    serveur (SSR) qui l'utilise, et lui tourne sur CETTE machine. L'envoyer
#    faire le tour par ngrok ajouterait un aller-retour Internet à chaque page.
# =============================================================================

set -euo pipefail

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

PORT_API=8000     # php artisan serve
PORT_WEB=4200     # ng serve
PORT_NGROK=4040   # interface locale de ngrok (elle publie l'URL du tunnel)

# --- Couleurs (désactivées si la sortie n'est pas un terminal) ---------------
if [ -t 1 ]; then
  C_TITRE=$'\e[1;38;5;33m'; C_OK=$'\e[32m'; C_ERR=$'\e[31m'
  C_API=$'\e[35m'; C_WEB=$'\e[36m'; C_GRIS=$'\e[90m'; C_RAZ=$'\e[0m'
else
  C_TITRE=''; C_OK=''; C_ERR=''; C_API=''; C_WEB=''; C_GRIS=''; C_RAZ=''
fi

info()   { printf '%s\n' "$*"; }
ok()     { printf '%s✔%s %s\n' "$C_OK" "$C_RAZ" "$*"; }
echec()  { printf '%s✖ %s%s\n' "$C_ERR" "$*" "$C_RAZ" >&2; exit 1; }

# --- Lecture des options ----------------------------------------------------
mode="public"          # public (ngrok) | local | lan
domaine_ngrok="${NGROK_DOMAIN:-}"

while [ $# -gt 0 ]; do
  case "$1" in
    --local) mode="local" ;;
    --lan)   mode="lan" ;;
    --domaine|--domain) domaine_ngrok="${2:-}"; shift ;;
    # ⚠️ La plage suit l'en-tête ci-dessus : y ajouter une ligne d'exemple sans
    # l'élargir la ferait disparaître de l'aide.
    -h|--aide|--help)
      sed -n '3,14p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'
      exit 0 ;;
    *) echec "Option inconnue : $1 (essayez --aide)" ;;
  esac
  shift
done

# =============================================================================
# 1. Vérifications AVANT de lancer quoi que ce soit
#
# Chacune remplace une panne qui, sinon, se découvre au pire moment : devant le
# client, avec un écran blanc et aucune explication.
# =============================================================================
info "${C_TITRE}Kaikun 360 — préparation de la démonstration${C_RAZ}"

command -v php  >/dev/null || echec "php introuvable."
command -v node >/dev/null || echec "node introuvable."
[ -f "$racine/backend/.env" ]            || echec "backend/.env manquant (copiez .env.example puis php artisan key:generate)."
[ -d "$racine/backend/vendor" ]          || echec "Dépendances PHP absentes : lancez « composer install » dans backend/."
[ -d "$racine/frontend/node_modules" ]   || echec "Dépendances Node absentes : lancez « npm install » dans frontend/."
[ -e "$racine/backend/public/storage" ]  || echec "Lien des médias absent : lancez « php artisan storage:link » dans backend/."

# La base doit répondre : sans elle le site se charge et TOUTES les pages de
# contenu échouent, ce qui ressemble à un bug d'application.
if ! (cd "$racine/backend" && php artisan db:monitor >/dev/null 2>&1); then
  echec "La base de données ne répond pas. Démarrez MySQL, puis relancez."
fi
ok "Dépendances, médias et base de données en place."

# Ports libres — un « php artisan serve » oublié d'hier et la démonstration
# montrerait un serveur qui n'est pas celui qu'on croit.
port_occupe() { (exec 3<>"/dev/tcp/127.0.0.1/$1") >/dev/null 2>&1; }
for p in $PORT_API $PORT_WEB; do
  ! port_occupe "$p" || echec "Le port $p est déjà utilisé. Fermez le serveur qui l'occupe (fuser -k $p/tcp) puis relancez."
done

if [ "$mode" = "public" ]; then
  command -v ngrok >/dev/null || echec "ngrok introuvable. Installez-le, ou lancez « ./scripts/demo.sh --lan »."
  ngrok config check >/dev/null 2>&1 || echec "ngrok n'est pas configuré : « ngrok config add-authtoken <votre-jeton> »."
  ok "ngrok prêt."
fi

# =============================================================================
# 2. Arrêt propre
#
# Sans cela, Ctrl+C laisserait des ports occupés et le lancement suivant
# échouerait — juste avant la présentation, forcément.
#
# ⚠️ On vise chaque serveur par sa ligne de commande, et non le groupe de
# processus entier (« kill 0 ») : ce raccourci n'est sûr que si le script a été
# lancé depuis un terminal interactif. Appelé depuis un autre script, il
# emporterait aussi son appelant. On tue donc précisément ce qu'on a démarré :
# les serveurs eux-mêmes, ET les processus qu'ils délèguent (php artisan serve
# ne fait que superviser un « php -S »).
# =============================================================================
nettoyage() {
  trap - INT TERM EXIT
  info "\n${C_GRIS}Arrêt des serveurs…${C_RAZ}"
  pkill -f "artisan serve --port=$PORT_API"        2>/dev/null || true
  pkill -f "php -S .*:$PORT_API"                   2>/dev/null || true
  pkill -f "ng serve --configuration demo"         2>/dev/null || true
  [ "$mode" = "public" ] && { pkill -f "ngrok http $PORT_WEB" 2>/dev/null || true; }
  # Les relais d'affichage (sed) démarrés avec chaque serveur.
  kill "${pids_affichage[@]}" 2>/dev/null || true
  return 0
}
pids_affichage=()
trap nettoyage INT TERM EXIT

# =============================================================================
# 3. L'adresse publique — connue AVANT de construire le site
#
# L'ordre compte : le site est compilé avec l'adresse publique dedans (balises
# canonical, partages sociaux) et Laravel démarre avec elle dans APP_URL. Il
# faut donc ouvrir le tunnel d'abord, même si rien n'écoute encore derrière.
# =============================================================================
case "$mode" in
  public)
    # ⚠️ On cherche le tunnel qui dessert LE PORT DU SITE, jamais « la première
    # adresse publiée ».
    #
    # Le forfait gratuit n'autorise qu'une session d'agent : si un ngrok tourne
    # déjà (vers l'API, vers un autre projet…), celui du script ne démarre pas —
    # et prendre l'adresse de l'autre a produit exactement le défaut qu'on
    # cherchait à éviter. Le site se servait alors depuis localhost pendant que
    # ses photos venaient du domaine ngrok : le navigateur n'ayant jamais
    # accepté l'avertissement de ngrok SUR CE DOMAINE, chaque image recevait la
    # page d'avertissement à la place du JPEG. Un site entier sans photos, sans
    # la moindre erreur nulle part.
    #
    # ⚠️ Le `|| true` final n'est pas décoratif. Ce script tourne sous
    # `set -e -o pipefail` : tant que le tunnel n'écoute pas, `curl` échoue,
    # `pipefail` propage cet échec au tuyau, et l'affectation
    # `url_publique="$(tunnel_du_site)"` sort alors du script — la boucle
    # d'attente de 40 essais n'en faisait jamais qu'un, et le script s'arrêtait
    # sans un mot juste après avoir lancé ngrok. Ici l'échec est l'état NORMAL
    # des premières secondes : il doit rendre une chaîne vide, pas un code
    # d'erreur.
    tunnel_du_site() {
      curl -s "http://127.0.0.1:$PORT_NGROK/api/tunnels" 2>/dev/null | PORT="$PORT_WEB" php -r '
        $j = json_decode(stream_get_contents(STDIN), true);
        foreach ($j["tunnels"] ?? [] as $t) {
          $vise_le_site = str_contains($t["config"]["addr"] ?? "", ":" . getenv("PORT"));
          if ($vise_le_site && str_starts_with($t["public_url"] ?? "", "https")) {
            echo $t["public_url"];
            return;
          }
        }
      ' 2>/dev/null || true
    }

    if curl -sf -o /dev/null "http://127.0.0.1:$PORT_NGROK/api/tunnels" 2>/dev/null; then
      # Un agent tourne déjà : soit il expose le site (on le réutilise), soit il
      # expose autre chose et il faut le fermer — le forfait gratuit ne permet
      # pas d'en ouvrir un second.
      url_publique="$(tunnel_du_site)"
      [ -n "$url_publique" ] || echec "Un ngrok tourne déjà, mais il n'expose PAS le port $PORT_WEB (celui du site).
   Le forfait gratuit n'autorise qu'une session : fermez-le d'abord (pkill ngrok),
   puis relancez. Laisser les deux donnerait un site dont toutes les PHOTOS
   seraient cassées — elles viendraient d'un domaine que le navigateur n'a pas
   encore accepté."
      ok "Tunnel ngrok déjà ouvert sur le site, réutilisé : $url_publique"
    else
      info "${C_GRIS}Ouverture du tunnel ngrok…${C_RAZ}"
      # --host-header=rewrite : ngrok présente au serveur Angular un en-tête Host
      # « localhost:4200 ». Sans cela, le serveur de développement refuse la
      # requête (« Blocked request »), car il n'autorise que localhost.
      if [ -n "$domaine_ngrok" ]; then
        ngrok http "$PORT_WEB" --host-header=rewrite --domain="$domaine_ngrok" --log=stdout >/tmp/kaikun360-ngrok.log 2>&1 &
      else
        ngrok http "$PORT_WEB" --host-header=rewrite --log=stdout >/tmp/kaikun360-ngrok.log 2>&1 &
      fi

      url_publique=""
      for _ in $(seq 1 40); do
        url_publique="$(tunnel_du_site)"
        [ -n "$url_publique" ] && break
        sleep 0.5
      done
      [ -n "$url_publique" ] || echec "ngrok n'a pas publié d'adresse pour le port $PORT_WEB. Détail : /tmp/kaikun360-ngrok.log"
      ok "Adresse publique : $url_publique"
    fi
    ;;
  lan)
    ip_locale="$(hostname -I 2>/dev/null | awk '{print $1}')"
    [ -n "$ip_locale" ] || echec "Adresse IP locale introuvable."
    url_publique="http://$ip_locale:$PORT_WEB"
    ok "Adresse réseau local : $url_publique"
    ;;
  local)
    url_publique="http://localhost:$PORT_WEB"
    ;;
esac

# =============================================================================
# 4. L'environnement « demo » du site — RÉGÉNÉRÉ à chaque lancement
#
# Fichier volontairement jetable (ignoré par git) : il porte l'adresse ngrok du
# jour, qui change à chaque ouverture de tunnel. Le committer reviendrait à
# publier une adresse déjà morte.
# =============================================================================
identifiant_google="$(sed -n "s/.*googleClientId: '\([^']*\)'.*/\1/p" \
  "$racine/frontend/src/environments/environment.ts" | head -1)"

cat > "$racine/frontend/src/environments/environment.demo.ts" <<EOF
/**
 * ⚠️ FICHIER GÉNÉRÉ — ne pas modifier, ne pas committer.
 *
 * Réécrit à chaque lancement de \`scripts/demo.sh\` : il porte l'adresse
 * publique du jour. Voir ce script pour le pourquoi de chaque valeur.
 */
export const environment = {
  production: false,
  // Relative : le site et l'API partagent une seule origine publique, le
  // serveur de développement relayant /api vers Laravel (proxy.conf.json).
  apiUrl: '/api/v1',
  googleClientId: '$identifiant_google',
  // Adresse publique de CETTE démonstration (balises canonical / partages).
  siteUrl: '$url_publique',
};
EOF
ok "Environnement de démonstration écrit."

# =============================================================================
# 5. Les deux serveurs
# =============================================================================
cd "$racine/backend"
# APP_URL et FRONTEND_URL sont passées par l'environnement du processus : Laravel
# donne la priorité aux vraies variables d'environnement sur le .env, donc le
# fichier de configuration du projet n'est PAS touché — rien à remettre en place
# après la démonstration, rien à committer par erreur.
APP_URL="$url_publique" FRONTEND_URL="$url_publique" \
  php artisan serve --port="$PORT_API" 2>&1 \
  | sed -u "s/^/${C_API}[api]${C_RAZ} /" &
pids_affichage+=($!)

cd "$racine/frontend"
# API_ORIGIN reste local : c'est le rendu serveur (Node) qui appelle l'API, et
# il est sur cette machine (voir l'en-tête du script).
API_ORIGIN="http://localhost:$PORT_API" \
  npx ng serve --configuration demo --port "$PORT_WEB" \
  $([ "$mode" = "lan" ] && printf %s "--host=0.0.0.0") 2>&1 \
  | sed -u "s/^/${C_WEB}[web]${C_RAZ} /" &
pids_affichage+=($!)

# =============================================================================
# 6. On attend que le site réponde, puis on affiche l'adresse à partager
# =============================================================================
info "${C_GRIS}Compilation du site (une minute environ au premier lancement)…${C_RAZ}"
for _ in $(seq 1 180); do
  if curl -sf -o /dev/null "http://localhost:$PORT_WEB"; then break; fi
  sleep 1
done

cat <<MSG

${C_TITRE}═══════════════════════════════════════════════════════════${C_RAZ}
  Kaikun 360 est en ligne :

      ${C_TITRE}$url_publique${C_RAZ}

  Back-office : $url_publique/back-office
$( [ "$mode" = "public" ] && printf '  Trafic en direct : http://localhost:%s\n' "$PORT_NGROK" )
$( [ "$mode" = "public" ] && printf '\n  %s⚠️ Ouvrez CETTE adresse, pas localhost:%s : les photos sont\n  servies par le même domaine, et le navigateur ne les affichera\n  qu'"'"'après avoir accepté l'"'"'avertissement de ngrok.%s\n' "$C_ERR" "$PORT_WEB" "$C_RAZ" )
$( [ "$mode" = "public" ] && printf '  %sAu premier accès, ngrok affiche donc un avertissement : cliquer\n  sur « Visit Site » une fois, et tout le site suit. La connexion\n  Google, elle, ne marche que sur les domaines déclarés chez\n  Google — pas sur ngrok.%s\n' "$C_GRIS" "$C_RAZ" )

  Ctrl+C pour tout arrêter.
${C_TITRE}═══════════════════════════════════════════════════════════${C_RAZ}

MSG

wait
