import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import {
  PropertyDocumentType,
  PropertyManagementService,
} from '../../../core/api/property-management.service';
import { Property, PropertyDocument } from '../../../models/property.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { propertyLocality } from '../properties/property-status';

/** État de chargement de la fiche (le bien). */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

/** Option du sélecteur de type de document (valeur serveur + libellé). */
interface TypeOption {
  value: PropertyDocumentType;
  label: string;
}

/** Libellés lisibles des types de document (miroir de l'allow-list serveur). */
const TYPE_LABELS: Record<string, string> = {
  titre_foncier: 'Titre foncier',
  bail: 'Bail',
  plan: 'Plan',
  autre: 'Autre',
};

/** Libellés lisibles des statuts de validation d'un document. */
const STATUS_LABELS: Record<string, string> = {
  pending: 'En attente de vérification',
  approved: 'Vérifié',
  validated: 'Vérifié',
  rejected: 'Rejeté',
};

@Component({
  selector: 'app-owner-property-documents-page',
  imports: [DatePipe, BackLinkComponent],
  templateUrl: './owner-property-documents-page.html',
  styleUrl: './owner-property-documents-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Gestion des documents d'UN bien (F4.5), montée sous
 * `/espace-proprietaire/documents/:id`. Atteinte en cliquant une carte depuis
 * l'écran « Documents ».
 *
 * Charge le bien (`GET /properties/mine/{id}`, réservé au propriétaire — 404
 * sinon) pour l'intitulé, puis ses pièces (`GET /properties/{id}/documents`).
 * Le propriétaire peut **déposer** une pièce (type + fichier PDF/JPG/PNG ≤ 5 Mo,
 * `POST`), la **télécharger** (lien signé temporaire) et la **retirer**
 * (`DELETE`, après confirmation). Le statut de validation est posé par un agent
 * Kaikun (lecture seule ici).
 */
export class OwnerPropertyDocumentsPageComponent {
  private readonly properties = inject(PropertyManagementService);
  private readonly route = inject(ActivatedRoute);

  // — État de la fiche (le bien) —
  protected readonly state = signal<LoadState>('loading');
  protected readonly property = signal<Property | null>(null);
  private readonly propertyId = signal<number | string | null>(null);

  // — État de la liste des documents —
  protected readonly documents = signal<PropertyDocument[]>([]);
  protected readonly documentsLoading = signal(false);
  protected readonly documentsError = signal(false);

  // — État du dépôt —
  /** Type sélectionné pour le prochain dépôt. */
  protected readonly selectedType = signal<PropertyDocumentType>('titre_foncier');
  protected readonly uploading = signal(false);
  /** Message d'erreur du dépôt (fichier invalide, refus serveur…). */
  protected readonly uploadError = signal<string | null>(null);
  /** Id du document en cours de suppression (désactive son bouton). */
  protected readonly deletingId = signal<number | null>(null);

  /** Options du sélecteur de type (miroir de l'allow-list serveur). */
  protected readonly typeOptions: readonly TypeOption[] = [
    { value: 'titre_foncier', label: 'Titre foncier' },
    { value: 'bail', label: 'Bail' },
    { value: 'plan', label: 'Plan' },
    { value: 'autre', label: 'Autre' },
  ];

  // Contraintes du sélecteur de fichier (exposées au template).
  protected readonly accept = PropertyManagementService.DOC_ACCEPT;
  protected readonly maxBytes = PropertyManagementService.DOC_MAX_BYTES;

  // Helpers de présentation.
  protected readonly localityOf = propertyLocality;
  protected readonly typeLabel = (type: string): string => TYPE_LABELS[type] ?? type;
  protected readonly statusLabel = (status: string | null): string =>
    status ? (STATUS_LABELS[status] ?? status) : '';
  protected readonly statusTone = (status: string | null): string => {
    if (status === 'approved' || status === 'validated') {
      return 'active';
    }
    if (status === 'rejected') {
      return 'cancelled';
    }
    return 'pending';
  };

  /**
   * Charge le bien dès que l'identifiant de route est connu, puis ses documents.
   * `switchMap` annule une requête précédente si l'on change de bien.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.property.set(null);
        this.documents.set([]);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        this.propertyId.set(id);
        return this.properties.get(id).pipe(
          tap((env) => {
            this.property.set(env.data);
            this.state.set('ready');
            this.loadDocuments();
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );

  /** (Re)charge la liste des documents du bien courant. */
  protected loadDocuments(): void {
    const id = this.propertyId();
    if (id == null) {
      return;
    }
    this.documentsLoading.set(true);
    this.documentsError.set(false);
    this.properties.documents(id).subscribe({
      next: (env) => {
        this.documents.set(env.data);
        this.documentsLoading.set(false);
      },
      error: () => {
        this.documentsLoading.set(false);
        this.documentsError.set(true);
      },
    });
  }

  /** Change le type sélectionné pour le prochain dépôt. */
  protected onTypeChange(value: string): void {
    this.selectedType.set(value as PropertyDocumentType);
  }

  /**
   * Dépôt d'un fichier choisi par l'utilisateur. On valide le TYPE MIME et la
   * TAILLE en amont (mêmes règles que le serveur) pour éviter un aller-retour
   * 422, puis on téléverse. La liste est rechargée en cas de succès.
   */
  protected onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) {
      return;
    }

    this.uploadError.set(null);

    // Contrôle en amont (le serveur revérifie de toute façon).
    if (!this.accept.split(',').includes(file.type)) {
      this.uploadError.set('Le fichier doit être au format PDF, JPG ou PNG.');
      input.value = '';
      return;
    }
    if (file.size > this.maxBytes) {
      this.uploadError.set('Le fichier ne doit pas dépasser 5 Mo.');
      input.value = '';
      return;
    }

    const id = this.propertyId();
    if (id == null) {
      return;
    }

    this.uploading.set(true);
    this.properties.uploadDocument(id, this.selectedType(), file).subscribe({
      next: () => {
        this.uploading.set(false);
        input.value = ''; // Réarme le sélecteur pour un éventuel dépôt suivant.
        this.loadDocuments();
      },
      error: (err: { status?: number }) => {
        this.uploading.set(false);
        input.value = '';
        this.uploadError.set(
          err?.status === 403
            ? "Votre compte doit être vérifié pour déposer un document."
            : "Le dépôt a échoué. Vérifiez le fichier et réessayez.",
        );
      },
    });
  }

  /** Retire un document, après confirmation (action irréversible). */
  protected remove(doc: PropertyDocument): void {
    const id = this.propertyId();
    if (id == null || this.deletingId() !== null) {
      return;
    }
    const label = doc.original_name ?? this.typeLabel(doc.type);
    if (typeof window !== 'undefined' && !window.confirm(`Supprimer « ${label} » ? Cette action est définitive.`)) {
      return;
    }

    this.deletingId.set(doc.id);
    this.properties.removeDocument(id, doc.id).subscribe({
      next: () => {
        this.deletingId.set(null);
        // Retrait local immédiat (évite un rechargement complet).
        this.documents.update((list) => list.filter((d) => d.id !== doc.id));
      },
      error: () => {
        this.deletingId.set(null);
        this.documentsError.set(true);
      },
    });
  }

  /** Poids d'un fichier en format lisible (Ko / Mo). */
  protected fileSize(bytes: number | null): string {
    if (!bytes) {
      return '';
    }
    if (bytes >= 1024 * 1024) {
      return `${(bytes / (1024 * 1024)).toFixed(1)} Mo`;
    }
    return `${Math.max(1, Math.round(bytes / 1024))} Ko`;
  }
}
