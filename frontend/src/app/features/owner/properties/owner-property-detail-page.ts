import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import { of } from 'rxjs';
import { catchError, map, switchMap, tap } from 'rxjs/operators';

import { PropertyManagementService } from '../../../core/api/property-management.service';
import { Property } from '../../../models/property.model';
import { formatFcfa } from '../../../shared/components/catalog/catalog.config';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { propertyLocality, propertyStatus, propertyVerified } from './property-status';

/** État de chargement de l'écran. */
type LoadState = 'loading' | 'ready' | 'notfound' | 'failed';

@Component({
  selector: 'app-owner-property-detail-page',
  imports: [DatePipe, BackLinkComponent],
  templateUrl: './owner-property-detail-page.html',
  styleUrl: './owner-property-detail-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
/**
 * Fiche d'un bien de l'espace propriétaire (F4.2), montée sous
 * `/espace-proprietaire/biens/:id`. Atteinte en cliquant une carte depuis « Mes
 * biens ».
 *
 * Charge le bien (`GET /properties/mine/{id}`, réservé au propriétaire, tous
 * statuts) et en présente le récapitulatif : **statut de validation** (avec une
 * explication), caractéristiques, localisation, prix et dates. Un bouton
 * « ← Mes biens » ramène toujours à la liste. Un bien qui n'appartient pas à
 * l'utilisateur (ou inexistant) renvoie 404 (état « introuvable »). L'édition
 * du bien viendra en F4.3.
 */
export class OwnerPropertyDetailPageComponent {
  private readonly properties = inject(PropertyManagementService);
  private readonly route = inject(ActivatedRoute);

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly property = signal<Property | null>(null);

  // Helpers de présentation (partagés avec la liste).
  protected readonly localityOf = propertyLocality;

  /** Présentation du statut courant (libellé, tonalité, explication). */
  protected readonly status = computed(() => propertyStatus(this.property()?.status ?? null));

  /** Prix formaté en FCFA (ou null si non renseigné). */
  protected readonly priceLabel = computed(() => formatFcfa(this.property()?.price_xof));

  /** Le bien porte-t-il un badge de confiance « Vérifié » ? */
  protected readonly verified = computed(() => propertyVerified(this.property()?.verification_level ?? null));

  /**
   * Déclenche le chargement dès que l'identifiant est connu. `switchMap` annule
   * une requête précédente si l'on change de bien.
   */
  private readonly loader = toSignal(
    this.route.paramMap.pipe(
      map((params) => params.get('id')),
      switchMap((id) => {
        this.state.set('loading');
        this.property.set(null);
        if (!id) {
          this.state.set('notfound');
          return of(null);
        }
        return this.properties.get(id).pipe(
          tap((env) => {
            this.property.set(env.data);
            this.state.set('ready');
          }),
          catchError((err: { status?: number }) => {
            this.state.set(err?.status === 404 ? 'notfound' : 'failed');
            return of(null);
          }),
        );
      }),
    ),
  );
}
