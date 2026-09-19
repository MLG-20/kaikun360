import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { FormArray, FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { of } from 'rxjs';
import { switchMap } from 'rxjs/operators';

import { NewExperiencePayload, OfferService } from '../../../core/api/offer.service';
import { Experience } from '../../../models/experience.model';
import { PropertyPhoto } from '../../../models/property.model';
import { extractGoogleMapsEmbedUrl } from '../../../shared/format/google-maps';
import { ValidationErrorBody } from '../../../core/api/api-response.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';
import { PhotoManagerComponent } from '../../../shared/components/photo-manager/photo-manager';

/**
 * Formulaire de dépôt d'une **expérience touristique** (F5.6), monté sous
 * `/espace-prestataire/offres/experience/nouvelle`.
 *
 * Le back-office réutilise CE MÊME composant (F21) sous `/back-office/...` :
 * un super_admin peut ainsi déposer un circuit exactement comme un
 * prestataire, sans un second formulaire qui divergerait avec le temps. Le
 * seul comportement piloté par la route est la destination du retour après
 * enregistrement (`data: { returnTo }`, cf. `app.routes.ts`).
 *
 * Miroir de `StoreExperienceRequest` / `UpdateExperienceRequest` (F21) :
 * - le **programme** (`itinerary`) est un jour par jour libre, le numéro du
 *   jour étant simplement la position dans la liste ;
 * - les **dates de départ** (`departures`) portent chacune leurs propres
 *   places — au moins une est exigée, un circuit sans date n'étant jamais
 *   réservable ;
 * - « inclus »/« non inclus » sont du texte libre, plus proches de ce qu'un
 *   circuit promet réellement que les 4 cases à cocher qu'ils remplacent.
 *
 * ⚠️ **L'édition n'existait pas** (F8.19) : le backend n'exposait aucun `PATCH`,
 * un circuit déposé était donc définitif — et, les photos n'étant déposables
 * qu'à la création (F8.18), un circuit créé sans photo ne pouvait plus jamais
 * être illustré. Le même composant sert désormais les deux modes, comme celui
 * des véhicules : la présence d'un `:id` bascule en édition.
 */
@Component({
  selector: 'app-provider-experience-form-page',
  imports: [ReactiveFormsModule, RouterLink, BackLinkComponent, PhotoManagerComponent],
  templateUrl: './provider-experience-form-page.html',
  styleUrl: './provider-experience-form-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderExperienceFormPageComponent {
  private readonly offers = inject(OfferService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  /** Id du circuit édité (null en création). */
  protected readonly editId = signal<number | null>(null);
  protected readonly isEdit = computed(() => this.editId() !== null);
  protected readonly state = signal<'loading' | 'form' | 'not-found' | 'error'>('form');

  /**
   * Après enregistrement, où revenir ? Le prestataire retrouve ses offres ;
   * le back-office (F21) retrouve l'onglet Circuits — piloté par la route
   * plutôt que codé en dur, pour que ce composant reste réutilisable tel quel.
   */
  protected readonly returnTo =
    (this.route.snapshot.data['returnTo'] as string | undefined) ?? '/espace-prestataire/offres';

  /** Vrai quand le formulaire est ouvert depuis le back-office (dépôt par l'équipe). */
  protected readonly isBackoffice = computed(() => this.returnTo.startsWith('/back-office'));

  /** Photos déjà en ligne du circuit (mode édition). */
  protected readonly existingPhotos = signal<PropertyPhoto[]>([]);

  /**
   * Bloc photos (F8.18). Un circuit est ce qui se vend le plus par l'image, et
   * c'était l'univers le plus démuni : ni dépôt, ni photo sur la carte, ni
   * galerie sur la fiche.
   */
  private readonly photoManager = viewChild(PhotoManagerComponent);

  protected readonly form = this.fb.nonNullable.group({
    title: ['', [Validators.required, Validators.maxLength(255)]],
    destination: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    duration_days: [1, [Validators.required, Validators.min(1)]],
    price_xof: [0, [Validators.required, Validators.min(0)]],
    included: [''],
    excluded: [''],
    maps_link: [''],
    itinerary: this.fb.array<ReturnType<typeof this.newItineraryDay>>([]),
    departures: this.fb.array<ReturnType<typeof this.newDeparture>>([], Validators.minLength(1)),
  });

  /** Accès typé au programme jour par jour. */
  protected get itinerary(): FormArray<ReturnType<typeof this.newItineraryDay>> {
    return this.form.controls.itinerary;
  }

  /** Accès typé aux dates de départ. */
  protected get departures(): FormArray<ReturnType<typeof this.newDeparture>> {
    return this.form.controls.departures;
  }

  constructor() {
    // Un circuit qui démarre sans aucune date pousserait un prestataire à
    // publier une offre non réservable sans même s'en apercevoir : on en
    // propose une d'emblée, comme la première ligne d'un formulaire répétable.
    this.departures.push(this.newDeparture());

    const idParam = this.route.snapshot.paramMap.get('id');
    if (!idParam) {
      return;
    }

    this.state.set('loading');
    this.editId.set(Number(idParam));

    // ⚠️ `findMyExperience` et non le détail public : on édite justement des
    // circuits « en attente », « rejetés » ou « retirés », que le catalogue
    // public ne renvoie pas.
    this.offers.findMyExperience(Number(idParam)).subscribe({
      next: (experience) => {
        if (!experience) {
          this.state.set('not-found');
          return;
        }
        this.patch(experience);
        this.state.set('form');
      },
      error: () => this.state.set('error'),
    });
  }

  /** Fabrique une ligne de programme (titre + description du jour, facultatifs). */
  private newItineraryDay() {
    return this.fb.nonNullable.group({
      title: [''],
      description: [''],
    });
  }

  /** Ajoute un jour au programme. */
  addItineraryDay(): void {
    this.itinerary.push(this.newItineraryDay());
  }

  /** Retire le jour de programme à l'index donné. */
  removeItineraryDay(index: number): void {
    this.itinerary.removeAt(index);
  }

  /**
   * Fabrique une ligne de date de départ. `id` reste caché : présent en
   * édition, il dit au serveur qu'il s'agit d'une date EXISTANTE à corriger
   * plutôt qu'à recréer.
   */
  private newDeparture(id: number | null = null) {
    return this.fb.nonNullable.group({
      id: this.fb.control<number | null>(id),
      start_date: ['', [Validators.required]],
      seats_total: [1, [Validators.required, Validators.min(1)]],
    });
  }

  /** Ajoute une date de départ. */
  addDeparture(): void {
    this.departures.push(this.newDeparture());
  }

  /**
   * Retire la date de départ à l'index donné.
   *
   * ⚠️ Le serveur refuse de retirer une date qui porte déjà des réservations
   * (F21) : l'erreur revient à l'enregistrement, pas ici — on ne peut pas le
   * savoir sans interroger le serveur, et bloquer localement empêcherait de
   * corriger une erreur de saisie sur une date qui, elle, n'a rien.
   */
  removeDeparture(index: number): void {
    this.departures.removeAt(index);
  }

  /** Pré-remplit le formulaire à partir d'un circuit existant. */
  private patch(x: Experience): void {
    this.form.patchValue({
      title: x.title,
      destination: x.destination,
      description: x.description ?? '',
      duration_days: x.duration_days ?? 1,
      price_xof: x.price_xof,
      included: x.included ?? '',
      excluded: x.excluded ?? '',
      maps_link: x.maps_link ?? '',
    });

    this.itinerary.clear();
    for (const day of x.itinerary ?? []) {
      this.itinerary.push(
        this.fb.nonNullable.group({
          title: [day.title ?? ''],
          description: [day.description ?? ''],
        }),
      );
    }

    this.departures.clear();
    for (const departure of x.departures ?? []) {
      this.departures.push(this.newDeparture(departure.id));
      this.departures.at(-1).patchValue({
        start_date: departure.start_date,
        seats_total: departure.seats_total,
      });
    }
    if (this.departures.length === 0) {
      this.departures.push(this.newDeparture());
    }

    this.existingPhotos.set(x.photos ?? []);
  }

  /** Publie ou met à jour le circuit (POST / PATCH /experiences). */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: NewExperiencePayload = {
      title: raw.title,
      destination: raw.destination,
      description: raw.description || null,
      duration_days: raw.duration_days,
      price_xof: raw.price_xof,
      included: raw.included || null,
      excluded: raw.excluded || null,
      maps_link: raw.maps_link ? extractGoogleMapsEmbedUrl(raw.maps_link) : null,
      itinerary: raw.itinerary.map((day, index) => ({
        day: index + 1,
        title: day.title || null,
        description: day.description || null,
      })),
      departures: raw.departures.map((d) => ({
        id: d.id ?? undefined,
        start_date: d.start_date,
        seats_total: d.seats_total,
      })),
    };

    this.submitting.set(true);
    this.formError.set(null);

    const id = this.editId();
    const request$ = id
      ? this.offers.updateExperience(id, payload)
      : this.offers.createExperience(payload);

    request$
      .pipe(switchMap((env) => this.photoManager()?.uploadPending(env.data.experience.id) ?? of(null)))
      .subscribe({
      next: () => {
        this.submitting.set(false);
        this.router.navigateByUrl(this.returnTo);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(err));
      },
    });
  }

  /** Traduit une erreur serveur en message affichable. */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 403) {
      return 'Le dépôt est réservé aux prestataires dont le dossier est validé.';
    }
    const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
    return firstError ?? "Votre circuit n'a pas pu être enregistré. Réessayez.";
  }
}
