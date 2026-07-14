import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

import { CatalogComponent } from '../../../shared/components/catalog/catalog';
import { WhatsAppButtonComponent } from '../../../shared/components/whatsapp-button/whatsapp-button';

/**
 * Page univers Mobilité (F2.4) — route `/mobilite`.
 *
 * Vitrine des services de mobilité (navettes, transferts, liaisons,
 * excursions) : bandeau éditorial + catalogue filtrable figé sur l'univers
 * « mobilite » (recherche par départ/destination/date). Le backend n'expose pas
 * d'endpoint de détail pour un service de mobilité (index + réservation
 * seulement) : les cartes ne sont donc pas cliquables vers une fiche ; la
 * réservation se fait via un conseiller. Un renvoi vers le Transport
 * (`/transport`) est proposé pour la location de véhicules.
 */
@Component({
  selector: 'app-mobility-list-page',
  imports: [CatalogComponent, RouterLink, WhatsAppButtonComponent],
  templateUrl: './mobility-list-page.html',
  styleUrl: './mobility-list-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MobilityListPageComponent {
  /** Points de réassurance affichés dans le bandeau d'introduction. */
  protected readonly highlights = [
    { title: 'Navettes & transferts', text: 'Liaisons aéroport, interurbaines et excursions organisées.' },
    { title: 'Prestataires vérifiés', text: 'Opérateurs contrôlés avant leur mise en ligne.' },
    { title: 'Départs planifiés', text: 'Filtrez par départ, destination et date de trajet.' },
  ];
}
