# E-mails transactionnels — Kaikun 360

Ce dossier contient le **système d'e-mails de marque** : la fabrique qui produit
tous les messages envoyés aux utilisateurs, quel que soit le module d'origine.

> **Pourquoi ce soin ?** L'e-mail est aujourd'hui le seul canal de communication
> réellement maîtrisé de la plateforme. Or Kaikun 360 vend de la **confiance**
> sur un marché (immobilier et construction à distance, diaspora en tête) où la
> méfiance est la règle. Un e-mail générique — le gabarit « Hello! … Regards,
> Laravel » livré par défaut — détruit en trois secondes la crédibilité que
> l'ensemble du produit cherche à construire.

---

## 1. Principe : le contenu d'un côté, la mise en forme de l'autre

Une notification ne décrit **que son contenu**, sous forme de données
structurées. La mise en forme est faite une seule fois, par deux gabarits
communs.

```
  Notification (toMail)          BrandedMail                Gabarits Blade
  ─────────────────────          ───────────                ──────────────
  « titre, faits, bouton »  →  données structurées  →  emails/branded.blade.php       (HTML)
                                                      emails/branded-text.blade.php  (texte)
```

Conséquences :

| Bénéfice | Détail |
|---|---|
| Cohérence | Les 27 e-mails sont visuellement identiques, sans HTML recopié 27 fois. |
| Maintenance | Retoucher la charte = modifier **un** fichier. |
| Délivrabilité | Chaque envoi part en HTML **et** en texte brut, automatiquement. |
| Sûreté | Un lien d'espace privé ne peut plus pointer vers une page inexistante (voir `SpaceLink`). |

---

## 2. Les fichiers

| Fichier | Rôle |
|---|---|
| `BrandedMail.php` | Le constructeur. Une notification l'utilise pour décrire son contenu, puis appelle `toMailMessage()`. Porte aussi les aides de formatage `money()` et `date()`. |
| `SpaceLink.php` | Résout le bon lien d'espace privé selon le profil du destinataire (client, propriétaire, prestataire, entreprise). |
| `MailPreview.php` | Rejoue chaque e-mail avec des données fictives, pour l'aperçu navigateur. Local uniquement, rien n'est enregistré ni envoyé. |
| `../../../resources/views/emails/branded.blade.php` | Le gabarit HTML — **le seul HTML d'e-mail du projet**. |
| `../../../resources/views/emails/branded-text.blade.php` | Le gabarit texte brut, rendu à partir des mêmes données. |
| `../../../config/branding.php` | Couleurs, coordonnées, URL du site public. Aucune de ces valeurs n'est codée en dur dans les gabarits. |

---

## 3. Écrire un e-mail

```php
public function toMail(object $notifiable): MailMessage
{
    return BrandedMail::make()
        ->subject('Votre réservation est confirmée')   // « · Kaikun 360 » est ajouté
        ->preheader('Réservation BK-2031 confirmée.')  // ligne d'aperçu de la boîte de réception
        ->eyebrow('Réservation')                       // petit label au-dessus du titre
        ->tone('success')                              // brand | success | premium | alert
        ->heading('C\'est confirmé.')
        ->intro('Votre paiement a bien été encaissé.')
        ->facts([                                      // récapitulatif ; les valeurs nulles sautent
            'Référence' => $booking->reference,
            'Montant'   => BrandedMail::money($booking->amount_xof),
            'Arrivée'   => BrandedMail::date($booking->start_date),
        ])
        ->action('Voir ma réservation', SpaceLink::to($notifiable, 'reservations'))
        ->forRecipient($notifiable)                    // adapte le pied de page au profil
        ->reason('Vous recevez cet e-mail car…')       // conformité + confiance
        ->toMailMessage();
}
```

### Les blocs disponibles

Ils s'affichent dans cet ordre fixe, et seuls ceux qui sont renseignés apparaissent :

`eyebrow` → `heading` → `intro` → `code` → `facts` → `action` (+ `secondaryAction`)
→ `highlights` → `steps` → `note` → `security` → `outro` → `trust` → pied de page.

