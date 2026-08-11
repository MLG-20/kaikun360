import { ChangeDetectionStrategy, Component } from '@angular/core';

import { CatalogComponent } from '../../../shared/components/catalog/catalog';
import { PageHeroComponent } from '../../../shared/components/page-hero/page-hero';

/**
 * Page univers Tourisme (F2.4) — route `/tourisme`.
 *
 * Vitrine des expériences et circuits : bandeau éditorial + catalogue filtrable
 * figé sur l'univers « tourisme ». Le composant `app-catalog` gère
 * filtres/tri/pagination via l'URL ; chaque carte pointe vers la fiche
 * détaillée `/tourisme/:id`.
 */
@Component({
  selector: 'app-experience-list-page',
  imports: [PageHeroComponent, CatalogComponent],
  templateUrl: './experience-list-page.html',
  styleUrl: './experience-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ExperienceListPageComponent {
  /** Points de réassurance affichés dans le bandeau d'introduction. */
  protected readonly highlights = [
    { title: 'Prestataires vérifiés', text: 'Guides et agences contrôlés avant leur mise en ligne.' },
    { title: 'Programmes détaillés', text: 'Inclusions, durée et places restantes affichées clairement.' },
    { title: 'Réservation accompagnée', text: 'Un conseiller confirme votre expérience et le nombre de places.' },
  ];
}
