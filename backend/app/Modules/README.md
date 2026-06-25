# Architecture modulaire — Kaikun 360

Le code métier est organisé **par domaine** (et non par type technique global), pour rester
maintenable malgré les 9 univers fonctionnels.

## Modules

| Module | Domaine |
|---|---|
| `Core` | Auth, utilisateurs, rôles, profils |
| `Immo` | Achat / vente / location mensuelle |
| `Stay` | Nuitées / hébergements courte durée |
| `Manage` | Gestion locative |
| `Build` | Construction / rénovation / devis |
| `Explore` | Tourisme et expériences |
| `Mobility` | Transport et mobilité |
| `Diaspora` | Projets diaspora |
| `TeamBuilding` | Team building entreprises |
| `Pro` | Marketplace prestataires |
| `Admin` | Back-office |

## Structure d'un module

```
app/Modules/<Module>/
├── Models/              Modèles Eloquent
├── Http/Controllers/    Contrôleurs API
├── Http/Requests/       Form Requests (validation)
├── Services/            Logique métier (calculs, règles)
├── Policies/            Autorisations
├── Enums/               Enums de statuts métier
├── Events/              Événements du domaine
└── routes/              Fichiers de routes du module (montés sous /api/v1)
```

## Namespace

PSR-4 via le mapping Laravel par défaut `App\ => app/`. Donc :
`app/Modules/Immo/Models/Property.php` → classe `App\Modules\Immo\Models\Property`.
Aucune config `composer.json` supplémentaire n'est nécessaire.
