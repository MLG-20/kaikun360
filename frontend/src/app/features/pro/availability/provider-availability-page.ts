import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { ProviderService } from '../../../core/api/provider.service';
import { Unavailability, WeeklyAvailability } from '../../../models/provider.model';
import { BackLinkComponent } from '../../../shared/components/back-link/back-link';

/** Libellés des jours (0 = lundi … 6 = dimanche). */
const WEEKDAY_LABELS = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];

/** Ligne éditable du planning hebdomadaire (modèle local mutable). */
interface WeeklyRow {
  weekday: number;
  label: string;
  is_open: boolean;
  start_time: string;
  end_time: string;
}

/**
 * Écran « Disponibilités » de l'espace prestataire (F5.4), monté sous
 * `/espace-prestataire/disponibilites`. Deux volets :
 *
 *  - le **planning hebdomadaire récurrent** (`PUT .../availability/weekly`) : sept
 *    jours, chacun ouvert (avec une plage horaire) ou fermé ;
 *  - les **périodes d'indisponibilité** ponctuelles (`POST` / `DELETE`), congés
 *    qui priment sur le planning.
 *
 * Données chargées via `GET /providers/availability`.
 */
@Component({
  selector: 'app-provider-availability-page',
  imports: [ReactiveFormsModule, BackLinkComponent],
  templateUrl: './provider-availability-page.html',
  styleUrl: './provider-availability-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ProviderAvailabilityPageComponent {
  private readonly providers = inject(ProviderService);
  private readonly fb = inject(FormBuilder);

  // — État global —
  protected readonly loading = signal(true);
  protected readonly loadError = signal(false);

  // — Planning hebdomadaire —
  protected readonly rows = signal<WeeklyRow[]>([]);
  protected readonly savingWeekly = signal(false);
  protected readonly weeklySaved = signal(false);
  protected readonly weeklyError = signal<string | null>(null);

  // — Indisponibilités —
  protected readonly unavailabilities = signal<Unavailability[]>([]);
  protected readonly addingOff = signal(false);
  protected readonly offError = signal<string | null>(null);

  /** Formulaire d'ajout d'une indisponibilité. */
  protected readonly offForm = this.fb.group({
    start_date: ['', Validators.required],
    end_date: ['', Validators.required],
    reason: [''],
  });

  /** Au moins un jour ouvert dans le planning ? (pour un message d'aide). */
  protected readonly hasOpenDay = computed(() => this.rows().some((r) => r.is_open));

  constructor() {
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.loadError.set(false);
    this.providers.availability().subscribe({
      next: (res) => {
        this.rows.set(
          res.data.weekly.map((d) => ({
            weekday: d.weekday,
            label: WEEKDAY_LABELS[d.weekday] ?? `Jour ${d.weekday}`,
            is_open: d.is_open,
            start_time: d.start_time ?? '09:00',
            end_time: d.end_time ?? '18:00',
          })),
        );
        this.unavailabilities.set(res.data.unavailabilities);
        this.loading.set(false);
      },
      error: () => {
        this.loadError.set(true);
        this.loading.set(false);
      },
    });
  }

  /** Bascule ouvert/fermé d'un jour (met à jour la ligne dans le signal). */
  protected toggleDay(weekday: number, open: boolean): void {
    this.rows.update((rows) =>
      rows.map((r) => (r.weekday === weekday ? { ...r, is_open: open } : r)),
    );
    this.weeklySaved.set(false);
  }

  /** Met à jour une heure (début/fin) d'un jour. */
  protected setTime(weekday: number, field: 'start_time' | 'end_time', value: string): void {
    this.rows.update((rows) =>
      rows.map((r) => (r.weekday === weekday ? { ...r, [field]: value } : r)),
    );
    this.weeklySaved.set(false);
  }

  /** Enregistre le planning hebdomadaire (les 7 jours). */
  protected saveWeekly(): void {
    // Garde-fou client : sur un jour ouvert, la fin doit suivre le début.
    const invalid = this.rows().some((r) => r.is_open && r.end_time <= r.start_time);
    if (invalid) {
      this.weeklyError.set('Sur un jour ouvert, l’heure de fin doit être après le début.');
      return;
    }

    this.savingWeekly.set(true);
    this.weeklyError.set(null);
    this.weeklySaved.set(false);

    const days: WeeklyAvailability[] = this.rows().map((r) => ({
      weekday: r.weekday,
      is_open: r.is_open,
      start_time: r.is_open ? r.start_time : null,
      end_time: r.is_open ? r.end_time : null,
    }));

    this.providers.saveWeekly(days).subscribe({
      next: () => {
        this.savingWeekly.set(false);
        this.weeklySaved.set(true);
      },
      error: () => {
        this.savingWeekly.set(false);
        this.weeklyError.set("Le planning n'a pas pu être enregistré. Vérifiez vos horaires.");
      },
    });
  }

  /** Ajoute une période d'indisponibilité. */
  protected addOff(): void {
    if (this.offForm.invalid) {
      this.offForm.markAllAsTouched();
      return;
    }
    const raw = this.offForm.getRawValue();
    const startDate = raw.start_date ?? '';
    const endDate = raw.end_date ?? '';
    if (endDate < startDate) {
      this.offError.set('La date de fin doit être postérieure ou égale à la date de début.');
      return;
    }

    this.addingOff.set(true);
    this.offError.set(null);
    this.providers
      .addUnavailability({
        start_date: startDate,
        end_date: endDate,
        reason: raw.reason?.trim() || null,
      })
      .subscribe({
        next: (res) => {
          // Insertion triée par date de début.
          this.unavailabilities.update((list) =>
            [...list, res.data.unavailability].sort((a, b) =>
              a.start_date.localeCompare(b.start_date),
            ),
          );
          this.offForm.reset({ start_date: '', end_date: '', reason: '' });
          this.addingOff.set(false);
        },
        error: () => {
          this.addingOff.set(false);
          this.offError.set("La période n'a pas pu être ajoutée. Vérifiez les dates.");
        },
      });
  }

  /** Supprime une période d'indisponibilité (avec confirmation). */
  protected removeOff(period: Unavailability): void {
    if (typeof window !== 'undefined' && !window.confirm('Supprimer cette indisponibilité ?')) {
      return;
    }
    this.providers.removeUnavailability(period.id).subscribe({
      next: () => {
        this.unavailabilities.update((list) => list.filter((p) => p.id !== period.id));
      },
      error: () => {
        this.offError.set("La période n'a pas pu être supprimée.");
      },
    });
  }

  /** Formate une plage de dates (« du 12 au 15 août 2026 » ou « le 3 sept. 2026 »). */
  protected formatRange(period: Unavailability): string {
    const start = new Date(period.start_date);
    const end = new Date(period.end_date);
    const long = (d: Date) =>
      d.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' });
    if (period.start_date === period.end_date) {
      return `Le ${long(start)}`;
    }
    return `Du ${long(start)} au ${long(end)}`;
  }
}
