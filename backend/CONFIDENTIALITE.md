# Confidentialité & conservation des données (B15.4)

Ce document reflète **techniquement** la politique de confidentialité : durée de
conservation par type de donnée et mécanisme de suppression sur demande.

## Suppression / anonymisation sur demande

`DELETE /api/v1/users/me` (utilisateur authentifié) déclenche
`App\Modules\Core\Services\AccountAnonymizer` :

- les **données personnelles** sont scrubées (nom, e-mail rendu non nominatif,
  téléphone, ville, mot de passe régénéré) ;
- les **pièces d'identité (KYC)** sont supprimées (fichiers + enregistrements) ;
- les **préférences de profil** sont vidées ;
- **tous les jetons d'accès** (Sanctum) sont révoqués ;
- le compte passe en statut `desactive`.

Le compte n'est **pas** effacé physiquement afin de préserver l'intégrité
comptable et légale des transactions (cf. rétention ci-dessous). L'opération est
tracée dans le journal d'audit (`Anonymisation de compte (RGPD)`).

## Durée de conservation par type de donnée

| Donnée | Support | Conservation | À la suppression du compte |
|---|---|---|---|
| Identité (nom, e-mail, téléphone) | `users` | tant que le compte est actif | **anonymisée** |
| Préférences de profil | `profiles` | idem compte | **vidées** |
| Pièces d'identité (KYC) | disque privé + `user_documents` | jusqu'à validation puis besoin légal | **supprimées** |
| Réservations | `bookings` | rétention comptable/légale | **conservées** (rattachées au compte anonymisé) |
| Paiements | `payments` | rétention comptable/légale | **conservés** |
| Reversements | `owner_payouts` | rétention comptable/légale | **conservés** |
| Journal d'audit | `activity_log` | traçabilité sécurité | **conservé** |

## Sécurité des documents

Les pièces sensibles (KYC, documents de biens) sont stockées sur un **disque non
public** et servies uniquement via des **URLs signées temporaires** (B1.5 / B2.3).
