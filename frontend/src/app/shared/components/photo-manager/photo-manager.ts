import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  input,
  linkedSignal,
  signal,
} from '@angular/core';
import { Observable, from, of } from 'rxjs';
import { concatMap, toArray } from 'rxjs/operators';

import { MediaService, MediableType } from '../../../core/api/media.service';
import { PropertyPhoto } from '../../../models/property.model';

/**
 * Photo choisie mais **pas encore envoyée**. En création, la ressource n'existe
 * pas encore (pas d'id à qui rattacher le média) : on retient les fichiers et on
 * les téléverse une fois la ressource créée. `preview` est une URL objet locale.
 */
interface PendingPhoto {
  file: File;
  preview: string;
}

/**
 * Bloc « photos » d'un formulaire de dépôt d'annonce — composant partagé (F8.18).
 *
 * POURQUOI IL EXISTE
 * ------------------
 * Cette mécanique n'existait que dans le formulaire de bien du propriétaire, et
 * c'était **le seul appelant de `POST /media/upload` dans tout le frontend**.
 * Conséquence : un loueur de véhicule ou un organisateur de circuit ne pouvait
 * illustrer son annonce par aucun moyen, et trois univers du catalogue sur cinq
 * s'affichaient invariablement en vignette dégradée.
 *
 * Plutôt que de recopier cent lignes dans chaque formulaire — où elles auraient
 * divergé au premier correctif — la gestion vit ici et les trois écrans la
 * montent. Le serveur, lui, n'a jamais eu besoin de rien : `media/upload` est
 * polymorphe depuis B12.1.
 *
 * COMMENT L'UTILISER
 * ------------------
 * ```html
 * <app-photo-manager #photos type="vehicle" [resourceId]="editId()" [initial]="loaded.photos" />
 * ```
 * puis, une fois la ressource créée/enregistrée :
 * ```ts
 * this.photos().uploadPending(id).subscribe(...)
 * ```
 *
 * ⚠️ **Le dépôt différé n'est pas un détail** : en création, l'annonce n'a pas
 * encore d'id. Envoyer les photos à ce moment-là exigerait un identifiant
 * temporaire côté serveur, donc des médias orphelins à nettoyer si le partenaire
 * abandonne le formulaire. On garde les fichiers en mémoire et on ne téléverse
 * qu'une fois l'annonce réellement créée.
 */
