import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import { AdminService, VehicleShowcaseCardAdmin } from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';

/**
 * Saisie en cours pour une carte véhicule, avant enregistrement.
 *
 * Le fichier ne peut pas passer par `[(ngModel)]` : il vit à part, posé par
 * le gestionnaire `(change)` du champ `<input type=file>`.
 */
interface VehicleShowcaseDraft {
  title: string;
  description: string;
  category: string;
  link_url: string;
  link_label: string;
  is_published: boolean;
  position: number;
  /** Nouvelle image choisie (`null` = on garde l'image déjà enregistrée). */
  image: File | null;
}

const EMPTY_CARD_DRAFT: VehicleShowcaseDraft = {
  title: '',
  description: '',
  category: '',
  link_url: '',
  link_label: '',
  is_published: false,
  position: 0,
  image: null,
};

/** Suggestions de départ pour la catégorie, en plus de celles déjà utilisées. */
const CATEGORIES_SUGGEREES = ['Berline', '4x4', 'Minibus'];

/**
 * Écran **Location de véhicules** du back-office (2026-09-07) — hors CDC,
 * contenu de vitrine.
 *
 * Remplace l'ancienne section « Protocole de confiance » de l'accueil :
 * cartes véhicules (image, texte, lien) rangées par catégorie libre
 * (berline, 4x4, minibus, ou toute autre catégorie inventée par le client),
 * sur le même patron que l'écran Actualités (`BackofficeNewsPageComponent`).
 */
