import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

/** Le sous-ensemble d'un article dont une carte compacte a besoin. */
export interface NewsCardMini {
  id: number;
  title: string;
  image: string;
  linkUrl: string | null;
  linkLabel: string | null;
}

/**
 * Cartes compactes de la section « Actualités Kaikun » (F17, 2026-08-21).
 *
 * Une ligne « Actualités » **sans texte rédigé mais avec un lien** devient
 * une de ces cartes — voir `home-page.ts::cartesLibres`. Le client les crée
 * depuis le même écran back-office que les vrais articles, sans rien
 * rédiger : juste une image, un titre, une destination.
 *
 * ⚠️ **Toujours une image, jamais de vidéo** (décision du client,
 * 2026-08-21) : la vidéo vit dans sa propre colonne, à gauche du grand bloc
 * « Actualités ». Ces cartes restent de simples vignettes cliquables.
 *
 * ⚠️ **Le survol pilote le carrousel vidéo voisin** (F17.3, 2026-08-21) :
 * survoler une carte émet son id via `survol`, le composant parent
 * (`home-page.ts::onSurvolCarte`) bascule alors sur la vidéo de MÊME id si
 * elle en a une — remplace les pastilles retirées à la demande du client.
 * `null` en sortie de survol signale que la souris a quitté la carte.
 *
 * ⚠️ **Plafonnées à 4 et SANS rotation propre** (décision du client,
 * 2026-08-21, contraire au carrousel vidéo à côté) : ce sont des repères
 * fixes, pas un contenu qui défile — le plafond est déjà appliqué en amont
 * (`cartesLibres`), ce composant se contente d'afficher ce qu'on lui donne.
 *
 * Composant à part, avec SA PROPRE feuille de style — même raison que
 * `UniverseStripComponent` : `home-page.scss` est déjà au maximum de son
 * budget de build.
 */
@Component({
  selector: 'app-news-card-mini-list',
  templateUrl: './news-card-mini-list.html',
  styleUrl: './news-card-mini-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class NewsCardMiniListComponent {
  cards = input.required<NewsCardMini[]>();
  survol = output<number | null>();
}
