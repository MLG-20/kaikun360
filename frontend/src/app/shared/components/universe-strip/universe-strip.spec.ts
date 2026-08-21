import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { UniverseStripService } from '../../../core/api/universe-strip.service';
import { UniverseStripComponent } from './universe-strip';

/**
 * Tests de la bande des univers (F16.2, refaite en F17.3).
 *
 * ⚠️ **Un seul message affiché à la fois, immobile** — remplace un
 * défilement continu en boucle (marquee) jugé confus par le client : deux
 * messages restaient visibles en même temps, tronqués aux bords, et donnaient
 * l'impression que « le texte ne finit jamais de défiler ». Un message
 * s'affiche fixe, disparaît, le suivant prend sa place — voir
 * `universe-strip.ts`.
 */
describe('UniverseStripComponent', () => {
  afterEach(() => {
    TestBed.resetTestingModule();
  });

  async function monter(names: string[]) {
    await TestBed.configureTestingModule({
      imports: [UniverseStripComponent],
      providers: [{ provide: UniverseStripService, useValue: { list: () => of(names) } }],
    }).compileComponents();

    const fixture = TestBed.createComponent(UniverseStripComponent);
    fixture.detectChanges();
    await fixture.whenStable();

    return fixture.nativeElement as HTMLElement;
  }

  it('n’affiche rien tant qu’aucun nom n’est publié', async () => {
    const hote = await monter([]);

    expect(hote.querySelector('.ustrip')).toBeFalsy();
  });

  it('affiche le premier message publié, et lui seul', async () => {
    const hote = await monter(['Immobilier', 'Nuitées', 'Tourisme']);

    const items = Array.from(hote.querySelectorAll('.ustrip-item')).map((el) => el.textContent?.trim());
    expect(items).toEqual(['Immobilier']);
  });

  it('passe au message suivant puis boucle sur le premier', async () => {
    vi.useFakeTimers();

    try {
      const hote = await monter(['Immobilier', 'Nuitées']);
      const texte = () => hote.querySelector('.ustrip-item')?.textContent?.trim();

      expect(texte()).toBe('Immobilier');

      // Les deux messages sont assez courts pour tomber sur le plancher de
      // 4s (10 et 7 caractères < le seuil où la longueur prend le dessus) :
      // une avance d'un peu plus de 4s fait passer EXACTEMENT un message.
      // ⚠️ Variante asynchrone (`advanceTimersByTimeAsync`) : le prochain
      // minuteur est reprogrammé DANS le callback du précédent — la variante
      // synchrone ne laisse pas toujours la main à ce second `setTimeout`
      // imbriqué avant de considérer la fenêtre avancée close.
      await vi.advanceTimersByTimeAsync(4100);
      expect(texte()).toBe('Nuitées');

      await vi.advanceTimersByTimeAsync(4100);
      expect(texte()).toBe('Immobilier');
    } finally {
      vi.useRealTimers();
    }
  });

  /**
   * Correctif du 2026-08-21 (plusieurs passes) : la durée d'affichage doit
   * suivre la longueur du message — un message court affiché aussi longtemps
   * qu'un message long serait lu en un clin d'œil puis attendrait sans
   * raison ; l'inverse (message long affiché aussi peu de temps qu'un mot)
   * était le défaut d'origine, coupant la lecture avant la fin.
   */
  it('accorde plus de temps à un message long qu’à un message court', async () => {
    vi.useFakeTimers();

    try {
      const court = 'Immobilier';
      const long =
        'Notre nouvelle plateforme dédiée au tourisme au Sénégal : découvrez dès aujourd’hui toutes nos offres vérifiées, nos circuits et nos hébergements, partout dans le pays, en toute confiance';
      const hote = await monter([court, long]);
      const texte = () => hote.querySelector('.ustrip-item')?.textContent?.trim();

      expect(texte()).toBe(court);

      // Le message court (10 car.) est déjà passé bien avant 5s (plancher 4s
      // + 10 × 0,07s ≈ 4,7s) ; le message long (≈190 car.) ne l'est pas
      // encore à ce même instant (plancher 4s + 190 × 0,07s ≈ 17,3s).
      await vi.advanceTimersByTimeAsync(5000);
      expect(texte()).toBe(long);

      await vi.advanceTimersByTimeAsync(5000);
      expect(texte()).toBe(long);
    } finally {
      vi.useRealTimers();
    }
  });
});
