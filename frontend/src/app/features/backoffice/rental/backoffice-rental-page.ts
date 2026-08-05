import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';

import { AdminService, MandateDossier, MandateQuery } from '../../../core/api/admin.service';
import { Property } from '../../../models/property.model';

/** Option de filtre par statut. */
interface StatusOption {
  value: string;
  label: string;
}

/**
 * Écran **Gestion locative** du back-office — CDC §6 *Gestion locative*.
 *
 * Anciennement un onglet de l'écran « Dossiers », partagé avec la construction.
 * Les deux métiers n'ont ni le même cycle ni les mêmes gestes : chacun a
 * désormais sa rubrique au rail (F7.3.c).
 *
 * Liste des mandats de gestion de tous les propriétaires
 * (`GET /admin/mandates`, garde `consulter:dashboard-admin`) avec le bien géré,
 * le propriétaire, la commission, la période et les agrégats financiers —
 * **loyers impayés en alerte**, dépenses, reversements, incidents ouverts.
 * Chaque ligne ouvre la **fiche pilotable** du mandat (F7.3.a), où se font
 * réellement les encaissements, les incidents, les dépenses et les reversements.
 */
@Component({
  selector: 'app-backoffice-rental-page',
  imports: [FormsModule],
  templateUrl: './backoffice-rental-page.html',
  styleUrl: './backoffice-rental-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeRentalPageComponent {
  private readonly admin = inject(AdminService);
  private readonly router = inject(Router);

  protected readonly rows = signal<MandateDossier[]>([]);
  protected readonly total = signal(0);
  protected readonly page = signal(1);
  protected readonly lastPage = signal(1);
  protected readonly loading = signal(true);
  protected readonly error = signal(false);

  protected status = '';

  protected readonly statusOptions: readonly StatusOption[] = [
    { value: '', label: 'Tous les statuts' },
    { value: 'en_attente', label: 'En attente' },
    { value: 'actif', label: 'Actif' },
    { value: 'suspendu', label: 'Suspendu' },
    { value: 'termine', label: 'Terminé' },
  ];

  constructor() {
    this.load();
  }

  protected applyFilters(): void {
    this.page.set(1);
    this.load();
  }

  protected load(): void {
    this.loading.set(true);
    this.error.set(false);

    const query: MandateQuery = {
      status: this.status || undefined,
      page: this.page(),
    };

    this.admin.adminMandates(query).subscribe({
      next: (paginated) => {
        this.rows.set(paginated.data);
        this.total.set(paginated.meta.total);
        this.lastPage.set(paginated.meta.last_page);
        this.loading.set(false);
      },
      error: () => {
        this.error.set(true);
        this.loading.set(false);
      },
    });
  }

  protected goTo(page: number): void {
    if (page < 1 || page > this.lastPage() || page === this.page()) return;
    this.page.set(page);
    this.load();
  }

  // --- Ouvrir un mandat (F8.15.d) ---------------------------------------------
  //
  // ⚠️ `POST /manage/mandates` était écrit depuis B6 et n'avait AUCUN appelant :
  // le back-office savait tout piloter d'un mandat — loyers, incidents,
  // dépenses, reversements, rapport mensuel — SAUF en créer un. Tous les
  // mandats de la base venaient du seeder. Le CTA « Confier mon bien » de la
  // page publique produisait une demande générique, qu'aucun geste ne
  // transformait en contrat : l'univers Kaikun Manage n'avait pas de porte
  // d'entrée.

  /** Le volet de création est-il ouvert ? */
  protected readonly creating = signal(false);
  /** Résultats de la recherche de bien (le mandat porte sur UN bien). */
  protected readonly candidates = signal<Property[]>([]);
  protected readonly searching = signal(false);
  /** Bien retenu — tant qu'il est nul, le formulaire ne peut pas partir. */
  protected readonly picked = signal<Property | null>(null);
  protected readonly saving = signal(false);
  protected readonly createError = signal<string | null>(null);
  /** Référence du mandat créé (bandeau de succès). */
  protected readonly created = signal<string | null>(null);

  protected propertySearch = '';
  protected commissionRate = 10;
  protected startDate = new Date().toISOString().slice(0, 10);
  protected endDate = '';
  protected terms = '';

  protected toggleCreate(): void {
    this.creating.update((open) => !open);
    this.resetCreate();
  }

  /** Cherche un bien à mettre sous mandat (`GET /admin/properties?q=`). */
  protected searchProperty(): void {
    const term = this.propertySearch.trim();
    if (term.length < 2) return;

    this.searching.set(true);
    this.createError.set(null);

    this.admin.adminProperties({ q: term }).subscribe({
      next: (paginated) => {
        this.candidates.set(paginated.data);
        this.searching.set(false);
      },
      error: () => {
        this.searching.set(false);
        this.createError.set('La recherche de biens a échoué. Réessayez.');
      },
    });
  }

  protected pick(property: Property): void {
    this.picked.set(property);
    this.candidates.set([]);
    this.createError.set(null);
  }

  /** Repose le bien retenu pour en chercher un autre. */
  protected clearPick(): void {
    this.picked.set(null);
    this.propertySearch = '';
    this.candidates.set([]);
  }

  /**
   * Ouvre le mandat. Le **propriétaire n'est pas envoyé** : le serveur le déduit
   * du bien — deux sources pour la même information, c'est une incohérence
   * garantie le jour où un bien change de main.
   *
   * ⚠️ Le serveur refuse un bien **déjà sous mandat vivant** (422) : deux
   * mandats sur le même bien, ce sont deux commissions sur le même loyer et un
   * rapport mensuel faux. On affiche son message tel quel, il nomme le mandat
   * en cause.
   */
  protected submitMandate(): void {
    const property = this.picked();
    if (!property || this.saving()) return;

    this.saving.set(true);
    this.createError.set(null);

    this.admin
      .createMandate({
        property_id: property.id,
        commission_rate: this.commissionRate,
        start_date: this.startDate,
        end_date: this.endDate || null,
        terms: this.terms.trim() || null,
      })
      .subscribe({
        next: (mandate) => {
          this.saving.set(false);
          this.created.set(mandate.reference);
          this.picked.set(null);
          // La liste doit montrer le mandat qu'on vient d'ouvrir.
          this.page.set(1);
          this.load();
        },
        error: (err: { error?: { errors?: Record<string, string[]>; message?: string } }) => {
          this.saving.set(false);
          const first = err?.error?.errors ? Object.values(err.error.errors)[0]?.[0] : null;
          this.createError.set(
            first ?? err?.error?.message ?? "Le mandat n'a pas pu être ouvert. Réessayez.",
          );
        },
      });
  }

  private resetCreate(): void {
    this.propertySearch = '';
    this.commissionRate = 10;
    this.startDate = new Date().toISOString().slice(0, 10);
    this.endDate = '';
    this.terms = '';
    this.candidates.set([]);
    this.picked.set(null);
    this.createError.set(null);
    this.created.set(null);
  }

  /** Libellé court d'un bien candidat (titre + propriétaire). */
  protected candidateLabel(property: Property): string {
    const owner = property.owner?.name;
    return owner ? `${property.title} — ${owner}` : property.title;
  }

  /** Ouvre la fiche PILOTABLE du mandat (F7.3.a). */
  protected open(mandate: MandateDossier): void {
    void this.router.navigate(['/back-office', 'gestion-locative', mandate.id]);
  }

  // --- Présentation -----------------------------------------------------------

  protected statusClass(status: string | null): string {
    switch (status) {
      case 'actif':
        return 'is-ok';
      case 'suspendu':
        return 'is-warn';
      case 'termine':
        return 'is-off';
      default:
        return 'is-pending';
    }
  }

  /** Localisation courte du bien géré (commune · département · région). */
  protected placeOf(mandate: MandateDossier): string {
    const location = mandate.property.location;
    if (!location) return '—';
    const parts = [location.commune, location.department, location.region].filter(
      (part): part is string => !!part,
    );
    return parts.length ? parts.join(' · ') : '—';
  }

  /** Taux de commission formaté en pourcentage. */
  protected rate(value: number | string | null): string {
    if (value === null || value === undefined) return '—';
    const n = typeof value === 'string' ? Number(value) : value;
    if (Number.isNaN(n)) return '—';
    return `${n} %`;
  }

  /** Montant formaté en FCFA. */
  protected xof(value: number | null | undefined): string {
    if (value === null || value === undefined) return '—';
    return new Intl.NumberFormat('fr-FR').format(value) + ' F';
  }

  /** Date courte (jour/mois/année). */
  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }
}
