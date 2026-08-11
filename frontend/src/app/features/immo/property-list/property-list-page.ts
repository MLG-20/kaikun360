import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CatalogComponent } from '../../../shared/components/catalog/catalog';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/**
 * Page univers Immobilier (F2.3) — route `/immobilier`.
 *
 * Vitrine dédiée aux biens à la vente/location : bandeau éditorial (promesse
 * de confiance) + catalogue filtrable figé sur l'univers « immobilier ». Le
 * composant `app-catalog` gère filtres/tri/pagination via l'URL ; chaque carte
 * pointe vers la fiche détaillée `/immobilier/:id`.
 */
@Component({
  selector: 'app-property-list-page',
  imports: [PageHeroComponent, CatalogComponent, RouterLink],
  templateUrl: './property-list-page.html',
  styleUrl: './property-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PropertyListPageComponent {
  /** Points de réassurance affichés dans le bandeau d'introduction. */
  protected readonly highlights = [
    { title: 'Vérification documentée', text: 'Titre de propriété et pièces contrôlés avant publication.' },
    { title: 'Visite sur demande', text: 'Planifiez une visite filmée et datée, où que vous soyez.' },
    { title: 'Zéro arnaque diaspora', text: 'Un numéro de suivi unique pour chaque démarche.' },
  ];
}
