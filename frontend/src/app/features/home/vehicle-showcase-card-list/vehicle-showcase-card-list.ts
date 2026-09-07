import { ChangeDetectionStrategy, Component, input } from '@angular/core';

/** Le sous-ensemble d'une carte véhicule dont l'affichage a besoin. */
export interface VehicleShowcaseCardView {
  id: number;
  title: string;
  description: string | null;
  image: string;
  linkUrl: string;
  linkLabel: string | null;
}

/**
 * Cartes de la section « Location de véhicules » de l'accueil (2026-09-07),
 * qui remplace l'ancienne section « Protocole de confiance ».
 *
 * Composant à part, avec sa propre feuille de style — même raison que
 * `NewsCardMiniListComponent` : pas de budget CSS à consommer sur
 * `home-page.scss`. Plus grandes que les cartes « À découvrir » et
 * affichant toujours leur description (demande client, 2026-09-07) : ce
 * n'est PAS le même composant que les actualités, dont l'absence de
 * description est un choix produit documenté à part.
 */
@Component({
  selector: 'app-vehicle-showcase-card-list',
  templateUrl: './vehicle-showcase-card-list.html',
  styleUrl: './vehicle-showcase-card-list.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleShowcaseCardListComponent {
  cards = input.required<VehicleShowcaseCardView[]>();
}
