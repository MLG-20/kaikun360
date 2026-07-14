import { ChangeDetectionStrategy, Component } from '@angular/core';

import { CatalogComponent } from '../../../shared/components/catalog/catalog';

/**
 * Page univers Nuitées (F2.3) — route `/nuitees`.
 *
 * Vitrine des logements réservables à la nuit : bandeau éditorial + catalogue
 * filtrable figé sur l'univers « nuitees ». Chaque carte pointe vers la fiche
 * `/nuitees/:id` (calendrier de disponibilité, équipements, réservation).
 */
@Component({
  selector: 'app-stay-list-page',
  imports: [CatalogComponent],
  templateUrl: './stay-list-page.html',
  styleUrl: './stay-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class StayListPageComponent {
  /** Points de réassurance affichés dans le bandeau d'introduction. */
  protected readonly highlights = [
    { title: 'Disponibilités en temps réel', text: 'Un calendrier à jour pour chaque logement.' },
    { title: 'Caution encadrée', text: 'Montant affiché et sécurisé, restitué après le séjour.' },
    { title: 'Ménage & check-in suivis', text: 'Arrivée et départ tracés par nos équipes.' },
  ];
}
