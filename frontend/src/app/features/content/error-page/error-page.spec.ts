import { TestBed } from '@angular/core/testing';
import { ActivatedRoute, Router, convertToParamMap } from '@angular/router';
import { of } from 'rxjs';

import { ErrorPageComponent } from './error-page';

/**
 * La page de secours (F10.1.a).
 *
 * Un seul comportement mérite d'être verrouillé, mais il compte : le bouton
 * « Réessayer » est construit à partir d'un paramètre d'URL, donc à partir de
 * ce que **n'importe qui** peut écrire dans un lien. Une page d'erreur est
 * précisément celle qu'on envoie à quelqu'un d'inquiet ; en faire un tremplin
 * vers un domaine étranger serait offrir un hameçonnage sous notre marque.
 */
describe('ErrorPageComponent', () => {
  const monter = (depuis: string | null, kind = 'serveur') => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        {
          provide: ActivatedRoute,
          useValue: {
            data: of({ kind }),
            queryParamMap: of(convertToParamMap(depuis === null ? {} : { depuis })),
          },
        },
        { provide: Router, useValue: { navigateByUrl: vi.fn() } },
      ],
    });

    return TestBed.createComponent(ErrorPageComponent).componentInstance as unknown as {
      retour: () => string | null;
      reessayer: () => void;
    };
  };

  it('propose de reprendre là où on en était', () => {
    expect(monter('/immobilier/98').retour()).toBe('/immobilier/98');
  });

  it('refuse toute adresse sortante', () => {
    expect(monter('https://evil.test').retour()).toBeNull();
    // Forme protocole-relative : un `//` que le navigateur résout en domaine externe.
    expect(monter('//evil.test').retour()).toBeNull();
    expect(monter('javascript:alert(1)').retour()).toBeNull();
  });

  it('ne propose rien quand on ne sait pas d’où l’on vient', () => {
    expect(monter(null).retour()).toBeNull();
  });

  it('ne se renvoie pas sur elle-même', () => {
    // Sinon « Réessayer » rechargerait la page d'erreur, indéfiniment.
    expect(monter('/erreur?depuis=/immobilier').retour()).toBeNull();
  });
});
