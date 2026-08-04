import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { signal } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { environment } from '../../../environments/environment';
import { AuthService } from '../auth/auth.service';
import { UnreadStore } from './unread-store';

/** Session simulée : c'est le seul élément d'`AuthService` que le store regarde. */
const session = signal(false);

/**
 * L'état partagé des non-lus (F8.13). Ce qui est verrouillé ici, ce sont les
 * trois règles qui l'empêchent de nuire : il **ne parle pas** en visiteur (aucun
 * 401 sur les pages publiques), il **oublie tout** à la déconnexion (les
 * compteurs d'un compte ne survivent pas au suivant), et une **erreur réseau ne
 * remet pas un compteur à zéro** — ce qui ferait disparaître un non-lu réel.
 */
describe('UnreadStore', () => {
  let store: UnreadStore;
  let http: HttpTestingController;

  const notificationsUrl = `${environment.apiUrl}/users/me/notifications/unread-count`;
  const messagesUrl = `${environment.apiUrl}/messages/unread-count`;

  beforeEach(() => {
    session.set(false);
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        { provide: AuthService, useValue: { isAuthenticated: session } },
      ],
    });
    store = TestBed.inject(UnreadStore);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('ne demande rien tant que personne n’est connecté', () => {
    store.refresh();

    http.expectNone(notificationsUrl);
    http.expectNone(messagesUrl);
    expect(store.notifications()).toBe(0);
    expect(store.messages()).toBe(0);
  });

  it('publie les deux compteurs, et leur total', () => {
    session.set(true);
    store.refresh();

    http.expectOne(notificationsUrl).flush({ data: { unread_count: 4 } });
    http.expectOne(messagesUrl).flush({ data: { unread_count: 3 } });

    expect(store.notifications()).toBe(4);
    expect(store.messages()).toBe(3);
    expect(store.total()).toBe(7);
  });

  it('accepte le compteur poussé par un écran qui vient de marquer comme lu', () => {
    session.set(true);
    store.refresh();
    http.expectOne(notificationsUrl).flush({ data: { unread_count: 4 } });
    http.expectOne(messagesUrl).flush({ data: { unread_count: 0 } });

    // « Tout marquer comme lu » : la pastille s'éteint sans attendre la relève.
    store.setNotifications(0);

    expect(store.notifications()).toBe(0);
  });

  it('garde le dernier compteur connu quand le réseau échoue', () => {
    session.set(true);
    store.refresh();
    http.expectOne(notificationsUrl).flush({ data: { unread_count: 2 } });
    http.expectOne(messagesUrl).flush({ data: { unread_count: 0 } });

    store.refresh();
    http.expectOne(notificationsUrl).error(new ProgressEvent('offline'));
    http.expectOne(messagesUrl).error(new ProgressEvent('offline'));

    // Remettre à 0 sur une coupure ferait DISPARAÎTRE une notification réelle.
    expect(store.notifications()).toBe(2);
  });
});
