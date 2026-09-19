import { ChangeDetectionStrategy, Component, computed, inject, input, output, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { OfferService } from '../../../../core/api/offer.service';
import { PropertyManagementService } from '../../../../core/api/property-management.service';
import { AuthService } from '../../../../core/auth/auth.service';

/** Type d'offre gérable par son déposant. */
export type OwnOfferKind = 'property' | 'vehicle' | 'experience';

/**
 * Boutons « Modifier » et « Supprimer » d'une offre (F21.1) — affichés **seulement
 * au déposant**.
 *
 * Le super administrateur gère ce qu'il a lui-même ajouté (biens, nuitées,
 * véhicules, circuits), jamais l'offre d'un prestataire ou d'un propriétaire
 * externe : sans correspondance entre `ownerId` et le compte connecté, le
 * composant ne rend rien. Le serveur applique la même règle (403), l'affichage
 * ne fait que ne pas proposer un geste qui serait refusé.
 */
@Component({
  selector: 'app-own-offer-actions',
  imports: [RouterLink],
  templateUrl: './own-offer-actions.html',
  styleUrl: './own-offer-actions.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class OwnOfferActionsComponent {
  private readonly auth = inject(AuthService);
  private readonly offers = inject(OfferService);
  private readonly properties = inject(PropertyManagementService);
  private readonly router = inject(Router);

  readonly kind = input.required<OwnOfferKind>();
  readonly id = input.required<number>();
  /** Déposant de l'offre (`owner_id` du bien, `provider_id` du véhicule ou du circuit). */
  readonly ownerId = input<number | null | undefined>(null);
  /** Route du formulaire de modification ; absent = pas de bouton « Modifier ». */
  readonly editLink = input<unknown[] | null>(null);
  /** Où aller une fois l'offre supprimée ; absent = on reste, et l'hôte réagit à `removed`. */
  readonly backLink = input<string | null>(null);

  /** Émis quand l'offre a réellement quitté la liste (supprimée ou retirée). */
  readonly removed = output<void>();

  protected readonly mine = computed(() => {
    const owner = this.ownerId();
    return owner != null && owner === this.auth.user()?.id;
  });

  protected readonly confirming = signal(false);
  protected readonly busy = signal(false);
  protected readonly message = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);

  protected askDelete(): void {
    this.error.set(null);
    this.confirming.set(true);
  }

  protected cancel(): void {
    this.confirming.set(false);
  }

  protected confirmDelete(): void {
    if (this.busy()) return;
    this.busy.set(true);
    this.error.set(null);

    const id = this.id();
    const request$: Observable<{ data: { deleted?: boolean; reason?: string | null; message?: string } }> =
      this.kind() === 'property'
        ? this.properties.delete(id)
        : this.kind() === 'vehicle'
          ? this.offers.deleteVehicle(id)
          : this.offers.deleteExperience(id);

    request$.subscribe({
      next: (env) => {
        this.busy.set(false);
        this.confirming.set(false);
        // « Retiré mais conservé » (offre déjà réservée) : le serveur dit pourquoi.
        this.message.set(env.data.reason ?? env.data.message ?? null);
        this.removed.emit();
        const back = this.backLink();
        if (back) void this.router.navigateByUrl(back);
      },
      error: (err: { status?: number; error?: { message?: string } }) => {
        this.busy.set(false);
        this.error.set(
          err?.status === 403
            ? 'Vous ne pouvez supprimer que ce que vous avez vous-même ajouté.'
            : (err?.error?.message ?? "La suppression n'a pas pu être effectuée. Réessayez."),
        );
      },
    });
  }
}
