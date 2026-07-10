# Enums de statuts métier — Kaikun 360

Les **statuts métier** ne sont jamais manipulés sous forme de simples chaînes
de caractères « magiques » : ils sont typés via des **enums PHP natifs** (backed
enums `string`). Cela garantit qu'une valeur de statut invalide ne peut pas
circuler dans le code, et centralise les libellés d'affichage.

## Convention

Chaque enum de statut expose :
- des `case` en `UPPER_SNAKE_CASE`, avec une valeur stockée en `snake_case` ;
- `label(): string` → le libellé lisible en **français** (back-office, API) ;
- `values(): array` → la liste des valeurs brutes, utile pour la validation
  (`Rule::in(MonEnum::values())`) et les colonnes `enum`/`string` des migrations.

## Où trouver quel enum ?

| Statut | Enum | Emplacement |
|---|---|---|
| Bien immobilier | `PropertyStatus` | `app/Modules/Immo/Enums/` (spécifique au module) |
| Demande client | `App\Enums\RequestStatus` | `app/Enums/` (transversal, cf. B11) |
| Devis | `App\Enums\QuoteStatus` | `app/Enums/` (transversal, cf. B11) |
| Réservation | `App\Enums\BookingStatus` | `app/Enums/` (transversal, cf. B11) |
| Paiement | `App\Enums\PaymentStatus` | `app/Enums/` (transversal, cf. B14) |

> **Règle de placement :** un enum propre à un seul module vit dans
> `app/Modules/<Module>/Enums/`. Un enum partagé par plusieurs modules
> (couche transversale Requests / Bookings / Payments) vit dans `app/Enums/`.

## Usage typique

```php
use App\Enums\BookingStatus;

$booking->status = BookingStatus::CONFIRMEE;          // affectation typée
echo BookingStatus::CONFIRMEE->label();               // "Confirmée"
$regle = ['status' => ['required', Rule::in(BookingStatus::values())]];
```
