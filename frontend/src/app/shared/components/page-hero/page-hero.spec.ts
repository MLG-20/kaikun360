import { TestBed } from '@angular/core/testing';

import { PageHeroComponent } from './page-hero';
import { HeroBanner, HeroService } from '../../../core/api/hero.service';

/**
 * Tests du bandeau d'en-tête (F12).
 *
 * L'enjeu tenu ici est le même que côté serveur, vu de l'autre bout : la
 * personnalisation back-office ne doit JAMAIS pouvoir laisser une page sans
 * titre. Les textes du gabarit restent la valeur par défaut, la surcharge ne
 * fait que passer devant quand elle existe.
 */
describe('PageHeroComponent', () => {
  /** Faux service : on décide bandeau par bandeau ce que le serveur a renvoyé. */
  class FakeHeroService {
    banners: Record<string, HeroBanner> = {};

    banner(key: string): HeroBanner | null {
      return this.banners[key] ?? null;
    }
  }

  let heroes: FakeHeroService;

  beforeEach(async () => {
    heroes = new FakeHeroService();

    await TestBed.configureTestingModule({
      imports: [PageHeroComponent],
      providers: [{ provide: HeroService, useValue: heroes }],
    }).compileComponents();
  });

  async function render(key: string) {
    const fixture = TestBed.createComponent(PageHeroComponent);
    fixture.componentRef.setInput('heroKey', key);
    fixture.componentRef.setInput('eyebrow', 'Univers Immobilier');
    fixture.componentRef.setInput('title', 'Des biens vérifiés');
    fixture.componentRef.setInput('lead', 'Accroche d’origine.');
    await fixture.whenStable();
    return fixture.nativeElement as HTMLElement;
  }

  it('affiche les textes du gabarit quand rien n’a été saisi au back-office', async () => {
    const el = await render('immobilier');

    expect(el.querySelector('.uni-hero-title')?.textContent).toContain('Des biens vérifiés');
    expect(el.querySelector('.k-eyebrow')?.textContent).toContain('Univers Immobilier');
    expect(el.querySelector('.uni-hero-lead')?.textContent).toContain('Accroche d’origine.');
    // Aucune image : le bandeau garde son dégradé, posé par la feuille globale.
    expect(el.querySelector('.uni-hero')?.classList.contains('uni-hero--image')).toBe(false);
  });

  it('laisse la surcharge back-office passer devant', async () => {
    heroes.banners['immobilier'] = {
      image: null,
      eyebrow: null,
      title: 'Nos plus belles adresses',
      lead: null,
    };

    const el = await render('immobilier');

    expect(el.querySelector('.uni-hero-title')?.textContent).toContain('Nos plus belles adresses');
    // Les champs NON surchargés gardent le texte d'origine : une saisie
    // partielle ne doit pas vider le reste du bandeau.
    expect(el.querySelector('.k-eyebrow')?.textContent).toContain('Univers Immobilier');
    expect(el.querySelector('.uni-hero-lead')?.textContent).toContain('Accroche d’origine.');
  });

  it('pose l’image en fond, sous un voile qui protège la lisibilité', async () => {
    heroes.banners['immobilier'] = {
      image: 'https://exemple.test/storage/heroes/villa.jpg',
      eyebrow: null,
      title: null,
      lead: null,
    };

    const el = await render('immobilier');
    const section = el.querySelector('.uni-hero') as HTMLElement;

    expect(section.classList.contains('uni-hero--image')).toBe(true);
    expect(section.style.backgroundImage).toContain('villa.jpg');
    // Le dégradé est empilé AVANT l'image : sans lui, un titre blanc posé sur
    // une photo claire deviendrait illisible.
    expect(section.style.backgroundImage).toContain('linear-gradient');
  });

  it('ne lit que le bandeau de sa propre clé', async () => {
    heroes.banners['nuitees'] = {
      image: 'https://exemple.test/storage/heroes/plage.jpg',
      eyebrow: null,
      title: 'Dormez au bord de l’eau',
      lead: null,
    };

    const el = await render('immobilier');

    expect(el.querySelector('.uni-hero-title')?.textContent).toContain('Des biens vérifiés');
    expect(el.querySelector('.uni-hero')?.classList.contains('uni-hero--image')).toBe(false);
  });
});
