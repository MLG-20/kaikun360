import { TestBed } from '@angular/core/testing';
import { of, throwError } from 'rxjs';

import { HeroService } from '../../../core/api/hero.service';
import { WaitlistEntryPayload, WaitlistService } from '../../../core/api/waitlist.service';
import { WaitlistPageComponent } from './waitlist-page';

/**
 * Liste d'attente avant ouverture (2026-08-14). Ce que ces tests protègent :
 * changer de catégorie doit changer le champ requis (et lui seul), et le
 * champ des AUTRES catégories ne doit jamais être envoyé au serveur.
 */
describe('WaitlistPageComponent', () => {
  let created: WaitlistEntryPayload[];

  const setUp = (create: (payload: WaitlistEntryPayload) => ReturnType<WaitlistService['create']>) => {
    created = [];

    TestBed.configureTestingModule({
      imports: [WaitlistPageComponent],
      providers: [
        { provide: HeroService, useValue: { banner: () => null } },
        { provide: WaitlistService, useValue: { create } },
      ],
    });

    const fixture = TestBed.createComponent(WaitlistPageComponent);
    fixture.detectChanges();
    return { fixture, host: fixture.nativeElement as HTMLElement };
  };

  function clickCategory(host: HTMLElement, label: string): void {
    const buttons = Array.from(host.querySelectorAll<HTMLButtonElement>('.waitlist-category'));
    const button = buttons.find((b) => b.textContent?.trim() === label);
    button?.click();
  }

  function setValue(host: HTMLElement, id: string, value: string): void {
    const input = host.querySelector<HTMLInputElement | HTMLSelectElement>(`#${id}`);
    if (!input) return;
    input.value = value;
    input.dispatchEvent(new Event('input'));
    input.dispatchEvent(new Event('change'));
  }

  it("affiche 5 catégories, Propriétaire sélectionnée par défaut", () => {
    const { host } = setUp(() => of({}));

    const buttons = host.querySelectorAll('.waitlist-category');
    expect(buttons.length).toBe(5);
    expect(host.querySelector('.waitlist-category--active')?.textContent?.trim()).toBe(
      'Propriétaire',
    );
    expect(host.querySelector('#wl-type-bien')).toBeTruthy();
    expect(host.querySelector('#wl-type-service')).toBeFalsy();
  });

  it('changer de catégorie change les champs affichés', () => {
    const { fixture, host } = setUp(() => of({}));

    clickCategory(host, 'Team building');
    fixture.detectChanges();

    expect(host.querySelector('#wl-type-bien')).toBeFalsy();
    expect(host.querySelector('#wl-taille-equipe')).toBeTruthy();
  });

  it('envoie uniquement les champs de la catégorie sélectionnée', () => {
    const create = vi.fn((payload: WaitlistEntryPayload) => {
      created.push(payload);
      return of({});
    });
    const { fixture, host } = setUp(create);

    clickCategory(host, 'Diaspora');
    fixture.detectChanges();

    setValue(host, 'wl-name', 'Ibrahima Fall');
    setValue(host, 'wl-phone', '+33612345678');
    setValue(host, 'wl-pays', 'France');
    setValue(host, 'wl-type-projet', 'construction');
    fixture.detectChanges();

    host.querySelector('form')?.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(create).toHaveBeenCalledTimes(1);
    expect(created[0].category).toBe('diaspora');
    expect(created[0].details).toEqual({
      pays_residence: 'France',
      type_projet: 'construction',
    });
    // Aucun champ des 4 autres catégories (type_bien, type_service, …).
    expect(Object.keys(created[0].details)).toEqual(['pays_residence', 'type_projet']);
  });

  it('bloque la soumission tant que le champ requis de la catégorie manque', () => {
    const create = vi.fn(() => of({}));
    const { fixture, host } = setUp(create);

    setValue(host, 'wl-name', 'Awa Diop');
    setValue(host, 'wl-phone', '+221771234567');
    // 'wl-type-bien' jamais renseigné.
    fixture.detectChanges();

    host.querySelector('form')?.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(create).not.toHaveBeenCalled();
  });

  it("affiche l'erreur serveur et permet de recommencer après un échec", () => {
    const create = vi.fn(() =>
      throwError(() => ({
        status: 422,
        error: { message: 'Veuillez corriger les champs indiqués.', errors: { phone: ['Le téléphone est obligatoire.'] } },
      })),
    );
    const { fixture, host } = setUp(create);

    setValue(host, 'wl-name', 'Awa Diop');
    setValue(host, 'wl-phone', '+221771234567');
    setValue(host, 'wl-type-bien', 'villa');
    fixture.detectChanges();

    host.querySelector('form')?.dispatchEvent(new Event('submit'));
    fixture.detectChanges();

    expect(create).toHaveBeenCalledTimes(1);
    expect(host.querySelector('.k-form-error')?.textContent).toContain(
      'Veuillez corriger les champs indiqués.',
    );
  });
});
