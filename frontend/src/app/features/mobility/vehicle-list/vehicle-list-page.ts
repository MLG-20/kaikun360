import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CatalogComponent } from '../../../shared/components/catalog/catalog';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/**
 * Page univers Transport (F2.4) — route `/transport`.
 *
 * Vitrine des véhicules (avec ou sans chauffeur) : bandeau éditorial + catalogue
 * filtrable figé sur l'univers « transport ». Chaque carte pointe vers la fiche
 * détaillée `/transport/:id`. Un renvoi vers la Mobilité (`/mobilite`) est
 * proposé pour les navettes et transferts, qui relèvent d'un autre catalogue.
 */
@Component({
  selector: 'app-vehicle-list-page',
  imports: [PageHeroComponent, CatalogComponent, RouterLink],
  templateUrl: './vehicle-list-page.html',
  styleUrl: './vehicle-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class VehicleListPageComponent {
  /** Points de réassurance affichés dans le bandeau d'introduction. */
  protected readonly highlights = [
    { title: 'Véhicules contrôlés', text: 'Documents et conformité vérifiés avant publication.' },
    { title: 'Avec ou sans chauffeur', text: 'Location libre ou trajet accompagné, selon votre besoin.' },
    { title: 'Caution transparente', text: 'Le montant de la caution est affiché avant toute demande.' },
  ];
}
