# `scripts/` — outils de lancement

## `demo.sh` — montrer le site en une commande

```bash
./scripts/demo.sh                 # site accessible depuis Internet (ngrok)
./scripts/demo.sh --local         # seulement sur cette machine
./scripts/demo.sh --lan           # sur le wifi (téléphone, tablette, collègue)
./scripts/demo.sh --domaine mon-site.ngrok-free.dev
```

Le script démarre **l'API Laravel**, **le site Angular** et **l'ouverture
publique ngrok**, puis affiche l'adresse à partager. `Ctrl+C` arrête les trois.

Prévu pour une **présentation client** : brancher, ouvrir le lien sur le
téléphone de la personne en face, montrer. Aucun déploiement, aucun serveur à
louer, et rien à remettre en place après.

### Ce que ça évite d'oublier

Une démonstration à distance échoue rarement de façon franche : elle s'affiche,
et quelque chose ne va pas. Le script règle d'avance les trois pièges connus.

| Le piège | Ce qui se passe sans | Ce que fait le script |
| --- | --- | --- |
| `apiUrl` pointe sur `localhost:8000` | Le site s'ouvre chez le client, **aucune donnée ne charge** : son navigateur cherche une API sur *sa* machine | Génère un environnement `demo` où `apiUrl` est **relative** (`/api/v1`) — le serveur Angular relaie vers Laravel (`proxy.conf.json`) |
| `APP_URL` reste sur `localhost:8000` | Le site s'affiche, mais **toutes les photos sont cassées** : Laravel fabrique les URL des médias à partir de cette valeur | Passe l'adresse publique dans `APP_URL` **par l'environnement du processus**, sans modifier `backend/.env` |
| `API_ORIGIN` suivrait l'adresse publique | Chaque page rendue côté serveur ferait un **aller-retour par Internet** pour joindre une API située sur la même machine | Le garde sur `http://localhost:8000` |

### Pourquoi une seule ouverture ngrok

Exposer séparément le site (`:4200`) et l'API (`:8000`) donnerait deux domaines
publics : du CORS à rouvrir, deux adresses à replacer dans la configuration à
chaque lancement, et un forfait ngrok gratuit qui ne tient qu'une session.

Tout passe donc par l'adresse du site, le serveur de développement Angular
relayant `/api` et `/storage` vers Laravel. Le visiteur ne voit **qu'une
origine** — exactement comme en production, où Laravel sert le site et l'API
ensemble. La démonstration montre ainsi l'architecture réelle, pas un montage
propre au portable de développement.

### Ce qu'il faut savoir avant de présenter

- **L'avertissement ngrok** : au premier accès, le forfait gratuit affiche une
  page « You are about to visit… ». Un clic sur *Visit Site* et on n'en reparle
  plus. Ouvrir le lien soi-même avant la réunion évite de le découvrir devant
  le client.
- **L'adresse change à chaque lancement**, sauf avec `--domaine` (un domaine
  fixe est offert avec un compte ngrok : le réserver rend le lien stable d'une
  démonstration à l'autre).
- **La connexion Google ne fonctionne pas** sur une adresse ngrok : Google
  n'autorise que les domaines déclarés dans sa console. Prévoir une connexion
  par e-mail et mot de passe pour la démonstration.
- **C'est le serveur de développement**, donc les données affichées sont celles
  de la base locale, et le site n'est pas optimisé pour la vitesse. Ce qui est
  exposé pendant ce temps est bien la vraie base : ne pas laisser tourner après.

### Ce que le script vérifie avant de démarrer

Dépendances PHP et Node installées, `backend/.env` présent, lien
`php artisan storage:link` en place, **base de données qui répond**, ports 8000
et 4200 libres, et ngrok configuré. Chacun de ces points remplace une panne qui,
sinon, se découvre au pire moment.

### Fichier généré

`frontend/src/environments/environment.demo.ts` est **réécrit à chaque
lancement** (il porte l'adresse publique du jour) et ignoré par git. La
configuration Angular correspondante s'appelle `demo`, dans `angular.json` — les
configurations `development` et `production` ne sont pas touchées.

Lancer `ng serve --configuration demo` à la main sans passer par le script
échouera donc, faute de ce fichier : c'est voulu, l'environnement de
démonstration n'a de sens qu'associé à une adresse publique vivante.
