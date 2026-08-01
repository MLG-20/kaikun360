import { ChangeDetectionStrategy, Component, computed, input } from '@angular/core';

/**
 * Un signal remonté en tête de fiche (F8.3).
 *
 * `anchor` désigne la section qui contient la réponse : un signal qui ne mène
 * nulle part se contente de nommer le problème.
 */
export interface FicheFlag {
  /** `alerte` = le dossier est bloqué ou part de travers ; `vigilance` = à suivre. */
  readonly level: 'alerte' | 'vigilance';
  /** Phrase complète, chiffrée. « 3 loyers échus — 450 000 FCFA », pas « impayés ». */
  readonly text: string;
  /** `id` de la section qui explique le signal. */
  readonly anchor: string;
  /** Libellé du bouton qui y conduit. */
  readonly cta: string;
}

/**
 * Bandeau **« ce qui appelle une décision »** des fiches du back-office (F8.3).
 *
 * **Le défaut qu'il corrige.** Les fiches livrées avant F8.1 ne manquaient pas
 * d'informations — elles les empilaient à plat : cinq à sept cartes de même
 * poids visuel, dont des tableaux de douze lignes. Sur un mandat, un incident
 * critique ouvert et un reversement jamais exécuté se lisaient au même niveau
 * que les clauses du contrat, six écrans plus bas. L'agent avait tout sous les
 * yeux et ne voyait rien.
 *
 * Le bandeau **n'ajoute aucune donnée** : il remonte celles qui changent la
 * conduite du dossier, et conduit à la section qui les explique. Chaque signal
 * est calculé par la fiche qui le connaît — ce composant ne fait que présenter,
 * il n'a aucune règle métier.
 *
 * Il se replie de lui-même : sans signal, aucun bandeau. Un dossier sain ne
 * doit pas porter un encadré « rien à signaler », qui deviendrait du bruit
 * qu'on apprend à ignorer — et avec lui, les vrais signaux.
 */
@Component({
  selector: 'app-fiche-signals',
  templateUrl: './fiche-signals.html',
  styleUrl: './fiche-signals.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class FicheSignalsComponent {
  /** Signaux à présenter, dans l'ordre d'importance décidé par la fiche. */
  readonly flags = input.required<readonly FicheFlag[]>();

  /** Nom du dossier, pour le titre du bandeau (« Ce mandat appelle… »). */
  readonly subject = input('dossier');

  /** Au moins un signal bloquant ? Le bandeau change alors de ton. */
  protected readonly hasAlert = computed(() =>
    this.flags().some((flag) => flag.level === 'alerte'),
  );

  /**
   * Amène la section visée sous les yeux de l'agent, en dépliant le volet
   * qui la contient — un `<details>` fermé avalerait le saut.
   */
  protected goTo(anchor: string): void {
    if (typeof document === 'undefined') {
      return;
    }
    const target = document.getElementById(anchor);
    if (!target) {
      return;
    }
    target.closest('details')?.setAttribute('open', '');
    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}