| Bloc | Usage |
|---|---|
| `code()` | Code à usage unique, en gros et très espacé (l'utilisateur le recopie). |
| `facts()` | Tableau clé/valeur. Les valeurs `null` ou vides sont ignorées : pas besoin de `if`. |
| `highlights()` | Encadrés « ce que vous obtenez ». Réservé aux e-mails d'accueil. |
| `steps()` | Étapes numérotées. Répond à « et maintenant, je fais quoi ? ». |
| `note()` | Encart discret, filet doré. Un conseil, une précision. |
| `security()` | Encart rouge. **À réserver aux vrais avertissements** — banalisé, il ne protège plus personne. |
| `trust()` | Bandeau « protocole de confiance ». **Uniquement sur l'e-mail de bienvenue.** |

### Règles de rédaction

1. **Toujours un `preheader`.** Sans lui, la boîte de réception affiche les
   premiers mots du gabarit. C'est un second objet, gratuit.
2. **Un e-mail = une action.** Un seul bouton principal.
3. **Formater montants et dates** via `BrandedMail::money()` et `::date()` —
   jamais à la main. Un même montant ne doit pas s'écrire de deux façons.
4. **Un chemin relatif suffit** dans `action()` : il est préfixé par l'URL du
   site public. Pour un lien d'espace privé, passer par `SpaceLink`.
5. **Dire pourquoi** l'utilisateur reçoit le message (`reason`). Exigence
   anti-spam autant que signal de sérieux.

---

## 4. Relire un e-mail

En local, chaque e-mail est consultable dans le navigateur, avec des données
fictives et **sans aucun envoi** :

```
http://127.0.0.1:8000/apercu-emails                          ← sommaire
http://127.0.0.1:8000/apercu-emails/bienvenue-proprietaire   ← un e-mail
http://127.0.0.1:8000/apercu-emails/bienvenue-proprietaire?texte=1
```

Réduire la fenêtre vérifie le rendu mobile ; basculer le thème du système
vérifie le mode sombre. La route est fermée hors environnement local
(`abort_unless(app()->environment('local'))`).

Ajouter un e-mail au catalogue : une entrée dans `MailPreview::catalog()` et le
cas correspondant dans `MailPreview::notification()`.

### …et dans un vrai client de messagerie

Le navigateur ne dit rien de ce qui compte le plus en production : ce que Gmail
ampute de la balise `<style>`, le rendu de son application Android, le texte
d'aperçu dans la liste des messages, et le passage des filtres anti-spam.

```bash
php artisan mail:apercu contact@exemple.com                          # les 20
php artisan mail:apercu contact@exemple.com --only=bienvenue-client  # un seul
php artisan mail:apercu contact@exemple.com --only=devis-recu,bien-publie
```

Données fictives, sujet préfixé `[APERÇU n/N]` pour rester groupés dans la boîte
de réception, deux secondes entre deux envois (`--pause`) — une rafale de vingt
messages en une seconde est exactement le profil qu'un filtre anti-spam
sanctionne.

> ⚠️ **Envoi réel** : consomme le quota du compte SMTP configuré. La commande
> refuse de s'exécuter avec `MAIL_MAILER=log`, qui donnerait l'illusion d'un envoi.

---

## 5. L'e-mail de bienvenue

`App\Modules\Core\Notifications\WelcomeNotification` — le message le plus
travaillé de la plateforme.

- **Quand ?** Une seule fois, quand le compte devient **actif** : après la saisie
  du code de vérification, ou immédiatement pour une inscription Google
  (l'adresse y est déjà vérifiée). Jamais en même temps que le code : deux
  e-mails à la seconde zéro noieraient le seul message utile à cet instant.
- **Contenu adapté au profil.** Client, diaspora, propriétaire, prestataire et
  entreprise ne viennent pas chercher la même chose : chacun a ses promesses,
  ses étapes et son bouton.
- **Seul e-mail à porter le bandeau « protocole de confiance »** — vérification
  documentée, tout filmé/daté/archivé, numéro de suivi unique.

---

## 6. Contraintes techniques du HTML d'e-mail

Elles expliquent la forme du gabarit, qui ne ressemble pas à du HTML moderne :

- **Mise en page en `<table>`** — Outlook utilise le moteur de rendu de Word :
  ni flexbox, ni grid.
- **Styles « inline »** — Gmail ampute la balise `<style>`, et l'ignore
  totalement dans son application Android. Tout ce qui est vital est écrit dans
  l'attribut `style`.
- **Le `<style>` ne porte que des améliorations optionnelles** : responsive et
  mode sombre. S'il saute, l'e-mail reste parfaitement lisible.
- **Aucune image distante** — les messageries les bloquent par défaut. Le logo
  est composé en typographie : il s'affiche toujours.
- **Largeur 600 px** — la largeur sûre des volets de lecture.
- **Bouton « à toute épreuve »** — une cellule de tableau colorée, pas un `<a>`
  stylé : c'est la seule technique qui tienne sur Outlook.

---

## 7. Réglage et pilotage

- **Configuration** : `config/branding.php` (couleurs, coordonnées, `FRONTEND_URL`).
- **Coupure par événement** : chaque notification déclare un
  `NotificationEvent` ; l'équipe peut l'éteindre depuis le back-office
  (Paramètres → Notifications, F7.2.l). Voir `app/Support/Notifications/README.md`.
- **Exception** : les codes de vérification et la double authentification ne
  sont **pas** désactivables — les couper condamnerait l'accès et l'inscription.

---

## 8. Tests

`tests/Feature/Notification/BrandedMailTest.php` — 13 tests, sans base de
données. Le dernier rend les 27 e-mails du catalogue dans leurs deux versions :
c'est le garde-fou qui rattrapera une propriété de modèle renommée, puisqu'un
envoi de notification échoue en silence (asynchrone, en file d'attente).

Le déclenchement de l'accueil est couvert par
`tests/Feature/Core/VerificationTest.php` (envoyé à l'activation, une seule fois).
