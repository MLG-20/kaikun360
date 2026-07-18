import { Location } from '@angular/common';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { vi } from 'vitest';

import { BackLinkComponent } from './back-link';

describe('BackLinkComponent', () => {
  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [BackLinkComponent],
      providers: [provideRouter([])],
    }).compileComponents();
  });

  it('affiche « ← Retour » par défaut', async () => {
    const fixture = TestBed.createComponent(BackLinkComponent);
    await fixture.whenStable();
    const btn = (fixture.nativeElement as HTMLElement).querySelector('button');
    expect(btn?.textContent?.trim()).toContain('← Retour');
  });

  it('reprend le libellé fourni', async () => {
    const fixture = TestBed.createComponent(BackLinkComponent);
    fixture.componentRef.setInput('label', 'Mes réservations');
    await fixture.whenStable();
    const btn = (fixture.nativeElement as HTMLElement).querySelector('button');
    expect(btn?.textContent?.trim()).toContain('← Mes réservations');
  });

  it('revient en arrière quand l\'historique le permet', async () => {
    const back = vi.spyOn(Location.prototype, 'back').mockImplementation(() => {});
    // Simule un historique non vide.
    vi.spyOn(window.history, 'length', 'get').mockReturnValue(3);

    const fixture = TestBed.createComponent(BackLinkComponent);
    await fixture.whenStable();
    (fixture.nativeElement as HTMLElement).querySelector('button')!.click();

    expect(back).toHaveBeenCalled();
  });
});
