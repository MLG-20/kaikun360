import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Observable } from 'rxjs';

import { AdminService, CatalogQuery } from '../../../core/api/admin.service';
import { Paginated } from '../../../core/api/pagination.model';
import { AdminMobilityService } from '../../../models/mobility-service.model';
import { AdminVehicle } from '../../../models/vehicle.model';

/** Onglet courant : la flotte (véhicules) ou les départs programmés. */
type MobilityTab = 'fleet' | 'trips';

/** Une option de liste déroulante (statut, type…). */
interface SelectOption {
  value: string;
  label: string;
}

/**
 * Verdict de conformité d'un véhicule, tel qu'affiché dans la colonne dédiée.
 * `pending` = contrôle non applicable (type sans exigence renseignée).
 */
type ComplianceVerdict = 'ok' | 'ko' | 'partial';

/**
 * Écran **Mobilité** du back-office (F7.2.j) — module CDC §6 « Mobilité ».
 *
 * Le cahier des charges demande de piloter « véhicules, chauffeurs, pirogues,
 * bus, disponibilités, assurances, capacités ». Deux réalités distinctes se
 * cachent derrière : la **flotte** (des moyens de transport au catalogue, loués
 * à la journée) et les **départs programmés** (des trajets datés que des
 * voyageurs réservent à la place). D'où deux onglets plutôt qu'un tableau unique.
 *
 * - **Flotte** : véhicules tous statuts, avec la colonne de **conformité** qui
 *   est la vraie valeur ajoutée du back-office — assurance manquante, chauffeur
 *   non déclaré, pirogue sans gilets sont visibles d'un coup d'œil. Filtres :
 *   catégorie (bus, pirogue, chauffeur…), statut, présence de chauffeur, recherche.
 * - **Trajets** : départs tous statuts avec leur **remplissage** (places prises
 *   / restantes = les « disponibilités » du cahier). Filtres : nature du trajet,
 *   statut, période de départ, recherche sur départ/destination.
 *
 * Lecture seule, comme les écrans Catalogues (F7.2.b) et Dossiers (F7.2.e) :
 * l'approbation d'un véhicule reste dans l'écran **Validation** (F7.2.a), qui
 * est le point unique de décision. Cet écran-ci sert à *repérer* les anomalies.
 *
 * Endpoints : `GET /admin/vehicles` et `GET /admin/mobility-services`
 * (garde `consulter:dashboard-admin`).
 */
