import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { environment } from '../../../environments/environment';
import { HeroService } from './hero.service';

/**
 * Les bandeaux d'en-tête (F12) sont lus **une fois pour tout le site** : douze
 * pages publiques partagent la même liste, un appel par page coûterait bien plus
 * cher que la totalité des données.
 *
 * Ce partage a un revers, découvert en recette et qui donne son sujet à ce
 * fichier : *une fois* voulait dire *une seule fois pour la durée de la
 * session*. L'équipe déposait ses dix photos au back-office, revenait sur le
 * site sans jamais recharger le navigateur — ce qu'une application d'une seule
 * page ne demande jamais — et n'en voyait aucune. Le service doit donc savoir
 * relire, sans rien perdre de son économie d'appels.
 */
describe('HeroService', () => {
  const url = `${environment.apiUrl}/heroes`;

  const reponse = (image: string) => ({
    data: { heroes: { immobilier: { image, eyebrow: null, title: null, lead: null } } },
  });

  const creer = () => {
    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting(), HeroService],
    });

    return {
      service: TestBed.inject(HeroService),
      http: TestBed.inject(HttpTestingController),
    };
  };

  it('ne demande la liste qu’une fois, même lue par plusieurs pages', () => {
    const { service, http } = creer();

    // Trois « pages » lisent le service avant même que la réponse n'arrive.
    service.banner('immobilier');
    service.banner('nuitees');
    service.banner('contact');

    http.expectOne(url).flush(reponse('villa.jpg'));
    http.verify();
  });

  it('relit la liste sur demande, et sert la nouvelle', () => {
    const { service, http } = creer();

    service.banner('immobilier');
    http.expectOne(url).flush(reponse('ancienne.jpg'));
    expect(service.banner('immobilier')?.image).toBe('ancienne.jpg');

    // Geste du back-office après l'enregistrement d'un bandeau.
    service.refresh();
    http.expectOne(url).flush(reponse('nouvelle.jpg'));

    expect(service.banner('immobilier')?.image).toBe('nouvelle.jpg');
    http.verify();
  });

  /**
   * Un bandeau est de la décoration : une panne de réseau doit laisser les pages
   * dans leur apparence d'origine, jamais en erreur. ⚠️ Et surtout, elle ne doit
   * pas **condamner** le service : le `catchError` est posé à l'intérieur du
   * `switchMap` précisément pour qu'un échec n'achève pas le flux et qu'un
   * rechargement ultérieur reste possible.
   */
  it('survit à une panne, et sait encore recharger ensuite', () => {
    const { service, http } = creer();

    service.banner('immobilier');
    http.expectOne(url).error(new ProgressEvent('panne'));
    expect(service.banner('immobilier')).toBeNull();

    service.refresh();
    http.expectOne(url).flush(reponse('revenue.jpg'));

    expect(service.banner('immobilier')?.image).toBe('revenue.jpg');
    http.verify();
  });
});