@Component({
  selector: 'app-photo-manager',
  imports: [],
  templateUrl: './photo-manager.html',
  styleUrl: './photo-manager.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PhotoManagerComponent {
  private readonly mediaApi = inject(MediaService);

  /** Type de ressource illustrée — miroir de l'allow-list serveur `Media::TYPES`. */
  readonly type = input.required<MediableType>();

  /**
   * Id de la ressource, ou `null` en création (les photos attendent alors la
   * création avant d'être envoyées).
   */
  readonly resourceId = input<number | null>(null);

  /** Nom de la chose illustrée, pour les textes ("Photos du véhicule"). */
  readonly subject = input<string>('annonce');

  /** Conseil de prise de vue propre à l'univers (affiché sous le titre). */
  readonly hint = input<string | null>(null);

  /**
   * Photos déjà en ligne (mode édition), telles que le parent les a reçues du
   * serveur.
   *
   * ⚠️ **Une entrée, et surtout pas une méthode appelée par le parent** : ce
   * bloc vit dans une branche conditionnelle des formulaires (compte non
   * vérifié, annonce en cours de chargement). Un parent qui pousserait ses
   * photos par `viewChild` viserait un composant pas encore monté — et les
   * perdrait, ou lèverait une erreur avec `viewChild.required`.
   */
  readonly initial = input<PropertyPhoto[] | null | undefined>(null);

  /**
   * Photos en ligne, **dérivées de l'entrée mais modifiables sur place** : le
   * partenaire retire une photo ou change de couverture sans attendre un
   * rechargement, et une nouvelle réponse du serveur reprend la main.
   */
  readonly photos = linkedSignal<PropertyPhoto[]>(() => this.initial() ?? []);
  /** Photos choisies mais pas encore envoyées. */
  readonly pendingPhotos = signal<PendingPhoto[]>([]);
  /** Erreur propre au bloc photos (fichier refusé, envoi échoué…). */
  readonly photoError = signal<string | null>(null);
  /** Photo en cours de suppression / promotion (désactive ses boutons). */
  readonly photoBusyId = signal<number | null>(null);

  /** Contraintes du serveur, exposées au gabarit. */
  protected readonly accept = MediaService.ACCEPT;

  /** Aucune photo, ni en ligne ni en attente ? */
  readonly hasNoPhoto = computed(
    () => this.photos().length === 0 && this.pendingPhotos().length === 0,
  );

  /**
   * Fichiers choisis : on filtre en amont ce que le serveur refuserait (type,
   * taille), pour donner un retour immédiat plutôt qu'un 422 après l'envoi.
   */
  onPhotosSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const files = Array.from(input.files ?? []);
    this.photoError.set(null);

    const accepted: PendingPhoto[] = [];
    for (const file of files) {
      if (!MediaService.ACCEPT.includes(file.type)) {
        this.photoError.set(`« ${file.name} » n'est pas une image JPEG, PNG ou WebP.`);
        continue;
      }
      if (file.size > MediaService.MAX_BYTES) {
        this.photoError.set(`« ${file.name} » dépasse 5 Mo.`);
        continue;
      }
      accepted.push({ file, preview: URL.createObjectURL(file) });
    }

    this.pendingPhotos.update((list) => [...list, ...accepted]);
    // Permet de re-sélectionner le même fichier après un retrait.
    input.value = '';
  }

  /** Retire une photo pas encore envoyée (et libère son URL objet). */
  removePending(index: number): void {
    this.pendingPhotos.update((list) => {
      const target = list[index];
      if (target) {
        URL.revokeObjectURL(target.preview);
      }
      return list.filter((_, i) => i !== index);
    });
  }

  /** Supprime une photo déjà en ligne (édition). */
  removePhoto(photo: PropertyPhoto): void {
    if (this.photoBusyId()) {
      return;
    }
    this.photoBusyId.set(photo.id);
    this.photoError.set(null);

    this.mediaApi.remove(photo.id).subscribe({
      next: () => {
        this.photoBusyId.set(null);
        this.photos.update((list) => list.filter((p) => p.id !== photo.id));
      },
      error: () => {
        this.photoBusyId.set(null);
        this.photoError.set("Cette photo n'a pas pu être supprimée. Réessayez.");
      },
    });
  }

  /** Désigne une photo déjà en ligne comme image de couverture. */
  makePrimary(photo: PropertyPhoto): void {
    if (this.photoBusyId() || photo.is_primary) {
      return;
    }
    this.photoBusyId.set(photo.id);
    this.photoError.set(null);

    this.mediaApi.setPrimary(photo.id).subscribe({
      next: () => {
        this.photoBusyId.set(null);
        // Reflète le choix localement : une seule couverture, remise en tête.
        this.photos.update((list) => {
          const next = list.map((p) => ({ ...p, is_primary: p.id === photo.id }));
          return [...next].sort((a, b) => Number(b.is_primary) - Number(a.is_primary));
        });
      },
      error: () => {
        this.photoBusyId.set(null);
        this.photoError.set("La photo de couverture n'a pas pu être changée. Réessayez.");
      },
    });
  }

  /**
   * Envoie les photos en attente, une fois la ressource connue (création comme
   * édition). La première photo d'une annonce qui n'en a aucune devient la
   * couverture — celle des cartes du catalogue.
   *
   * ⚠️ Séquentiel (`concatMap`) et non parallèle : l'ordre choisi par le
   * partenaire est l'ordre d'affichage de sa galerie, et des envois concurrents
   * le rendraient aléatoire.
   */
  uploadPending(resourceId: number): Observable<unknown> {
    const pending = this.pendingPhotos();
    if (pending.length === 0) {
      return of(null);
    }

    const alreadyOnline = this.photos().length;

    return from(pending.map((p, i) => ({ ...p, index: i }))).pipe(
      concatMap((item) =>
        this.mediaApi.upload(this.type(), resourceId, item.file, {
          isPrimary: alreadyOnline === 0 && item.index === 0,
          position: alreadyOnline + item.index,
        }),
      ),
      toArray(),
    );
  }
}
