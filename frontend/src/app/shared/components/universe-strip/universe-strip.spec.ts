import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { UniverseStripService } from '../../../core/api/universe-strip.service';
import { UniverseStripComponent } from './universe-strip';

/**
 * Tests de la bande défilante des univers (F16.2).
 *
 * Ce qui compte n'est pas l'animation (jugée à l'œil) mais que la piste porte
 * bien la liste DEUX FOIS — c'est cette duplication qui rend la boucle
 * invisible (`translateX(-50%)` ramène la seconde moitié à la position de
 * départ de la première) — et qu'une plateforme sans univers publié
 * n'affiche rien plutôt qu'une bande vide.
 */
describe('UniverseStripComponent', () => {
  async function monter(names: string[]) {
    await TestBed.configureTestingModule({
      imports: [UniverseStripComponent],
      providers: [{ provide: UniverseStripService, useValue: { list: () => of(names) } }],
    }).compileComponents();

    const fixture = TestBed.createComponent(UniverseStripComponent);
    fixture.detectChanges();

    return fixture.nativeElement as HTMLElement;
  }

  it('n’affiche rien tant qu’aucun nom n’est publié', async () => {
    const hote = await monter([]);

    expect(hote.querySelector('.ustrip')).toBeFalsy();
  });

  it('duplique la liste pour une boucle sans coupure', async () => {
    const hote = await monter(['Immobilier', 'Nuitées', 'Tourisme']);

    const items = Array.from(hote.querySelectorAll('.ustrip-item')).map((el) => el.textContent?.trim());
    expect(items).toEqual(['Immobilier', 'Nuitées', 'Tourisme', 'Immobilier', 'Nuitées', 'Tourisme']);
  });
});
