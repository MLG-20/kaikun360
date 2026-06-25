# Architecture de production retenue pour Kaikun 360

## 1. Choix principal

**Next.js + TypeScript + Supabase/PostgreSQL** constitue le meilleur socle pour Kaikun 360, car la plateforme doit dépasser un simple site vitrine : catalogue multiservice, comptes par profil, réservations, documents, paiement, tableaux de bord, rôles administratifs et application mobile.

Le prototype livré est volontairement statique afin d'être consultable immédiatement. Il représente l'interface cible à migrer dans le projet Next.js de production.

## 2. Applications

- `www.kaikun360.sn` : site public et espaces utilisateurs ;
- `admin.kaikun360.sn` : back-office isolé ;
- application mobile Expo/React Native ;
- API serveur Next.js/Supabase Edge Functions ;
- base PostgreSQL centrale ;
- stockage privé pour pièces KYC, contrats et preuves ;
- stockage public optimisé pour les photos du catalogue.

## 3. Modules métier

1. Immobilier et gestion locative
2. Construction et suivi de chantier
3. Tourisme, hébergements, circuits, team building et colonies
4. Transport : berlines, 4×4, navettes, bus, minibus et pirogues
5. Prestataires, KYC/KYB et contrats
6. Réservations, devis et ordres de service
7. Paiements, ledger, remboursements et payouts
8. Incidents, litiges, avis et qualité
9. Reporting propriétaire, diaspora, entreprise et direction

## 4. Paiements

Le backend crée la commande et calcule : service, commission, taxe estimée, caution et total. Le PSP encaisse. Kaikun ne confirme qu'après webhook signé et idempotent. Le ledger sépare : part prestataire payable, revenu Kaikun, réserve fiscale et frais/réserve. Le payout prestataire reste bloqué jusqu'à preuve d'exécution et contrôle EOD.

## 5. Sécurité

- MFA obligatoire pour super-admin, administrateurs et comptables ;
- deux super-administrateurs maximum ;
- aucun compte partagé ;
- journalisation des validations, prix, wallets et remboursements ;
- double validation des remboursements sensibles et payouts ;
- RLS Supabase et fonctions serveur pour les actions privilégiées ;
- chiffrement, sauvegarde quotidienne et test mensuel de restauration ;
- revue mensuelle des accès.

## 6. Mise en production par phases

- Phase 1 : catalogue, demandes, devis, comptes et back-office ;
- Phase 2 : paiement PSP, webhooks, rapprochement et remboursements ;
- Phase 3 : payouts, ledger, KYC avancé et reporting ;
- Phase 4 : application mobile, géolocalisation, OTP et automatisations ;
- Phase 5 : montée en charge nationale, supervision régionale et support étendu.