@Component({
  selector: 'app-backoffice-vehicle-showcase-page',
  imports: [FormsModule],
  templateUrl: './backoffice-vehicle-showcase-page.html',
  styleUrl: './backoffice-vehicle-showcase-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeVehicleShowcasePageComponent {
  private readonly admin = inject(AdminService);

  protected readonly cardsLoading = signal(true);
  protected readonly cardsError = signal(false);
  protected readonly cardsActionError = signal<string | null>(null);

  protected readonly cards = signal<VehicleShowcaseCardAdmin[]>([]);

  // --- Accroche de la section (œilleton, titre, texte d'intro) ---------------
  // Réglages `home.vehicle_showcase_*` (SettingsRepository), même mécanisme
  // que le nombre de cartes « À découvrir » de l'écran Actualités.

  protected readonly headingLoading = signal(true);
  protected readonly headingSaving = signal(false);
  protected readonly headingMessage = signal<string | null>(null);
  protected readonly headingError = signal<string | null>(null);
  protected headingDraft = { eyebrow: '', title: '', lead: '' };

  /**
   * Catégories déjà utilisées, pour la `<datalist>` du formulaire — aide
   * l'équipe à rester cohérente (une seule graphie par catégorie) sans
   * imposer de liste fermée, plus quelques suggestions de départ.
   */
  protected readonly categoriesConnues = computed(() => {
    const vues = new Set(CATEGORIES_SUGGEREES);
    for (const card of this.cards()) {
      if (card.category) vues.add(card.category);
    }
    return [...vues];
  });

  /** Carte en cours d'édition (`null` = aucune, `'new'` = création). */
  protected readonly editingCard = signal<VehicleShowcaseCardAdmin | 'new' | null>(null);
  protected cardForm: VehicleShowcaseDraft = { ...EMPTY_CARD_DRAFT };
  protected readonly cardSaving = signal(false);

  constructor() {
    this.loadCards();
    this.loadHeading();
  }

  private loadHeading(): void {
    this.headingLoading.set(true);
    this.admin.settings().subscribe({
      next: (snapshot) => {
        const get = (key: string) =>
          String(snapshot.settings.find((s) => s.key === key)?.value ?? '');
        this.headingDraft = {
          eyebrow: get('home.vehicle_showcase_eyebrow'),
          title: get('home.vehicle_showcase_title'),
          lead: get('home.vehicle_showcase_lead'),
        };
        this.headingLoading.set(false);
      },
      error: () => this.headingLoading.set(false),
    });
  }

  protected saveHeading(): void {
    this.headingSaving.set(true);
    this.headingError.set(null);
    this.headingMessage.set(null);

    this.admin
      .updateSettings({
        'home.vehicle_showcase_eyebrow': this.headingDraft.eyebrow.trim(),
        'home.vehicle_showcase_title': this.headingDraft.title.trim(),
        'home.vehicle_showcase_lead': this.headingDraft.lead.trim(),
      })
      .subscribe({
        next: () => {
          this.headingSaving.set(false);
          this.headingMessage.set('Accroche enregistrée.');
        },
        error: (error: HttpErrorResponse) => {
          this.headingSaving.set(false);
          this.headingError.set(this.messageFor(error));
        },
      });
  }

  protected loadCards(): void {
    this.cardsLoading.set(true);
    this.cardsError.set(false);
    this.admin.vehicleShowcaseCards().subscribe({
      next: (cards) => {
        this.cards.set(cards);
        this.cardsLoading.set(false);
      },
      error: () => {
        this.cardsError.set(true);
        this.cardsLoading.set(false);
      },
    });
  }

  protected newCard(): void {
    this.cardsActionError.set(null);
    this.cardForm = { ...EMPTY_CARD_DRAFT };
    this.editingCard.set('new');
  }

  protected editCard(card: VehicleShowcaseCardAdmin): void {
    this.cardsActionError.set(null);
    this.cardForm = {
      title: card.title,
      description: card.description ?? '',
      category: card.category ?? '',
      link_url: card.link_url,
      link_label: card.link_label ?? '',
      is_published: card.is_published,
      position: card.position,
      image: null,
    };
    this.editingCard.set(card);
  }

  protected cancelCard(): void {
    this.editingCard.set(null);
    this.cardsActionError.set(null);
  }

  protected onCardImage(event: Event): void {
    this.cardForm.image = (event.target as HTMLInputElement).files?.[0] ?? null;
  }

  protected saveCard(): void {
    const editing = this.editingCard();
    if (!editing) return;

    if (editing === 'new' && !this.cardForm.image) {
      this.cardsActionError.set('Une image de couverture est obligatoire.');
      return;
    }

    if (!this.cardForm.category.trim()) {
      this.cardsActionError.set('La catégorie est obligatoire (berline, 4x4, minibus…).');
      return;
    }

    if (!this.cardForm.link_url.trim()) {
      this.cardsActionError.set('Le lien de la carte est obligatoire.');
      return;
    }

    this.cardsActionError.set(null);
    this.cardSaving.set(true);

    const form = this.cardForm;
    const request$ =
      editing === 'new'
        ? this.admin.createVehicleShowcaseCard({
            title: form.title.trim(),
            description: form.description.trim() || undefined,
            category: form.category.trim(),
            image: form.image as File,
            linkUrl: form.link_url.trim(),
            linkLabel: form.link_label.trim() || undefined,
            isPublished: form.is_published,
            position: form.position,
          })
        : this.admin.updateVehicleShowcaseCard(editing.id, {
            title: form.title.trim(),
            description: form.description.trim(),
            category: form.category.trim(),
            image: form.image ?? undefined,
            linkUrl: form.link_url.trim(),
            linkLabel: form.link_label.trim(),
            isPublished: form.is_published,
            position: form.position,
          });

    request$.subscribe({
      next: () => {
        this.cardSaving.set(false);
        this.editingCard.set(null);
        this.loadCards();
      },
      error: (error: HttpErrorResponse) => {
        this.cardSaving.set(false);
        this.cardsActionError.set(this.messageFor(error));
      },
    });
  }

  protected deleteCard(card: VehicleShowcaseCardAdmin): void {
    this.cardsActionError.set(null);
    this.admin.deleteVehicleShowcaseCard(card.id).subscribe({
      next: () => this.cards.update((list) => list.filter((item) => item.id !== card.id)),
      error: (error: HttpErrorResponse) => this.cardsActionError.set(this.messageFor(error)),
    });
  }

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 422) {
      const body = error.error as ValidationErrorBody | null;
      const first = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
      return first ?? body?.message ?? 'Données invalides.';
    }
    if (error.status === 403) {
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Action réservée aux comptes disposant du droit « paramètres ».';
    }
    return 'Opération impossible pour le moment. Réessayez.';
  }
}
