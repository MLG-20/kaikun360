import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { Observable, catchError, debounceTime, distinctUntilChanged, map, of, startWith, switchMap } from 'rxjs';

import { ConstructionRequestFormComponent } from '../construction-request-form/construction-request-form';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import {
  ConstructionObjective,
  ConstructionService,
  FinishLevel,
  RegionOption,
  RentalMode,
  Simulation,
  SimulatePayload,
  FINISH_LABELS,
  OBJECTIVE_LABELS,
  RENTAL_MODE_LABELS,
  SENEGAL_REGIONS,
  ZONE_LABELS,
} from '../../../core/api/construction.service';

/** Option de choix présentée en boutons segmentés. */
interface Choice<T extends string> {
  value: T;
  label: string;
  hint: string;
}

/** Niveaux proposés (nombre de niveaux bâtis). */
interface LevelChoice {
  value: number;
  label: string;
  hint: string;
}

/** État de la simulation renvoyée par le backend. */
type SimState =
  | { status: 'loading' }
  | { status: 'ready'; data: Simulation }
  | { status: 'error' };

/**
 * Page univers Construction (F2.5, enrichie) — route `/construction`.
 *
 * Page de conversion « Kaikun Build ». Le **simulateur** collecte les paramètres
 * du projet (objectif, surface, niveaux, finition, zone, foncier) et interroge
 * l'endpoint PUBLIC `POST /construction-requests/simulate` : tout le chiffrage
 * (travaux, frais annexes, foncier, délai, jalons, rentabilité) vient du backend,
 * seule source de vérité, dont le barème est géré au back-office.
 *
 * ⚠️ **F8.15.b — le formulaire ne dépose plus une demande générique.** Il envoie
 * un vrai dossier de chantier (`POST /construction-requests`), en réutilisant les
 * paramètres déjà réglés au simulateur. Avant, la demande partait dans `requests`
 * avec le simulateur résumé en TEXTE : elle n'atteignait jamais l'écran
 * back-office « Construction », aucun jalon n'était semé et aucun devis ventilé
 * par lot n'était composable — tout l'aval du module était inatteignable.
 */
