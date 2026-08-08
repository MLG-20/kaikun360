import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { Router, provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { AuthService } from '../auth/auth.service';
import { SKIP_ERROR_REDIRECT } from '../interceptors/error.interceptor';
import { ASSISTANT_HISTORY_TURNS, AssistantStore } from './assistant-store';

/** Session simulée : le store ne regarde qu'`isAuthenticated` et `user`. */
const session = signal(false);
const compte = signal<unknown>(null);

/**
 * La conversation avec l'assistant (F10.1).
 *
 * Ce qui est verrouillé ici, ce sont les règles qui font la différence entre un
 * gadget et un outil sûr : l'**historique est borné** (sinon chaque message
 * renvoie toute la conversation, et à partir de F10.4 chaque tour est payé), un
 * **503 fait disparaître la bulle** au lieu de laisser un bouton qui s'excuse,
 * une **URL sortante n'est jamais suivie** (le jour où c'est un modèle de
 * langage qui les produit, ce serait une porte d'hameçonnage), et **rien n'est
 * envoyé pendant qu'une réponse est en route** — trois clics rapides suffiraient
 * sinon à épuiser le quota de 12 messages par minute.
 */
describe('AssistantStore', () => {
  let store: AssistantStore;
  let http: HttpTestingController;
  let router: Router;

  const url = `${environment.apiUrl}/assistant/messages`;

  /** Réponse minimale du serveur, forme de `AssistantReply::toArray()`. */
  const reponse = (text: string, actions: unknown[] = []) => ({
    data: { reply: { text, items: [], actions, tool: null } },
  });

  beforeEach(() => {
    session.set(false);
    compte.set(null);

    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { isAuthenticated: session, user: compte } },
      ],
    });

    store = TestBed.inject(AssistantStore);
    http = TestBed.inject(HttpTestingController);
    router = TestBed.inject(Router);
  });

  afterEach(() => http.verify());

  it("pose l'accueil sans appeler le serveur", () => {
    store.ouvrir();

    expect(store.estOuvert()).toBe(true);
    expect(store.bulles()).toHaveLength(1);
    expect(store.bulles()[0].speaker).toBe('assistant');
    // Dire bonjour ne doit rien coûter : ni un aller-retour, ni un appel facturé.
    http.expectNone(url);
  });

  it('envoie le message et affiche la réponse', () => {
    store.ouvrir();
    store.envoyer('une villa à Saly');

    const requete = http.expectOne(url);
    expect(requete.request.body.message).toBe('une villa à Saly');
    // Premier message : aucune clé `history` inutile dans la charge utile.
    expect(requete.request.body.history).toBeUndefined();

    requete.flush(reponse('Voici 3 biens à Saly.'));

    expect(store.bulles().at(-1)?.text).toBe('Voici 3 biens à Saly.');
    expect(store.attenteReponse()).toBe(false);
  });

  it("n'envoie ni le vide ni un second message pendant l'attente", () => {
    store.ouvrir();
    store.envoyer('   ');
    http.expectNone(url);

    store.envoyer('bonjour');
    http.expectOne(url); // la requête reste en vol

    store.envoyer('toujours là ?');
    http.expectNone(url);
  });

  it("borne l'historique renvoyé au serveur", () => {
    store.ouvrir();

    // Vingt tours (dix échanges) : bien au-delà du plafond.
    for (let i = 0; i < 12; i++) {
      store.envoyer(`question ${i}`);
      http.expectOne(url).flush(reponse(`réponse ${i}`));
    }

    store.envoyer('la dernière');
    const requete = http.expectOne(url);
    requete.flush(reponse('fin'));

    const historique = requete.request.body.history as { role: string; text: string }[];
    expect(historique).toHaveLength(ASSISTANT_HISTORY_TURNS);
    // Ce sont les DERNIERS tours qui sont gardés (les plus pertinents), et
    // l'accueil local n'en fait jamais partie.
    expect(historique.at(-1)?.text).toBe('réponse 11');
    expect(historique.some((tour) => tour.text.startsWith('Dalal'))).toBe(false);
  });

  it("marque ses appels comme accessoires (l'échec ne quitte pas la page)", () => {
    store.ouvrir();
    store.envoyer('bonjour');

    const requete = http.expectOne(url);

    // ⚠️ Sans ce marqueur, le traitement global des erreurs routerait vers la
    // page d'erreur au moindre 5xx — or l'interrupteur de l'assistant répond
    // 503 : le couper éjecterait de sa page quiconque lui écrit.
    expect(requete.request.context.get(SKIP_ERROR_REDIRECT)).toBe(true);

    requete.flush(reponse('Dalal ak diam'));
  });

  it('fait disparaître la bulle quand le serveur répond 503', () => {
    store.ouvrir();
    store.envoyer('bonjour');

    http.expectOne(url).flush(null, { status: 503, statusText: 'Service Unavailable' });

    expect(store.indisponible()).toBe(true);
    expect(store.bulles().at(-1)?.failed).toBe(true);
    // Et l'assistant coupé ne se laisse plus interroger.
    store.envoyer('encore');
    http.expectNone(url);
  });

  it('distingue le quota dépassé (429) d’un incident réseau', () => {
    store.ouvrir();
    store.envoyer('bonjour');
    http.expectOne(url).flush(null, { status: 429, statusText: 'Too Many Requests' });

    expect(store.indisponible()).toBe(false); // la bulle reste : ce n'est que passager
    expect(store.bulles().at(-1)?.text).toContain('Patientez');
  });

  it('ne suit que les chemins internes', () => {
    const navigation = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    store.executer({ kind: 'link', label: 'Piège', payload: { url: 'https://evil.test' } });
    store.executer({ kind: 'link', label: 'Piège', payload: { url: '//evil.test' } });
    expect(navigation).not.toHaveBeenCalled();

    store.executer({ kind: 'link', label: 'Immobilier', payload: { url: '/immobilier' } });
    expect(navigation).toHaveBeenCalledWith('/immobilier');
  });

  it("renvoie au contact quand on n'a pas de compte pour ouvrir un fil", () => {
    const navigation = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);

    store.executer({
      kind: 'support',
      label: 'Écrire à un conseiller',
      payload: { subject: 'Litige', body: 'bonjour' },
    });

    // Sans session, on n'appelle pas `POST /messages/support` pour récolter un 401.
    http.expectNone(`${environment.apiUrl}/messages/support`);
    expect(navigation).toHaveBeenCalledWith('/contact');
  });

  it("ouvre le fil de support et conduit à la messagerie de SON espace", () => {
    const navigation = vi.spyOn(router, 'navigateByUrl').mockResolvedValue(true);
    session.set(true);
    compte.set({ roles: ['proprietaire'] });

    store.executer({
      kind: 'support',
      label: 'Écrire à un conseiller',
      payload: { subject: 'Mandat', body: 'une question sur mon mandat' },
    });

    http
      .expectOne(`${environment.apiUrl}/messages/support`)
      .flush({ data: { conversation: { id: 42 } } });

    // ⚠️ Le site a cinq espaces connectés : un propriétaire ne doit pas être
    // envoyé sur `/mon-espace`, qui est gardé par le rôle `client`.
    expect(navigation).toHaveBeenCalledWith('/espace-proprietaire/messages/42');
    expect(store.estOuvert()).toBe(false);
  });

  it('oublie la conversation quand la session change', () => {
    store.ouvrir();
    store.envoyer('bonjour');
    http.expectOne(url).flush(reponse('Dalal ak diam'));
    expect(store.aConverse()).toBe(true);

    // Connexion : la trousse à outils change côté serveur, les boutons déjà
    // affichés ne correspondent plus à qui l'on est.
    session.set(true);
    TestBed.tick();

    expect(store.bulles()).toHaveLength(0);
  });
});
