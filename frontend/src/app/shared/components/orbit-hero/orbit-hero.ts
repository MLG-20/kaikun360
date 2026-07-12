import { ChangeDetectionStrategy, Component, computed, signal } from '@angular/core';

/** Un univers représenté par un nœud en orbite. `icon` = liste de chemins SVG. */
interface OrbitUniverse {
  title: string;
  price: string;
  cta: string;
  icon: string[];
}

/**
 * « Signature orbitale » (F0.4) — visuel de hero repris de la maquette du client,
 * adapté à la charte Kaikun (bleu/navy/or). Deux anneaux tournent en fond ; les
 * univers sont disposés en cercle ; survoler/cliquer un nœud met à jour la carte
 * centrale. Positions calculées trigonométriquement, interaction via signals.
 */
@Component({
  selector: 'app-orbit-hero',
  templateUrl: './orbit-hero.html',
  styleUrl: './orbit-hero.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OrbitHeroComponent {
  private readonly universes: OrbitUniverse[] = [
    { title: 'Immobilier', price: 'dès 150 000 F / mois', cta: 'Voir les biens', icon: ['M3 10.5 12 3l9 7.5', 'M5 9.5V21h14V9.5', 'M10 21v-6h4v6'] },
    { title: 'Nuitées', price: 'dès 25 000 F / nuit', cta: 'Réserver un séjour', icon: ['M3 8v10', 'M3 18h18v-2a4 4 0 0 0-4-4H3', 'M7 12V9h5v3'] },
    { title: 'Construction', price: 'dès 180 000 F / m²', cta: 'Simuler un chantier', icon: ['M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.3 2.3-2-2z'] },
    { title: 'Transport', price: 'dès 20 000 F / trajet', cta: 'Réserver un trajet', icon: ['M3 13V7h11l4 4v2', 'M2 13h18v4H2z', 'M7 17a1.5 1.5 0 1 0 0 .01', 'M16 17a1.5 1.5 0 1 0 0 .01'] },
    { title: 'Tourisme', price: 'dès 30 000 F / jour', cta: 'Créer un circuit', icon: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M15.5 8.5l-2.2 4.8-4.8 2.2 2.2-4.8z'] },
    { title: 'Diaspora', price: 'orientation gratuite', cta: 'Espace diaspora', icon: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M3 12h18', 'M12 3c3 3.6 3 14.4 0 18', 'M12 3c-3 3.6-3 14.4 0 18'] },
    { title: 'Gestion locative', price: '8 % des loyers', cta: 'Confier un bien', icon: ['M4 20V10', 'M10 20V4', 'M16 20v-7', 'M2 20h20'] },
    { title: 'Team building', price: 'dès 35 000 F / pers', cta: 'Organiser un groupe', icon: ['M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18z', 'M12 8a4 4 0 1 0 0 8 4 4 0 0 0 0-8z', 'M12 11.5a.5.5 0 1 0 0 1 .5.5 0 0 0 0-1z'] },
  ];

  /** Univers + position (% dans le carré) précalculée sur le cercle. */
  protected readonly nodes = this.universes.map((universe, index) => {
    const angle = (index / this.universes.length) * 2 * Math.PI - Math.PI / 2;
    return {
      ...universe,
      left: 50 + 43 * Math.cos(angle),
      top: 50 + 43 * Math.sin(angle),
    };
  });

  protected readonly focused = signal(0);

  /** Univers actuellement mis en avant dans la carte centrale. */
  protected readonly active = computed(() => this.universes[this.focused()]);

  protected focus(index: number): void {
    this.focused.set(index);
  }
}