@Component({
  selector: 'app-construction-page',
  imports: [ConstructionRequestFormComponent],
  templateUrl: './construction-page.html',
  styleUrl: './construction-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConstructionPageComponent {
  private readonly construction = inject(ConstructionService);

  /** Étapes de l'accompagnement, présentées en 1-2-3. */
  protected readonly steps = [
    {
      num: '1',
      title: 'Vous décrivez votre projet',
      text: 'Type de travaux, surface, niveaux, standing, zone et foncier : le simulateur donne une première fourchette.',
    },
    {
      num: '2',
      title: 'Nous chiffrons et planifions',
      text: 'Un conducteur de travaux vérifié établit un devis ferme et un calendrier de chantier.',
    },
    {
      num: '3',
      title: 'Vous suivez, filmé et daté',
      text: 'Chaque étape est archivée : photos, vidéos et rapports horodatés, où que vous soyez.',
    },
  ];

  /** Arguments de réassurance de l'univers Construction. */
  protected readonly highlights = [
    { title: 'Artisans vérifiés', text: 'Entreprises et artisans contrôlés avant toute mise en relation.' },
    { title: 'Suivi à distance', text: 'Reporting photo/vidéo horodaté, idéal pour la diaspora.' },
    { title: 'Paiements échelonnés', text: 'Décaissements par jalons validés, en FCFA (Wave, Orange Money, virement).' },
  ];

  // --- Choix du simulateur --------------------------------------------------
  protected readonly objectives: Choice<ConstructionObjective>[] = [
    { value: 'construction_neuve', label: OBJECTIVE_LABELS.construction_neuve, hint: 'Bâtir du neuf, du terrain au clé en main' },
    { value: 'extension', label: OBJECTIVE_LABELS.extension, hint: 'Agrandir un bâti existant' },
    { value: 'renovation', label: OBJECTIVE_LABELS.renovation, hint: 'Rénover ou réhabiliter' },
  ];

  protected readonly finishes: Choice<FinishLevel>[] = [
    { value: 'economique', label: FINISH_LABELS.economique, hint: 'Prestations essentielles' },
    { value: 'standard', label: FINISH_LABELS.standard, hint: 'Bon rapport qualité/prix' },
    { value: 'premium', label: FINISH_LABELS.premium, hint: 'Matériaux et finitions haut de gamme' },
  ];

  /** Les 14 régions proposées dans le menu déroulant de localisation. */
  protected readonly regions: RegionOption[] = SENEGAL_REGIONS;

  /** Libellés lisibles des zones (pour afficher la zone dérivée d'une région). */
  protected readonly zoneLabels = ZONE_LABELS;

  protected readonly levelChoices: LevelChoice[] = [
    { value: 1, label: 'Plain-pied', hint: 'RDC seul' },
    { value: 2, label: 'R+1', hint: 'RDC + 1 étage' },
    { value: 3, label: 'R+2', hint: 'RDC + 2 étages' },
  ];

  protected readonly rentalModes: Choice<RentalMode>[] = [
    { value: 'longue_duree', label: RENTAL_MODE_LABELS.longue_duree, hint: 'Bail au mois, revenu régulier' },
    { value: 'nuitee', label: RENTAL_MODE_LABELS.nuitee, hint: 'Meublé touristique, rendement plus élevé' },
  ];

  // --- Paramètres saisis (signaux) ------------------------------------------
  protected readonly objective = signal<ConstructionObjective>('construction_neuve');
  protected readonly finish = signal<FinishLevel>('standard');
  protected readonly surface = signal(120);
  protected readonly levels = signal(1);

  /** Région choisie dans le menu déroulant ; la zone de coût en est dérivée. */
  protected readonly region = signal<RegionOption>(SENEGAL_REGIONS[0]);
  protected readonly zone = computed(() => this.region().zone);

  /** Budget / capacité d'investissement saisi(e) (0 = non renseigné). */
  protected readonly budget = signal(0);

  /** Foncier : terrain déjà possédé (par défaut) ou à acquérir. */
  protected readonly landOwned = signal(true);
  protected readonly landCost = signal(0);

  /** Rentabilité : projection affichée seulement si l'utilisateur l'active. */
  protected readonly rentalOpen = signal(false);
  protected readonly rentalMode = signal<RentalMode>('longue_duree');

  // --- Appel backend (source unique du calcul) ------------------------------
  /** Paramètres envoyés au simulateur, recomposés à chaque changement. */
  private readonly payload = computed<SimulatePayload>(() => ({
    objective: this.objective(),
    surface_m2: this.surface(),
    finish_level: this.finish(),
    levels: this.levels(),
    zone: this.zone(),
    land_cost_xof: this.landOwned() ? 0 : this.landCost(),
  }));

  /** Flux du résultat backend (débouncé), avec états loading / ready / error. */
  private readonly sim$: Observable<SimState> = toObservable(this.payload).pipe(
    debounceTime(250),
    distinctUntilChanged((a, b) => JSON.stringify(a) === JSON.stringify(b)),
    switchMap((payload) =>
      this.construction.simulate(payload).pipe(
        map((res): SimState => ({ status: 'ready', data: res.data.simulation })),
        startWith({ status: 'loading' } as SimState),
        catchError(() => of({ status: 'error' } as SimState)),
      ),
    ),
  );

  private readonly sim = toSignal(this.sim$, { initialValue: { status: 'loading' } as SimState });

  /** La simulation courante, ou `null` tant qu'elle n'est pas prête. */
  protected readonly simulation = computed<Simulation | null>(() => {
    const s = this.sim();
    return s.status === 'ready' ? s.data : null;
  });

  protected readonly loading = computed(() => this.sim().status === 'loading');
  protected readonly failed = computed(() => this.sim().status === 'error');

  /** Formatage FCFA réutilisable dans le template. */
  protected readonly fcfa = formatFcfa;

  /** Convertit un ratio (0–1) en pourcentage entier (barres / libellés). */
  protected pct(ratio: number): number {
    return Math.round(ratio * 100);
  }

  /**
   * Verdict de faisabilité : compare le budget saisi au coût total du projet.
   * `null` tant qu'aucun budget n'est renseigné ou que la simulation n'est pas
   * prête. Sinon `{ covers, diff }` où `diff` est la marge (>0) ou le manque (<0).
   */
  protected readonly budgetVerdict = computed<{ covers: boolean; diff: number } | null>(() => {
    const budget = this.budget();
    const sim = this.simulation();
    if (budget <= 0 || !sim) {
      return null;
    }
    const diff = budget - sim.grand_total_xof;
    return { covers: diff >= 0, diff };
  });

  /**
   * Message pré-rempli du formulaire de devis, synchronisé avec le simulateur :
   * le conseiller reçoit directement le contexte complet de l'estimation.
   */
  protected readonly leadMessage = computed(() => {
    const objectiveLabel = OBJECTIVE_LABELS[this.objective()];
    const finishLabel = FINISH_LABELS[this.finish()];
    const zoneLabel = ZONE_LABELS[this.zone()];
    const sim = this.simulation();

    let message =
      `Bonjour, je souhaite un devis de construction.\n\n` +
      `Projet : ${objectiveLabel}\n` +
      `Surface au sol : ${this.surface()} m² · Niveaux : ${this.levels()}\n` +
      `Finition : ${finishLabel}\n` +
      `Localisation : ${this.region().name} (${zoneLabel})\n` +
      `Foncier : ${this.landOwned() ? 'terrain déjà possédé' : `terrain à acquérir (~${this.fcfa(this.landCost())})`}`;

    if (this.budget() > 0) {
      message += `\nBudget disponible : ${this.fcfa(this.budget())}`;
    }

    if (sim) {
      message +=
        `\n\nEstimation indicative du simulateur :\n` +
        `- Travaux : ${this.fcfa(sim.works.cost_xof)}\n` +
        `- Frais annexes : ${this.fcfa(sim.fees.total_xof)}\n` +
        `- Total projet : ${this.fcfa(sim.grand_total_xof)}\n` +
        `- Délai indicatif : ${sim.duration.min_months} à ${sim.duration.max_months} mois.`;

      if (this.rentalOpen()) {
        const r = sim.rental[this.rentalMode()];
        message +=
          `\n\nProjet locatif (${RENTAL_MODE_LABELS[this.rentalMode()]}) : ` +
          `revenu estimé ~${this.fcfa(r.monthly_income_xof)}/mois ` +
          `(rendement ${r.yield_min_pct}–${r.yield_max_pct} %/an).`;
      }
    }
    return message;
  });

  /** Met à jour la surface depuis le curseur (borne basse à 1 m²). */
  protected setSurface(value: string | number): void {
    const parsed = typeof value === 'number' ? value : parseInt(value, 10);
    this.surface.set(Number.isFinite(parsed) && parsed > 0 ? parsed : 1);
  }

  /** Met à jour le coût du terrain saisi (0 si vide/invalide). */
  protected setLandCost(value: string): void {
    this.landCost.set(this.parseAmount(value));
  }

  /** Met à jour le budget/capacité saisi(e) (0 si vide/invalide). */
  protected setBudget(value: string): void {
    this.budget.set(this.parseAmount(value));
  }

  /** Sélectionne la région par son nom (depuis le menu déroulant). */
  protected setRegion(name: string): void {
    const found = this.regions.find((r) => r.name === name);
    if (found) {
      this.region.set(found);
    }
  }

  /** Parse un montant FCFA saisi (tolère les espaces) ; 0 si invalide. */
  private parseAmount(value: string): number {
    const parsed = parseInt(value.replace(/\s/g, ''), 10);
    return Number.isFinite(parsed) && parsed > 0 ? parsed : 0;
  }
}
