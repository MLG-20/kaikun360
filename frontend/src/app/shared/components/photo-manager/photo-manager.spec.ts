import { TestBed } from '@angular/core/testing';
import { of } from 'rxjs';

import { MediaService } from '../../../core/api/media.service';
import { PhotoManagerComponent } from './photo-manager';

/**
 * Le bloc photos partagé (F8.18) porte deux règles que l'écran ne rattrape pas,
 * et une troisième qui décide de l'apparence du catalogue entier :
 *
 *   1. il **refuse en amont** ce que le serveur refuserait (type, taille) —
 *      sinon le partenaire attend un envoi qui finira en 422 ;
 *   2. il envoie les photos **dans l'ordre choisi**, cet ordre étant celui de la
 *      galerie publique ;
 *   3. il désigne la **première photo comme couverture** — c'est elle, et elle
 *      seule, qui illustre la carte du catalogue. Sans cette règle, une annonce
 *      illustrée resterait affichée en vignette dégradée.
 */
describe('PhotoManagerComponent', () => {
  /** Enregistre les appels de téléversement pour pouvoir les inspecter. */
  interface AppelUpload {
    type: string;
    id: number | string;
    nom: string;
    isPrimary?: boolean;
    position?: number;
  }

  let appels: AppelUpload[];

  const monter = (initial: unknown[] = []): PhotoManagerComponent => {
    appels = [];

    const mediaStub = {
      upload: (
        type: string,
        id: number | string,
        file: File,
        options: { isPrimary?: boolean; position?: number } = {},
      ) => {
        appels.push({ type, id, nom: file.name, ...options });
        return of({ data: { media: {} } });
      },
      setPrimary: () => of({ data: { media: {} } }),
      remove: () => of({ data: { message: 'ok' } }),
    };

    TestBed.resetTestingModule();
    TestBed.configureTestingModule({
      providers: [{ provide: MediaService, useValue: mediaStub }],
    });

    const fixture = TestBed.createComponent(PhotoManagerComponent);
    fixture.componentRef.setInput('type', 'vehicle');
    fixture.componentRef.setInput('initial', initial);
    fixture.detectChanges();

    return fixture.componentInstance;
  };

  /** Un faux fichier de type et de taille choisis. */
  const fichier = (nom: string, type: string, octets = 1_000): File => {
    const file = new File(['x'], nom, { type });
    Object.defineProperty(file, 'size', { value: octets });
    return file;
  };

  /** Simule la sélection de fichiers dans le sélecteur natif. */
  const choisir = (composant: PhotoManagerComponent, ...files: File[]): void => {
    const input = document.createElement('input');
    Object.defineProperty(input, 'files', { value: files, configurable: true });
    composant.onPhotosSelected({ target: input } as unknown as Event);
  };

  it('refuse un fichier qui n\'est pas une image acceptée', () => {
    const composant = monter();

    choisir(composant, fichier('contrat.pdf', 'application/pdf'));

    expect(composant.pendingPhotos().length).toBe(0);
    expect(composant.photoError()).toContain('contrat.pdf');
  });

  it('refuse une image au-delà de 5 Mo', () => {
    const composant = monter();

    choisir(composant, fichier('enorme.jpg', 'image/jpeg', 6 * 1024 * 1024));

    expect(composant.pendingPhotos().length).toBe(0);
    expect(composant.photoError()).toContain('5 Mo');
  });

  it('garde les images valides et écarte les autres du même lot', () => {
    const composant = monter();

    choisir(
      composant,
      fichier('avant.jpg', 'image/jpeg'),
      fichier('note.txt', 'text/plain'),
      fichier('arriere.png', 'image/png'),
    );

    expect(composant.pendingPhotos().map((p) => p.file.name)).toEqual([
      'avant.jpg',
      'arriere.png',
    ]);
  });

  it('envoie dans l\'ordre choisi et fait de la première la couverture', () => {
    const composant = monter();

    choisir(
      composant,
      fichier('1-face.jpg', 'image/jpeg'),
      fichier('2-interieur.jpg', 'image/jpeg'),
    );

    composant.uploadPending(42).subscribe();

    expect(appels.map((a) => a.nom)).toEqual(['1-face.jpg', '2-interieur.jpg']);
    expect(appels[0].isPrimary).toBe(true);
    expect(appels[1].isPrimary).toBe(false);
    expect(appels.map((a) => a.position)).toEqual([0, 1]);
    // Le type d'annonce voyage jusqu'au serveur (allow-list `Media::TYPES`).
    expect(appels.every((a) => a.type === 'vehicle' && a.id === 42)).toBe(true);
  });

  it('ne promeut PAS en couverture quand l\'annonce a déjà des photos', () => {
    // Mode édition : une photo est déjà en ligne, et c'est elle la couverture.
    const composant = monter([
      {
        id: 7,
        reference: 'MED-EXISTANTE',
        type: 'image',
        type_label: 'Image',
        url: 'https://exemple.test/1.jpg',
        is_primary: true,
        position: 0,
        status: 'actif',
        original_name: '1.jpg',
        size_bytes: 1000,
      },
    ]);
    choisir(composant, fichier('ajout.jpg', 'image/jpeg'));

    composant.uploadPending(42).subscribe();

    expect(appels[0].isPrimary).toBe(false);
    // La position continue la galerie au lieu de la réécrire depuis zéro.
    expect(appels[0].position).toBe(1);
  });

  it('n\'appelle pas le serveur quand aucune photo n\'a été choisie', () => {
    const composant = monter();

    composant.uploadPending(42).subscribe();

    expect(appels.length).toBe(0);
  });
});
