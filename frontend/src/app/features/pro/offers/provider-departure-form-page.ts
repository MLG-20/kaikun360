import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';

import { ValidationErrorBody } from '../../../core/api/api-response.model';
import {
  MOBILITY_SERVICE_TYPES,
  MobilityServiceTypeValue,
  NewMobilityServicePayload,
  OfferService,
} from '../../../core/api/offer.service';
import { MobilityService } from '../../../models/mobility-service.model';
import { Vehicle } from '../../../models/vehicle.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** État d'affichage (création prête d'emblée ; correction attend le chargement). */
type FormState = 'loading' | 'form' | 'not-found' | 'error';

/**
 * Formulaire de programmation / correction d'un **départ** (F8.23), monté sous
 * `/espace-prestataire/offres/depart/nouveau` et `.../depart/:id/modifier`.
 *
 * POURQUOI CET ÉCRAN EXISTE
 * -------------------------
 * ⚠️ `mobility_services` était en **lecture seule depuis B7.2**. Le catalogue
 * public `/mobilite` ne pouvait être alimenté que par le seeder : aucune navette
 * AIBD, aucune liaison interurbaine, aucun transfert n'était mettable en vente
 * par qui que ce soit — ni prestataire, ni agent.
 *
 * ⚠️ **Un départ n'est pas un véhicule.** Un même minibus assure une navette le
 * lundi et une liaison le mardi : ce qui se vend ici est le TRAJET DATÉ, le
 * véhicule n'en est que le moyen. C'est pour cela que l'écran est distinct de
 * celui du dépôt de véhicule, au lieu d'y ajouter des champs.
 *
 * ⚠️ **Aucun bloc photo, et c'est délibéré** : un départ hérite des photos de son
 * véhicule (F8.18). Le dire à l'écran évite au prestataire de chercher un
 * téléversement qui n'existe pas, et l'oriente vers le bon endroit — sa fiche
 * véhicule.
 */
@Component({
  selector: 'app-provider-departure-form-page',
  imports: [ReactiveFormsModule, RouterLink, BackLinkComponent],
  templateUrl: './provider-departure-form-page.html',
  styleUrl: './offer-form.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderDepartureFormPageComponent {
  private readonly offers = inject(OfferService);
  private readonly fb = inject(FormBuilder);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  /** Natures de trajet proposées (miroir de l'enum `MobilityServiceType`). */
  protected readonly types = MOBILITY_SERVICE_TYPES;

  /** Id du départ corrigé (null en programmation). */
  protected readonly editId = signal<number | null>(null);

  protected readonly state = signal<FormState>('form');
  protected readonly submitting = signal(false);
  protected readonly formError = signal<string | null>(null);

  /**
   * Mes véhicules, pour le sélecteur d'opérateur.
   *
   * ⚠️ **Le choix est restreint à MES véhicules, et c'est la moitié du garde-fou**
   * : le serveur refuse le véhicule d'un concurrent (il illustrerait l'annonce
   * avec le minibus de quelqu'un d'autre), mais un formulaire qui laisserait
   * saisir un identifiant libre transformerait cette règle en message d'erreur
   * incompréhensible. Ici, le cas fautif n'est simplement pas proposé.
   */
  protected readonly myVehicles = signal<Vehicle[]>([]);

  protected readonly form = this.fb.nonNullable.group({
    type: ['' as MobilityServiceTypeValue | '', [Validators.required]],
    departure: ['', [Validators.required, Validators.maxLength(255)]],
    destination: ['', [Validators.required, Validators.maxLength(255)]],
    departure_at: ['', [Validators.required]],
    capacity: [1, [Validators.required, Validators.min(1)]],
    price_xof: [0, [Validators.required, Validators.min(0)]],
    vehicle_id: [null as number | null],
    description: [''],
  });

  /** Vrai en mode correction (pour les libellés). */
  protected readonly isEdit = computed(() => this.editId() !== null);

  /** Le véhicule actuellement choisi, pour afficher sa capacité en clair. */
  protected readonly selectedVehicle = signal<Vehicle | null>(null);

  /**
   * Le plafond de places imposé par le véhicule choisi, ou `null` s'il n'y en a
   * pas. Affiché AVANT l'envoi : le serveur refuse une capacité supérieure à
   * celle du véhicule, autant que le prestataire le sache en saisissant.
   */
  protected readonly capacityCap = computed(() => this.selectedVehicle()?.capacity ?? null);

  /** Horodatage minimal accepté par le champ `datetime-local` : maintenant. */
  protected readonly minDeparture = this.toLocalInput(new Date());

  constructor() {
    this.form.controls.vehicle_id.valueChanges.subscribe((id) => {
      const nombre = id == null ? null : Number(id);
      this.selectedVehicle.set(this.myVehicles().find((v) => v.id === nombre) ?? null);
    });

    // Le sélecteur de véhicule se remplit dans tous les cas : un prestataire
    // sans véhicule publié peut quand même programmer un départ (le champ reste
    // facultatif), l'écran le lui dit alors explicitement.
    this.offers.myVehicles().subscribe({
      next: (res) => {
        this.myVehicles.set(res.data);
        // Rejoue la sélection : en correction, la liste arrive souvent APRÈS le
        // préremplissage, et le véhicule affiché resterait sinon introuvable.
        const id = this.form.controls.vehicle_id.value;
        this.selectedVehicle.set(res.data.find((v) => v.id === id) ?? null);
      },
      // Un échec ici ne doit pas bloquer la programmation : le champ est facultatif.
      error: () => this.myVehicles.set([]),
    });

    const idParam = this.route.snapshot.paramMap.get('id');
    if (idParam) {
      this.state.set('loading');
      this.editId.set(Number(idParam));
      this.offers.findMyDeparture(Number(idParam)).subscribe({
        next: (depart) => {
          if (!depart) {
            this.state.set('not-found');
            return;
          }
          this.patch(depart);
          this.state.set('form');
        },
        error: () => this.state.set('error'),
      });
    }
  }

  /** Pré-remplit le formulaire à partir d'un départ existant. */
  private patch(d: MobilityService): void {
    this.form.patchValue({
      type: (d.type as MobilityServiceTypeValue) ?? '',
      departure: d.departure,
      destination: d.destination,
      departure_at: d.departure_at ? this.toLocalInput(new Date(d.departure_at)) : '',
      capacity: d.capacity,
      price_xof: d.price_xof,
      vehicle_id: d.vehicle_id ?? null,
      description: d.description ?? '',
    });
  }

  /** Programme ou corrige le départ. */
  protected submit(): void {
    if (this.submitting() || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const raw = this.form.getRawValue();
    const payload: NewMobilityServicePayload = {
      type: raw.type as MobilityServiceTypeValue,
      departure: raw.departure,
      destination: raw.destination,
      departure_at: this.toServer(raw.departure_at),
      capacity: raw.capacity,
      price_xof: raw.price_xof,
      vehicle_id: raw.vehicle_id == null ? null : Number(raw.vehicle_id),
      description: raw.description || null,
    };

    this.submitting.set(true);
    this.formError.set(null);

    const id = this.editId();
    const request$ = id
      ? this.offers.updateDeparture(id, payload)
      : this.offers.createDeparture(payload);

    request$.subscribe({
      next: () => {
        this.submitting.set(false);
        this.router.navigate(['/espace-prestataire/offres']);
      },
      error: (err: { status?: number; error?: ValidationErrorBody }) => {
        this.submitting.set(false);
        this.formError.set(this.messageFor(err));
      },
    });
  }

  /**
   * `Date` → valeur d'un `<input type="datetime-local">` (`YYYY-MM-DDTHH:mm`).
   *
   * ⚠️ **Pas `toISOString()`** : celle-ci convertit en UTC et décalerait
   * l'affichage d'un départ saisi en heure locale — un bus de 06:00 s'afficherait
   * à 05:00 GMT. On lit donc les composantes locales, une à une.
   */
  private toLocalInput(date: Date): string {
    const p = (n: number) => String(n).padStart(2, '0');
    return (
      `${date.getFullYear()}-${p(date.getMonth() + 1)}-${p(date.getDate())}` +
      `T${p(date.getHours())}:${p(date.getMinutes())}`
    );
  }

  /**
   * Valeur du champ → format attendu par le serveur (`YYYY-MM-DD HH:mm:ss`).
   *
   * L'heure saisie est envoyée telle quelle, sans conversion de fuseau : le
   * produit est sénégalais, serveur et utilisateurs partagent le même temps, et
   * une conversion introduirait un décalage que personne n'a demandé.
   */
  private toServer(value: string): string {
    return `${value.replace('T', ' ')}:00`;
  }

  /** Traduit une erreur serveur en message affichable. */
  private messageFor(err: { status?: number; error?: ValidationErrorBody }): string {
    if (err?.status === 403) {
      return 'La programmation de départs est réservée aux prestataires dont le dossier est validé.';
    }
    const firstError = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
    return firstError ?? "Votre départ n'a pas pu être enregistré. Réessayez.";
  }
}
