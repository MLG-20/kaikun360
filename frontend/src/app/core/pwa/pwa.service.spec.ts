import { PLATFORM_ID } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { SwUpdate } from '@angular/service-worker';
import { Subject } from 'rxjs';

import { PwaService } from './pwa.service';

/**
 * La vie de l'application installée (F9.0) tient en quatre comportements, et
 * chacun protège d'un travers précis :
 *
 * - elle **ne touche rien au rendu serveur** (la leçon de F8.7 : une
 *   `ReferenceError` sur `window` y est silencieuse) ;
 * - elle **ne propose l'installation que si le navigateur l'a offerte**, et
 *   **empêche la bannière maison de Chrome** de s'afficher à sa place ;
 * - elle **ne rejoue jamais une invitation consommée** — l'événement ne se
 *   rejoue pas, garder le bouton ne ferait qu'un bouton mort ;
 * - elle **signale les nouvelles versions**, faute de quoi un onglet jamais
 *   fermé reste des semaines sur une version périmée.
 */
describe('PwaService', () => {
  /** Un `SwUpdate` de laboratoire, dont on pilote le flux de versions. */
  const faireSwUpdate = (isEnabled: boolean) => {
    const versionUpdates = new Subject<{ type: string }>();
    return {
      double: {
        isEnabled,
        versionUpdates,
        activateUpdate: () => Promise.resolve(true),
      } as unknown as SwUpdate,
      versionUpdates,
    };
  };

  const creer = (options: { navigateur?: boolean; swActif?: boolean } = {}) => {
    const { navigateur = true, swActif = true } = options;
    const sw = faireSwUpdate(swActif);

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [
        { provide: PLATFORM_ID, useValue: navigateur ? 'browser' : 'server' },
        { provide: SwUpdate, useValue: sw.double },
      ],
    });

    return { service: TestBed.inject(PwaService), ...sw };
  };

  afterEach(() => {
    try {
      localStorage.clear();
    } catch {
      /* navigation privée : sans importance ici */
    }
  });

  it('ne propose rien tant que le navigateur n\'a rien offert', () => {
    const { service } = creer();

    // ⚠️ Ces deux signaux valent `false` au rendu serveur ET au premier rendu
    // client : c'est ce qui rend le DOM identique de part et d'autre et permet
    // à l'hydratation de tenir.
    expect(service.installable()).toBe(false);
    expect(service.miseAJourPrete()).toBe(false);
  });

  it('retient l\'invitation du navigateur ET empêche sa bannière maison', () => {
    const { service } = creer();

    const evenement = new Event('beforeinstallprompt');
    const empeche = vi.spyOn(evenement, 'preventDefault');
    Object.assign(evenement, { prompt: () => Promise.resolve() });

    window.dispatchEvent(evenement);

    expect(service.installable()).toBe(true);
    // Sans ce `preventDefault`, Chrome affiche SA propre bannière, quand il veut.
    expect(empeche).toHaveBeenCalled();
  });

  it('ne rejoue pas une invitation déjà consommée', async () => {
    const { service } = creer();

    let appels = 0;
    const evenement = new Event('beforeinstallprompt');
    Object.assign(evenement, {
      prompt: () => {
        appels += 1;
        return Promise.resolve();
      },
    });
    window.dispatchEvent(evenement);

    await service.installer();
    await service.installer(); // second clic : sans effet

    expect(appels).toBe(1);
    expect(service.installable()).toBe(false);
  });

  it('oublie l\'invitation quand l\'application a été installée autrement', () => {
    const { service } = creer();

    const evenement = new Event('beforeinstallprompt');
    Object.assign(evenement, { prompt: () => Promise.resolve() });
    window.dispatchEvent(evenement);
    expect(service.installable()).toBe(true);

    // Installée depuis le menu du navigateur : l'invitation n'a plus d'objet.
    window.dispatchEvent(new Event('appinstalled'));

    expect(service.installable()).toBe(false);
  });

  it('signale une nouvelle version prête', () => {
    const { service, versionUpdates } = creer();

    versionUpdates.next({ type: 'VERSION_DETECTED' });
    expect(service.miseAJourPrete()).toBe(false);

    versionUpdates.next({ type: 'VERSION_READY' });
    expect(service.miseAJourPrete()).toBe(true);
  });

  it('reste muet au rendu serveur, sans toucher à window', () => {
    const { service, versionUpdates } = creer({ navigateur: false });

    // Même en poussant un événement, rien ne bouge : le service s'est arrêté
    // avant de s'abonner. C'est ce qui garantit qu'il ne lit jamais `window`
    // pendant le rendu serveur.
    versionUpdates.next({ type: 'VERSION_READY' });

    expect(service.miseAJourPrete()).toBe(false);
    expect(service.installable()).toBe(false);
  });

  it('ne s\'abonne pas quand le service worker est désactivé (développement)', () => {
    const { service, versionUpdates } = creer({ swActif: false });

    versionUpdates.next({ type: 'VERSION_READY' });

    expect(service.miseAJourPrete()).toBe(false);
  });
});
