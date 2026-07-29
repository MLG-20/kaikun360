import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AdminService, CatalogQuery, TourismDestination } from '../../../core/api/admin.service';
import { AdminExperience } from '../../../models/experience.model';
import { Provider } from '../../../models/provider.model';

/** Onglet courant. */
type TourismTab = 'circuits' | 'destinations' | 'partners';

/** Une option de liste déroulante. */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * Écran **Tourisme** du back-office (F7.2.k) — module CDC §6 « Tourisme ».
 *
 * Le cahier des charges demande de piloter « circuits, destinations,
 * programmes, guides, restaurants, capacités groupes ». Ces six éléments ne
 * vivent pas au même endroit dans le modèle de données, d'où trois onglets :
 *
 * - **Circuits** (`GET /admin/experiences`, tous statuts) — les circuits
 *   eux-mêmes, avec leur **capacité groupe** (jauge places prises / restantes)
 *   et leur **programme**, c'est-à-dire les inclusions structurées de la
 *   prestation (restauration, guide, transport…). Filtres : statut,
 *   destination, recherche.
 * - **Destinations** (`GET /admin/tourism/destinations`) — vue agrégée. Une
 *   destination n'est pas une entité en base mais une colonne des circuits :
 *   on la restitue par agrégation, ce qui répond à la vraie question de
 *   l'équipe — quelles destinations sont couvertes, lesquelles n'ont que des
 *   circuits en attente, lesquelles sont saturées. Un clic bascule sur
 *   l'onglet Circuits filtré sur cette destination.
 * - **Guides & restaurants** (`GET /admin/providers?category=guide,restauration`)
 *   — ⚠️ ce ne sont **pas** des entités du module Explore : le modèle ne les
 *   connaît que comme **catégories de prestataires** de la marketplace Pro, et
 *   comme simples drapeaux d'inclusion sur un circuit. Cet onglet les expose
 *   donc via la marketplace. Il n'existe **aucun rattachement d'un guide
 *   nommé à un circuit** — écart au cahier des charges signalé, pas comblé ici
 *   (il demanderait un modèle d'affectation, hors périmètre de cette tranche).
 *
 * Lecture seule, comme Catalogues (F7.2.b) et Mobilité (F7.2.j) : l'approbation
 * d'un circuit reste dans l'écran **Validation** (F7.2.a) et les sanctions
 * prestataires dans **Avis & qualité** (F7.2.g).
 */
@Component({
  selector: 'app-backoffice-tourism-page',
  imports: [FormsModule],
  templateUrl: './backoffice-tourism-page.html',
  styleUrl: './backoffice-tourism-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeTourismPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly selected = signal<TourismTab>('circuits');

  /** Données de l'onglet courant (une seule collection est peuplée à la fois). */
  protected readonly circuits = signal<AdminExperience[]>([]);
  protected readonly destinations = signal<TourismDestination[]>([]);
  protected readonly partners = signal<Provider[]>([]);

  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  // --- Filtres ---------------------------------------------------------------
  protected status = '';
  protected search = '';
  /** Destination épinglée depuis l'onglet Destinations (onglet Circuits). */
  protected destination = '';
  /** Catégorie de partenaire affichée (onglet Guides & restaurants). */
  protected category = 'guide,restauration';

  /** Statuts d'un circuit (miroir d'`ExperienceStatus`). */
  protected readonly statusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente_validation', label: 'En attente de validation' },
    { value: 'publie', label: 'Publié' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'rejete', label: 'Rejeté' },
  ];

  /** Statuts d'un prestataire (miroir de `ProviderStatus`). */
  protected readonly partnerStatusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'valide', label: 'Validé' },
    { value: 'refuse', label: 'Refusé' },
    { value: 'suspendu', label: 'Suspendu' },
  ];

  /** Catégories proposées dans l'onglet partenaires. */
  protected readonly categoryOptions: readonly SelectOption[] = [
    { value: 'guide,restauration', label: 'Guides et restaurants' },
    { value: 'guide', label: 'Guides touristiques' },
    { value: 'restauration', label: 'Restauration' },
  ];

  constructor() {
    this.load();
  }

  /** Sélectionne un onglet et remet les filtres à zéro (jeux disjoints). */
  protected select(tab: TourismTab): void {
    if (tab === this.selected()) return;
    this.selected.set(tab);
    this.status = '';
    this.search = '';
    this.destination = '';
    this.category = 'guide,restauration';
    this.page.set(1);
    this.load();
  }

  /** Applique les filtres depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  /** Page précédente / suivante (bornée ; les destinations ne paginent pas). */
  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /**
   * Ouvre les circuits d'une destination : c'est le chaînage qui rend l'onglet
   * Destinations actionnable plutôt que purement informatif.
   */
  protected openDestination(destination: string): void {
    this.selected.set('circuits');
    this.status = '';
    this.search = '';
    this.destination = destination;
    this.page.set(1);
    this.load();
  }

  /** Retire le filtre de destination épinglé. */
  protected clearDestination(): void {
    this.destination = '';
    this.applyFilters();
  }

  /** Charge l'onglet courant. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    switch (this.selected()) {
      case 'circuits':
        return this.loadCircuits();
      case 'destinations':
        return this.loadDestinations();
      default:
        return this.loadPartners();
    }
  }

  private loadCircuits(): void {
    const query: CatalogQuery = {
      status: this.status || undefined,
      q: this.search.trim() || undefined,
      destination: this.destination || undefined,
      page: this.page(),
    };

    this.admin.adminExperiences(query).subscribe({
      next: (paginated) => {
        this.circuits.set(paginated.data);
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => this.fail(),
    });
  }

  private loadDestinations(): void {
    this.admin.adminTourismDestinations(this.search.trim() || undefined).subscribe({
      next: (destinations) => {
        this.destinations.set(destinations);
        this.total.set(destinations.length);
        // Agrégat non paginé : une seule page, toujours.
        this.lastPage.set(1);
        this.loading.set(false);
      },
      error: () => this.fail(),
    });
  }

  private loadPartners(): void {
    this.admin
      .adminProviders({
        category: this.category,
        status: this.status || undefined,
        q: this.search.trim() || undefined,
        page: this.page(),
      })
      .subscribe({
        next: (paginated) => {
          this.partners.set(paginated.data);
          this.total.set(paginated.meta.total);
          this.lastPage.set(paginated.meta.last_page);
          this.loading.set(false);
        },
        error: () => this.fail(),
      });
  }

  /** Bascule en état d'erreur (message unique, quel que soit l'onglet). */
  private fail(): void {
    this.error.set(true);
    this.loading.set(false);
  }

  // --- Programme (inclusions) -------------------------------------------------

  /**
   * Inclusions actives d'un circuit, en libellés lisibles — le « programme » au
   * sens du cahier des charges.
   *
   * Le backend renvoie `[]` (tableau vide) quand rien n'est renseigné et un
   * objet `{ clé: booléen }` sinon : les deux formes sont traitées.
   */
  protected programme(circuit: AdminExperience): string[] {
    const inclusions = circuit.inclusions;
    if (!inclusions || Array.isArray(inclusions)) return [];

    return Object.entries(inclusions)
      .filter(([, included]) => included === true)
      .map(([key]) => this.inclusionLabel(key));
  }

  /** Libellé d'une clé d'inclusion (repli : la clé telle quelle). */
  private inclusionLabel(key: string): string {
    switch (key) {
      case 'restauration':
        return 'Restauration';
      case 'guide':
        return 'Guide';
      case 'transport':
        return 'Transport';
      case 'hebergement':
        return 'Hébergement';
      default:
        return key;
    }
  }

  // --- Remplissage -----------------------------------------------------------

  /** Taux de remplissage en pourcentage borné (jauge). */
  protected fillRate(capacity: number, taken: number): number {
    if (!capacity) return 0;
    return Math.min(100, Math.round((taken / capacity) * 100));
  }

  /** Classe CSS de la jauge : complet / bien rempli / peu rempli. */
  protected fillClass(capacity: number, taken: number): string {
    if (capacity > 0 && taken >= capacity) return 'is-full';
    return this.fillRate(capacity, taken) >= 60 ? 'is-high' : 'is-low';
  }

  // --- Formatage --------------------------------------------------------------

  /** Classe CSS du badge de statut d'un circuit. */
  protected statusClass(status: string | null): string {
    switch (status) {
      case 'publie':
        return 'is-ok';
      case 'en_attente_validation':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'rejete':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Classe CSS du badge de statut d'un prestataire. */
  protected partnerStatusClass(status: string | null): string {
    switch (status) {
      case 'valide':
        return 'is-ok';
      case 'en_attente':
        return 'is-pending';
      case 'suspendu':
        return 'is-warn';
      case 'refuse':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Montant formaté en FCFA (ou tiret si absent). */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Fourchette de prix d'une destination (un seul montant si min = max). */
  protected priceRange(d: TourismDestination): string {
    if (!d.price_min && !d.price_max) return '—';
    if (d.price_min === d.price_max) return this.xof(d.price_min);
    return `${this.xof(d.price_min)} – ${this.xof(d.price_max)}`;
  }

  /** Note agrégée d'un prestataire (ou tiret si jamais noté). */
  protected rating(p: Provider): string {
    if (!p.rating_count) return '—';
    return `${Number(p.rating_avg).toFixed(1)} / 5 (${p.rating_count})`;
  }
}