@Component({
  selector: 'app-backoffice-mobility-page',
  imports: [FormsModule],
  templateUrl: './backoffice-mobility-page.html',
  styleUrl: './backoffice-mobility-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeMobilityPageComponent {
  private readonly admin = inject(AdminService);

  /** Onglet courant. */
  protected readonly selected = signal<MobilityTab>('fleet');

  /** Lignes de la page courante (une seule des deux est peuplée à la fois). */
  protected readonly vehicles = signal<AdminVehicle[]>([]);
  protected readonly trips = signal<AdminMobilityService[]>([]);

  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  // --- Filtres (liés au formulaire) ------------------------------------------
  protected status = '';
  protected type = '';
  /** `''` = indifférent, `'1'` = avec chauffeur, `'0'` = sans (onglet Flotte). */
  protected driver = '';
  protected search = '';
  /** Bornes de période sur la date de départ (onglet Trajets). */
  protected from = '';
  protected to = '';

  /** Statuts (identiques pour les véhicules et les trajets). */
  protected readonly statusOptions: readonly SelectOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente_validation', label: 'En attente de validation' },
    { value: 'publie', label: 'Publié' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'rejete', label: 'Rejeté' },
  ];

  /** Catégories de véhicules (miroir de `VehicleType`). */
  protected readonly vehicleTypeOptions: readonly SelectOption[] = [
    { value: '', label: 'Toutes les catégories' },
    { value: 'voiture_particuliere', label: 'Voiture particulière' },
    { value: 'voiture_touristique', label: 'Voiture touristique' },
    { value: 'navette_aibd', label: 'Navette aéroportuaire (AIBD)' },
    { value: 'bus', label: 'Bus' },
    { value: 'minibus', label: 'Minibus' },
    { value: 'quatre_quatre', label: '4x4' },
    { value: 'pirogue', label: 'Pirogue' },
    { value: 'chauffeur', label: 'Chauffeur' },
  ];

  /** Natures de trajet (miroir de `MobilityServiceType`). */
  protected readonly tripTypeOptions: readonly SelectOption[] = [
    { value: '', label: 'Toutes les natures' },
    { value: 'navette', label: 'Navette' },
    { value: 'transfert', label: 'Transfert' },
    { value: 'liaison', label: 'Liaison interurbaine' },
    { value: 'excursion', label: 'Excursion' },
  ];

  /** Options de type selon l'onglet affiché. */
  protected readonly typeOptions = computed(() =>
    this.selected() === 'fleet' ? this.vehicleTypeOptions : this.tripTypeOptions,
  );

  constructor() {
    this.load();
  }

  /**
   * Sélectionne un onglet. Les filtres sont **réinitialisés** au passage : les
   * catégories de véhicules et les natures de trajet n'ont aucune valeur en
   * commun, garder un `type` d'un onglet à l'autre ne renverrait rien.
   */
  protected select(tab: MobilityTab): void {
    if (tab === this.selected()) return;
    this.selected.set(tab);
    this.status = '';
    this.type = '';
    this.driver = '';
    this.search = '';
    this.from = '';
    this.to = '';
    this.page.set(1);
    this.load();
  }

  /** Applique les filtres depuis la première page. */
  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  /** Page précédente / suivante (bornée). */
  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  /** Charge la page courante de l'onglet sélectionné. */
  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const fleet = this.selected() === 'fleet';
    const query: CatalogQuery = {
      status: this.status || undefined,
      type: this.type || undefined,
      q: this.search.trim() || undefined,
      page: this.page(),
      // Le booléen n'est transmis que si l'agent a fait un choix explicite.
      driver: fleet && this.driver !== '' ? this.driver === '1' : undefined,
      from: !fleet && this.from ? this.from : undefined,
      to: !fleet && this.to ? this.to : undefined,
    };

    // Type explicite : sans lui, TypeScript garde l'union des deux signatures
    // d'Observable et ne sait plus typer le `subscribe`.
    const request$: Observable<Paginated<AdminVehicle | AdminMobilityService>> = fleet
      ? this.admin.adminVehicles(query)
      : this.admin.adminMobilityServices(query);

    request$.subscribe({
      next: (paginated) => {
        if (fleet) {
          this.vehicles.set(paginated.data as AdminVehicle[]);
          this.trips.set([]);
        } else {
          this.trips.set(paginated.data as AdminMobilityService[]);
          this.vehicles.set([]);
        }
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  // --- Conformité de la flotte -----------------------------------------------

  /**
   * Verdict de conformité d'un véhicule.
   *
   * Les exigences diffèrent selon le moyen de transport (cf.
   * `VehicleComplianceChecker`, B7.3) : une **pirogue** relève du fluvial
   * (gilets de sauvetage, aptitude météo, prestataire agréé) tandis qu'un
   * transport **motorisé** relève de l'assurance et de l'identité du chauffeur.
   * On applique donc deux grilles distinctes plutôt qu'une case unique.
   */
  protected compliance(v: AdminVehicle): ComplianceVerdict {
    const checks =
      v.type === 'pirogue'
        ? [
            (v.life_jackets_count ?? 0) > 0,
            v.weather_compliant === true,
            v.provider_compliant === true,
          ]
        : [!!v.insurance_ref, !v.has_driver || !!v.driver_identity];

    const passed = checks.filter(Boolean).length;
    if (passed === checks.length) return 'ok';
    if (passed === 0) return 'ko';
    return 'partial';
  }

  /** Libellé du verdict de conformité. */
  protected complianceLabel(v: AdminVehicle): string {
    switch (this.compliance(v)) {
      case 'ok':
        return 'Conforme';
      case 'partial':
        return 'Incomplet';
      default:
        return 'Non conforme';
    }
  }

  /** Classe CSS du badge de conformité. */
  protected complianceClass(v: AdminVehicle): string {
    switch (this.compliance(v)) {
      case 'ok':
        return 'is-ok';
      case 'partial':
        return 'is-warn';
      default:
        return 'is-off';
    }
  }

  /**
   * Détail des manquements d'un véhicule, en clair, pour l'infobulle : l'agent
   * doit savoir QUOI réclamer au prestataire, pas seulement qu'il manque
   * quelque chose.
   */
  protected complianceDetail(v: AdminVehicle): string {
    const missing: string[] = [];

    if (v.type === 'pirogue') {
      if (!(v.life_jackets_count ?? 0)) missing.push('gilets de sauvetage');
      if (v.weather_compliant !== true) missing.push('aptitude météo');
      if (v.provider_compliant !== true) missing.push('agrément prestataire');
    } else {
      if (!v.insurance_ref) missing.push('assurance');
      if (v.has_driver && !v.driver_identity) missing.push('identité du chauffeur');
    }

    return missing.length ? 'Manque : ' + missing.join(', ') : 'Toutes les pièces sont déclarées.';
  }

  // --- Remplissage des trajets -----------------------------------------------

  /** Taux de remplissage d'un départ, en pourcentage borné (barre de jauge). */
  protected fillRate(trip: AdminMobilityService): number {
    if (!trip.capacity) return 0;
    return Math.min(100, Math.round((trip.seats_taken / trip.capacity) * 100));
  }

  /** Classe CSS de la jauge : complet / bien rempli / peu rempli. */
  protected fillClass(trip: AdminMobilityService): string {
    if (trip.seats_left === 0) return 'is-full';
    return this.fillRate(trip) >= 60 ? 'is-high' : 'is-low';
  }

  // --- Formatage --------------------------------------------------------------

  /** Classe CSS du badge de statut (mêmes codes que l'écran Catalogues). */
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

  /** Intitulé lisible d'un véhicule (marque + modèle, ou repli sur le type). */
  protected vehicleLabel(v: AdminVehicle): string {
    return [v.brand, v.model].filter(Boolean).join(' ') || (v.type_label ?? 'Véhicule');
  }

  /** Montant formaté en FCFA (ou tiret si absent). */
  protected xof(value: number | null): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Date + heure courtes (les départs se jouent à l'heure près). */
  protected dateTime(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  }
}
