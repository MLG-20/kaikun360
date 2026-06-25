# Kaikun 360 — version consolidée du site

Cette livraison fusionne les meilleures fonctions des deux maquettes HTML et les règles du manuel d'administration :

- site public responsive avec méga-menu ;
- vocabulaire final : **Tourisme** et **Transport** ;
- catalogue illustré couvrant biens, terrains, hébergements, circuits, pirogues, berlines, 4×4, minibus, construction, team building, colonies de vacances, conciergerie et livraison ;
- recherche et filtres dynamiques ;
- parcours de paiement Wave, Orange Money, Free Money, carte, virement et lien de paiement ;
- back-office complet : files opérationnelles, catalogue, KYC, réservations, paiements, rapprochement, payouts EOD, incidents, rapports, rôles et sécurité ;
- PWA installable grâce au manifeste et au service worker ;
- schéma PostgreSQL/Supabase de départ.

## Ouvrir la démo

Le plus simple est de servir le dossier avec un petit serveur local :

```bash
python -m http.server 8080
```

Puis ouvrir :

- `http://localhost:8080/index.html`
- `http://localhost:8080/admin.html`
- `http://localhost:8080/checkout.html`

Le site peut aussi être ouvert directement, mais le service worker nécessite un serveur HTTP/HTTPS.

## Architecture de production recommandée

- **Front web : Next.js avec App Router et TypeScript**
- **Application mobile : Expo / React Native**, partageant les types et règles métier
- **Base, authentification et stockage : Supabase / PostgreSQL**, avec Row Level Security
- **Hébergement : Vercel ou infrastructure équivalente**, avec CDN, logs et supervision
- **Paiement : PSP agréé au Sénégal / UMOA**, capable de gérer Wave, Orange Money, Free Money, cartes, webhooks, remboursements et payouts
- **Notifications : WhatsApp Business API, email et SMS**
- **Administration : interface séparée, MFA, rôles fins, journalisation et double validation**

## Ce qui reste à connecter avant mise en production

1. Nom de domaine, emails professionnels et numéro WhatsApp officiel.
2. Projet Supabase, migrations SQL et politiques RLS testées.
3. Comptes marchands et clés API du PSP retenu.
4. Webhooks signés, idempotence, rapprochement et remboursements.
5. Contrats, CGU, politique de confidentialité, mentions légales et validation fiscale.
6. Photos propriétaires/licenciées et données réelles du catalogue.
7. Tests de charge, sécurité, accessibilité, reprise après incident et sauvegardes.

## Fichiers principaux

- `index.html` : site public
- `admin.html` : back-office
- `checkout.html` : paiement
- `assets/css/` : charte graphique
- `assets/js/` : interactions de la démo
- `supabase/schema.sql` : base de données de départ
- `.env.example` : variables à prévoir

## Important

Les paiements de cette démo sont simulés. Aucun débit réel n'est effectué. La version de production doit attendre une confirmation serveur du PSP et ne jamais valider une commande sur simple capture d'écran.
