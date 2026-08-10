import { DatePipe, SlicePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
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
  imports: [DatePipe, SlicePipe, RouterLink, BackLinkComponent],
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
  private readonly router = inject(Router);

  // — État de l'écran —
  protected readonly state = signal<LoadState>('loading');
  protected readonly property = signal<Property | null>(null);

  // Helpers de présentation (partagés avec la liste).
  protected readonly localityOf = propertyLocality;

  /** Présentation du statut courant (libellé, tonalité, explication). */
  protected readonly status = computed(() => propertyStatus(this.property()?.status ?? null));

  /** Prix formaté en FCFA (ou null si non renseigné). */
  protected readonly priceLabel = computed(() => formatFcfa(this.property()?.price_xof));

  /** Config nuitées du bien (présente si le bien est loué en courte durée). */
  protected readonly stay = computed(() => this.property()?.stay ?? null);

  // === Corbeille (F11.4) ====================================================
  //
  // ⚠️ Le geste vit sur la FICHE et non dans la liste, délibérément : ranger un
  // bien est une décision, pas un tri en rafale. Sur la liste, un bouton par
  // ligne invite au clic distrait.

  /** Confirmation demandée ? (le bouton ne range jamais du premier clic) */
  protected readonly askingTrash = signal(false);

  /** Requête en cours. */
  protected readonly trashing = signal(false);

  /**
   * Motif de refus renvoyé par le serveur, affiché TEL QUEL.
   *
   * ⚠️ Un bien engagé — réservation en cours, mandat de gestion actif — est
   * refusé en 422 avec une phrase qui dit *quoi faire pour débloquer*. La
   * remplacer par « une erreur est survenue » laisserait le propriétaire devant
   * un mur, sans comprendre ce qui le retient.
   */
  protected readonly trashError = signal<string | null>(null);

  protected askTrash(oui: boolean): void {
    this.askingTrash.set(oui);
    this.trashError.set(null);
  }

  protected confirmTrash(): void {
    const bien = this.property();

    if (!bien) {
      return;
    }

    this.trashing.set(true);
    this.trashError.set(null);

    this.properties.trash(bien.id).subscribe({
      next: () => {
        // Retour à la liste : le bien n'y est plus, et rester sur la fiche d'un
        // bien rangé n'aurait aucun sens.
        void this.router.navigate(['/espace-proprietaire/biens'], {
          queryParams: { corbeille: bien.id },
        });
      },
      error: (err: unknown) => {
        this.trashing.set(false);
        const message = (err as { error?: { message?: string } })?.error?.message;
        this.trashError.set(
          message ?? 'La mise à la corbeille a échoué. Réessayez dans un instant.',
        );
      },
    });
  }

  /** Prix par nuit formaté (ou null). */
  protected readonly nightlyLabel = computed(() =>
    formatFcfa(this.stay()?.price_per_night_xof ?? null),
  );

  /**
   * Mode de location déduit du bien : mensuelle (loyer seul), nuitées (config
   * Stay seule) ou mixte (les deux). Un bien sans loyer ni nuitées reste
   * « mensuelle » (loyer à préciser).
   */
  protected readonly rentalMode = computed(() => {
    const p = this.property();
    if (!p) {
      return null;
    }
    const hasStay = !!p.stay;
    const hasMonthly = p.price_xof != null;
    if (hasStay && hasMonthly) {
      return 'Mensuelle + nuitées';
    }
    return hasStay ? 'Nuitées' : 'Mensuelle';
  });

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
