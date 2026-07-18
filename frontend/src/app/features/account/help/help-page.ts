import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { AccountIcon } from '../account-nav';
import { AccountIconComponent } from '../account-icon';

/** Un sujet d'aide = une rubrique de l'espace expliquée en quelques lignes. */
interface HelpTopic {
  /** Icône (réutilise le jeu de l'espace) pour un repère visuel. */
  icon: AccountIcon;
  /** Titre du sujet (généralement le nom de la rubrique). */
  title: string;
  /** Explication, une entrée = un paragraphe. */
  lines: string[];
  /** Lien interne pour ouvrir la rubrique concernée (optionnel). */
  link?: string;
  /** Libellé du lien (par défaut « Ouvrir »). */
  linkLabel?: string;
}

/**
 * Page d'aide de l'espace client — « Comment utiliser votre espace ».
 *
 * Mode d'emploi **toujours disponible** (rubrique « Aide » du menu) : il explique,
 * rubrique par rubrique, à quoi sert chaque partie du tableau de bord et comment
 * s'en servir. Présenté en **accordéon** (`<details>`/`<summary>`, accessible au
 * clavier), chaque sujet pointant vers l'écran correspondant. Contenu **statique**
 * (aucun appel réseau) : c'est de la documentation utilisateur.
 */
@Component({
  selector: 'app-help-page',
  imports: [RouterLink, AccountIconComponent],
  templateUrl: './help-page.html',
  styleUrl: './help-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class HelpPageComponent {
  /** Les sujets d'aide, dans l'ordre des rubriques de l'espace. */
  protected readonly topics: readonly HelpTopic[] = [
    {
      icon: 'grid',
      title: 'Tableau de bord',
      lines: [
        'C’est la page d’accueil de votre espace : une vue d’ensemble avec un mot de bienvenue et des raccourcis (les tuiles) vers chaque rubrique.',
        'La checklist « Pour bien démarrer » vous guide dans vos premiers pas (vérifier votre compte, compléter votre profil). Les étapes se cochent toutes seules et la checklist disparaît une fois tout accompli.',
      ],
      link: '/mon-espace',
      linkLabel: 'Aller au tableau de bord',
    },
    {
      icon: 'inbox',
      title: 'Mes demandes',
      lines: [
        'Retrouvez ici toutes les demandes de service que vous avez déposées sur le site (immobilier, construction, séminaire…).',
        'Chaque demande affiche une chronologie de son statut : reçue, en vérification, visite, devis, négociation, puis clôturée. Vous suivez ainsi son avancement en toute transparence.',
      ],
      link: '/mon-espace/demandes',
      linkLabel: 'Voir mes demandes',
    },
    {
      icon: 'calendar',
      title: 'Réservations',
      lines: [
        'Toutes vos réservations au même endroit, tous univers confondus : nuitées, locations de véhicules, expériences et trajets.',
        'Vous y voyez les dates, le montant et le statut. Quand c’est encore possible (véhicules et expériences non commencés), vous pouvez annuler en un geste, avec l’information sur un éventuel remboursement.',
      ],
      link: '/mon-espace/reservations',
      linkLabel: 'Voir mes réservations',
    },
    {
      icon: 'heart',
      title: 'Favoris',
      lines: [
        'Quand un bien vous plaît, cliquez sur le cœur pour le sauvegarder : vous le retrouvez ici, présenté comme dans le catalogue.',
        'Cliquez sur une carte pour rouvrir la fiche du bien, ou sur le cœur pour le retirer de vos favoris (une confirmation vous est demandée).',
      ],
      link: '/mon-espace/favoris',
      linkLabel: 'Voir mes favoris',
    },
    {
      icon: 'bell',
      title: 'Notifications',
      lines: [
        'Les mises à jour importantes (avancement d’une demande, devis reçu, réservation confirmée, nouveau message) apparaissent ici et sur la cloche en haut de l’écran.',
        'La pastille de la cloche indique le nombre de notifications non lues. Cliquez sur une notification pour la marquer comme lue et aller directement à l’écran concerné ; « Tout marquer comme lu » remet le compteur à zéro.',
      ],
      link: '/mon-espace/notifications',
      linkLabel: 'Voir mes notifications',
    },
    {
      icon: 'chat',
      title: 'Messages',
      lines: [
        'Échangez par messagerie avec le support Kaikun ou avec un professionnel lié à vos demandes.',
        'La liste montre vos conversations avec un aperçu du dernier message et une pastille de messages non lus. Ouvrez une conversation pour lire le fil et répondre : votre message part immédiatement et le fil est marqué comme lu.',
      ],
      link: '/mon-espace/messages',
      linkLabel: 'Ouvrir ma messagerie',
    },
    {
      icon: 'user',
      title: 'Profil',
      lines: [
        'Gérez votre identité et vos coordonnées : nom, e-mail, téléphone, adresse et localisation. Modifier votre e-mail ou votre téléphone déclenche une re-vérification par code, pour votre sécurité.',
        'Vous pouvez aussi changer votre mot de passe, déposer vos pièces justificatives (PDF ou image) et, si vous le souhaitez, supprimer votre compte.',
      ],
      link: '/mon-espace/profil',
      linkLabel: 'Ouvrir mon profil',
    },
    {
      icon: 'help',
      title: 'Se repérer et se déconnecter',
      lines: [
        'Le menu à gauche vous permet de passer d’une rubrique à l’autre. Sur téléphone, il s’ouvre avec le bouton ☰ en haut à gauche.',
        'La cloche donne accès à vos notifications, et votre nom (en haut à droite) ouvre le menu de votre compte. Le bouton « Se déconnecter », en bas du menu, ferme votre session en toute sécurité.',
      ],
    },
  ];
}
