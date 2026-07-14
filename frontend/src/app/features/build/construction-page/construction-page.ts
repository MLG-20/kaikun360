import { ChangeDetectionStrategy, Component, computed, signal } from '@angular/core';

import { LeadFormComponent } from '../../../shared/components/lead-form/lead-form';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import {
  ConstructionObjective,
  FinishLevel,
  FINISH_LABELS,
  OBJECTIVE_LABELS,
  estimateConstructionCost,
} from '../construction-estimator';

/** Option de choix (objectif ou finition) présentée en boutons segmentés. */
interface Choice<T extends string> {
  value: T;
  label: string;
  hint: string;
}

/**
 * Page univers Construction (F2.5) — route `/construction`.
 *
 * Page de conversion « Kaikun Build » : construire ou rénover à distance, avec
 * un suivi filmé et daté. Elle porte le **simulateur de budget interactif**
 * (calcul en direct, miroir du backend B5.4 — voir `construction-estimator.ts`)
 * annoncé sur l'accueil, puis un formulaire de demande de devis
 * (`service_type = build`).
 */
@Component({
  selector: 'app-construction-page',
  imports: [LeadFormComponent],
  templateUrl: './construction-page.html',
  styleUrl: './construction-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConstructionPageComponent {
  /** Étapes de l'accompagnement, présentées en 1-2-3. */
  protected readonly steps = [
    {
      num: '1',
      title: 'Vous décrivez votre projet',
      text: 'Type de travaux, surface, standing et budget : le simulateur donne une première fourchette.',
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

  // --- Simulateur de budget interactif --------------------------------------
  /** Objectifs proposés (miroir de l'enum backend). */
  protected readonly objectives: Choice<ConstructionObjective>[] = [
    { value: 'construction_neuve', label: OBJECTIVE_LABELS.construction_neuve, hint: 'Bâtir du neuf, du terrain au clé en main' },
    { value: 'extension', label: OBJECTIVE_LABELS.extension, hint: 'Agrandir un bâti existant' },
    { value: 'renovation', label: OBJECTIVE_LABELS.renovation, hint: 'Rénover ou réhabiliter' },
  ];

  /** Niveaux de finition proposés (miroir de l'enum backend). */
  protected readonly finishes: Choice<FinishLevel>[] = [
    { value: 'economique', label: FINISH_LABELS.economique, hint: 'Prestations essentielles' },
    { value: 'standard', label: FINISH_LABELS.standard, hint: 'Bon rapport qualité/prix' },
    { value: 'premium', label: FINISH_LABELS.premium, hint: 'Matériaux et finitions haut de gamme' },
  ];

  /** Sélections courantes du simulateur (signaux). */
  protected readonly objective = signal<ConstructionObjective>('construction_neuve');
  protected readonly finish = signal<FinishLevel>('standard');
  protected readonly surface = signal(120);

  /** Estimation indicative recalculée à chaque changement (calcul en direct). */
  protected readonly estimate = computed(() =>
    estimateConstructionCost(this.objective(), this.surface(), this.finish()),
  );

  /** Estimation formatée en FCFA. */
  protected readonly estimateLabel = computed(() => formatFcfa(this.estimate()));

  /**
   * Message pré-rempli du formulaire de devis, synchronisé avec le simulateur :
   * le conseiller reçoit directement le contexte de l'estimation.
   */
  protected readonly leadMessage = computed(() => {
    const objectiveLabel = OBJECTIVE_LABELS[this.objective()];
    const finishLabel = FINISH_LABELS[this.finish()];
    return (
      `Bonjour, je souhaite un devis de construction.\n\n` +
      `Projet : ${objectiveLabel}\n` +
      `Surface : ${this.surface()} m²\n` +
      `Finition : ${finishLabel}\n` +
      `Estimation indicative du simulateur : ${this.estimateLabel()}.`
    );
  });

  /** Met à jour la surface depuis le curseur/champ (borne basse à 1 m²). */
  protected setSurface(value: string | number): void {
    const parsed = typeof value === 'number' ? value : parseInt(value, 10);
    this.surface.set(Number.isFinite(parsed) && parsed > 0 ? parsed : 1);
  }
}
