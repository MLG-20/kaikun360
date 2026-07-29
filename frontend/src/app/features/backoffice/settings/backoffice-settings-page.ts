import { HttpErrorResponse } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';

import {
  AdminCommune,
  AdminService,
  ContentPage,
  FaqEntry,
  GeoDepartment,
  GeoRegion,
  NotificationEventOption,
  PlatformSetting,
  ReferenceCatalog,
} from '../../../core/api/admin.service';
import { ValidationErrorBody } from '../../../core/api/api-response.model';

/** Onglet actif de l'écran Paramètres. */
type SettingsTab = 'settings' | 'notifications' | 'content' | 'reference';

/** Sous-onglet du contenu éditorial. */
type ContentTab = 'pages' | 'faqs';

/** Un réglage simple (texte / nombre) rendu comme un champ de formulaire. */
interface SettingField {
  key: string;
  label: string;
  hint: string;
  type: 'string' | 'number';
  overridden: boolean;
}

/**
 * Une feuille numérique du barème de construction (`build.pricing`).
 *
 * Le barème est un objet imbriqué ; on l'aplatit en chemins (`price_m2.
 * construction_neuve`) pour le rendre éditable champ par champ sans coder en
 * dur sa structure — si le barème gagne une rubrique côté serveur, elle
 * apparaît toute seule à l'écran.
 */
interface PricingField {
  path: string;
  section: string;
  label: string;
  value: number;
}

/**
 * Écran **Paramètres & contenu** du back-office (F7.2.l) — CDC §6 *Paramètres*
 * (« Villes, catégories, tarifs, commissions, pages, FAQ, notifications »).
 *
 * Dernier des 14 modules du cahier des charges. Quatre onglets, parce que ces
 * sept fonctions ne se pilotent pas de la même façon :
 *
 *  - **Réglages** (`GET`/`PATCH /admin/settings`) : commissions & marges,
 *    coordonnées publiques, et le **barème du simulateur de construction**
 *    (« tarifs »), aplati en champs numériques. Seules les clés réellement
 *    modifiées sont envoyées.
 *  - **Notifications** : coupure des canaux (SMS facturé, e-mail) et
 *    interrupteur par événement, groupés par destinataire. Réellement branché
 *    sur les `via()` côté serveur — les codes de sécurité/2FA en sont exclus.
 *  - **Contenu** (`/admin/pages`, `/admin/faqs`) : CRUD complet des pages
 *    éditoriales et de la FAQ, publiées ou masquées.
 *  - **Référentiels** : les **villes** (arborescence région → département →
 *    communes, avec création/renommage/suppression gardée par l'usage réel) et
 *    les **catégories**, en lecture seule (voir l'encart à l'écran).
 */
@Component({
  selector: 'app-backoffice-settings-page',
  imports: [FormsModule],
  templateUrl: './backoffice-settings-page.html',
  styleUrl: './backoffice-settings-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class BackofficeSettingsPageComponent {
  private readonly admin = inject(AdminService);

  protected readonly tab = signal<SettingsTab>('settings');

  // --- Onglet Réglages --------------------------------------------------------

  protected readonly settingsLoading = signal(true);
  protected readonly settingsError = signal(false);
  protected readonly settingsSaving = signal(false);
  protected readonly settingsMessage = signal<string | null>(null);
  protected readonly settingsActionError = signal<string | null>(null);

  /** Réglages bruts renvoyés par le serveur (référence pour détecter les modifs). */
  private readonly rawSettings = signal<PlatformSetting[]>([]);

  /** Valeurs en cours d'édition, par clé (chaînes : ce que rendent les inputs). */
  protected readonly draft = signal<Record<string, string>>({});

  /** Barème de construction aplati et éditable. */
  protected readonly pricingFields = signal<PricingField[]>([]);

  /** Libellés lisibles des réglages simples. */
  private readonly settingLabels: Record<string, { label: string; hint: string }> = {
    'commission.default_rate': {
      label: 'Commission par défaut (%)',
      hint: 'Appliquée aux réservations mobilité et aux missions prestataires.',
    },
    'teambuilding.margin_rate': {
      label: 'Marge team building (%)',
      hint: 'Marge ajoutée par défaut à la composition d’un devis pack.',
    },
    'platform.currency': { label: 'Devise', hint: 'Code ISO affiché dans les montants (XOF).' },
    'support.email': { label: 'E-mail du support', hint: 'Affiché sur la page Contact publique.' },
    'support.phone': {
      label: 'Téléphone du support',
      hint: 'Sert aussi au lien WhatsApp contextuel et au paiement manuel Wave/OM.',
    },
    'contact.address': { label: 'Adresse du siège', hint: 'Affichée sur la page Contact.' },
    'contact.latitude': { label: 'Latitude', hint: 'Position du marqueur sur la carte de contact.' },
    'contact.longitude': { label: 'Longitude', hint: 'Position du marqueur sur la carte de contact.' },
    // Réseaux sociaux : affichés dans le pied de page public. Un champ laissé
    // vide masque simplement le réseau — aucun lien mort n'est publié.
    'social.facebook': {
      label: 'Facebook',
      hint: 'URL complète de la page. Vide = non affiché dans le pied de page.',
    },
    'social.instagram': { label: 'Instagram', hint: 'URL complète du compte. Vide = non affiché.' },
    'social.tiktok': { label: 'TikTok', hint: 'URL complète du compte. Vide = non affiché.' },
    'social.linkedin': { label: 'LinkedIn', hint: 'URL complète de la page. Vide = non affiché.' },
    'social.youtube': { label: 'YouTube', hint: 'URL complète de la chaîne. Vide = non affichée.' },
  };

  /** Libellés des groupes de réglages. */
  private readonly groupLabels: Record<string, string> = {
    general: 'Général & contact',
    commissions: 'Commissions & marges',
  };

  /** Libellés des segments du barème de construction. */
  private readonly pricingLabels: Record<string, string> = {
    price_m2: 'Coût de base au m² (FCFA)',
    finish_coeff: 'Coefficient de finition',
    zone_coeff: 'Coefficient de zone',
    fees: 'Frais annexes (part du coût des travaux)',
    land_acquisition_rate: 'Frais d’acquisition du terrain',
    rental_yield: 'Rendement locatif annuel indicatif',
    rounding_step: 'Pas d’arrondi de l’estimation',
    construction_neuve: 'Construction neuve',
    extension: 'Extension',
    renovation: 'Rénovation',
    economique: 'Économique',
    standard: 'Standard',
    premium: 'Premium',
    dakar: 'Dakar',
    autres_regions: 'Autres régions',
    zones_eloignees: 'Zones éloignées',
    etudes: 'Études',
    permis: 'Permis',
    viabilisation: 'Viabilisation',
    longue_duree: 'Longue durée',
    nuitee: 'Nuitée',
    min: 'Minimum',
    max: 'Maximum',
  };

  // --- Onglet Notifications ---------------------------------------------------

  protected readonly notificationEvents = signal<NotificationEventOption[]>([]);
  protected readonly emailEnabled = signal(true);
  protected readonly smsEnabled = signal(true);
  protected readonly notificationsSaving = signal(false);
  protected readonly notificationsMessage = signal<string | null>(null);
  protected readonly notificationsError = signal<string | null>(null);

  /** Événements adressés aux clients / partenaires. */
  protected readonly clientEvents = computed(() =>
    this.notificationEvents().filter((event) => event.audience !== 'Équipe Kaikun'),
  );

  /** Événements adressés à l'équipe interne. */
  protected readonly staffEvents = computed(() =>
    this.notificationEvents().filter((event) => event.audience === 'Équipe Kaikun'),
  );

  // --- Onglet Contenu ---------------------------------------------------------

  protected readonly contentTab = signal<ContentTab>('pages');

  protected readonly pages = signal<ContentPage[]>([]);
  protected readonly pagesLoading = signal(false);
  protected readonly pagesLoaded = signal(false);
  protected readonly pagesError = signal(false);
  protected readonly pageActionError = signal<string | null>(null);
  /** Page en cours d'édition (`null` = aucune, `'new'` = création). */
  protected readonly editingPage = signal<ContentPage | 'new' | null>(null);
  protected pageForm = { slug: '', title: '', body: '', is_published: false };

  protected readonly faqs = signal<FaqEntry[]>([]);
  protected readonly faqsLoading = signal(false);
  protected readonly faqsLoaded = signal(false);
  protected readonly faqsError = signal(false);
  protected readonly faqActionError = signal<string | null>(null);
  protected readonly editingFaq = signal<FaqEntry | 'new' | null>(null);
  protected faqForm = { question: '', answer: '', category: '', position: 0, is_published: false };

  // --- Onglet Référentiels ----------------------------------------------------

  protected readonly regions = signal<GeoRegion[]>([]);
  protected readonly geoLoading = signal(false);
  protected readonly geoLoaded = signal(false);
  protected readonly geoError = signal(false);

  /** Région dépliée dans l'arbre (une seule à la fois). */
  protected readonly openRegionId = signal<number | null>(null);
  /** Département sélectionné : c'est lui qui pilote la liste des communes. */
  protected readonly selectedDepartment = signal<GeoDepartment | null>(null);

  protected readonly communes = signal<AdminCommune[]>([]);
  protected readonly communesLoading = signal(false);
  protected readonly communesTotal = signal(0);
  protected readonly communesPage = signal(1);
  protected readonly communesLastPage = signal(1);
  protected readonly communeActionError = signal<string | null>(null);
  protected communeSearch = '';

  /** Commune en cours de renommage, ou `'new'` pour une création. */
  protected readonly editingCommune = signal<AdminCommune | 'new' | null>(null);
  protected communeForm = { name: '', type: '' };

  /** Département en cours de renommage / création (dans l'arbre). */
  protected readonly editingDepartment = signal<GeoDepartment | 'new' | null>(null);
  protected departmentForm = { name: '', region_id: 0 };
  protected readonly departmentActionError = signal<string | null>(null);

  protected readonly reference = signal<ReferenceCatalog | null>(null);
  protected readonly referenceLoading = signal(false);

  /** Les 4 nomenclatures, mises à plat pour l'affichage en lecture seule. */
  protected readonly categoryGroups = computed(() => {
    const catalog = this.reference();
    if (!catalog) return [];
    return [
      { label: 'Catégories de prestataires', items: catalog.categories.provider },
      { label: 'Types de bien', items: catalog.categories.property_type },
      { label: 'Types de service', items: catalog.categories.service_type },
      { label: 'Types de véhicule', items: catalog.categories.vehicle_type },
    ];
  });

  constructor() {
    this.loadSettings();
  }

  /** Bascule d'onglet — chaque onglet charge ses données à sa première ouverture. */
  protected switchTab(tab: SettingsTab): void {
    if (this.tab() === tab) return;
    this.tab.set(tab);

    if (tab === 'content' && !this.pagesLoaded()) this.loadPages();
    if (tab === 'reference') {
      if (!this.geoLoaded()) this.loadGeography();
      if (!this.reference()) this.loadReference();
    }
  }

  // ===========================================================================
  // Onglet Réglages
  // ===========================================================================

  protected loadSettings(): void {
    this.settingsLoading.set(true);
    this.settingsError.set(false);

    this.admin.settings().subscribe({
      next: (snapshot) => {
        this.rawSettings.set(snapshot.settings);
        this.notificationEvents.set(snapshot.notification_events);

        // Les réglages simples alimentent le brouillon ; le barème et les
        // interrupteurs de notification ont leur propre rendu.
        const draft: Record<string, string> = {};
        for (const setting of snapshot.settings) {
          if (setting.group === 'notifications') {
            if (setting.key === 'notifications.email_enabled') this.emailEnabled.set(!!setting.value);
            if (setting.key === 'notifications.sms_enabled') this.smsEnabled.set(!!setting.value);
            continue;
          }
          if (setting.type === 'json') {
            if (setting.key === 'build.pricing') {
              this.pricingFields.set(this.flattenPricing(setting.value));
            }
            continue;
          }
          draft[setting.key] = String(setting.value ?? '');
        }
        this.draft.set(draft);

        this.settingsLoading.set(false);
      },
      error: () => {
        this.settingsError.set(true);
        this.settingsLoading.set(false);
      },
    });
  }

  /** Réglages simples d'un groupe, prêts à rendre. */
  protected fieldsOf(group: string): SettingField[] {
    return this.rawSettings()
      .filter((setting) => setting.group === group && setting.type !== 'json')
      .map((setting) => ({
        key: setting.key,
        label: this.settingLabels[setting.key]?.label ?? setting.key,
        hint: this.settingLabels[setting.key]?.hint ?? '',
        type: setting.type === 'float' || setting.type === 'integer' ? 'number' : 'string',
        overridden: setting.overridden,
      }));
  }

  protected groupLabel(group: string): string {
    return this.groupLabels[group] ?? group;
  }

  /** Valeur courante d'un champ du brouillon (liaison des inputs). */
  protected draftValue(key: string): string {
    return this.draft()[key] ?? '';
  }

  protected setDraftValue(key: string, value: string): void {
    this.draft.update((current) => ({ ...current, [key]: value }));
  }

  protected setPricingValue(path: string, value: string): void {
    const parsed = Number(value);
    this.pricingFields.update((fields) =>
      fields.map((field) => (field.path === path ? { ...field, value: parsed } : field)),
    );
  }

  /** Sections du barème, dans l'ordre où le serveur les renvoie. */
  protected readonly pricingSections = computed(() => {
    const sections: string[] = [];
    for (const field of this.pricingFields()) {
      if (!sections.includes(field.section)) sections.push(field.section);
    }
    return sections;
  });

  protected pricingFieldsOf(section: string): PricingField[] {
    return this.pricingFields().filter((field) => field.section === section);
  }

  /**
   * Enregistre les réglages MODIFIÉS uniquement.
   *
   * Envoyer tout le catalogue transformerait chaque valeur par défaut en
   * surcharge en base : le drapeau « valeur par défaut » perdrait son sens et
   * un futur ajustement du code n'aurait plus d'effet.
   */
  protected saveSettings(): void {
    const changed: Record<string, unknown> = {};
    const draft = this.draft();

    for (const setting of this.rawSettings()) {
      if (setting.group === 'notifications' || setting.type === 'json') continue;

      const next = draft[setting.key] ?? '';
      const before = String(setting.value ?? '');
      if (next === before) continue;

      changed[setting.key] =
        setting.type === 'float' || setting.type === 'integer' ? Number(next) : next;
    }

    // Le barème est envoyé en bloc dès qu'une de ses valeurs bouge.
    const pricing = this.rawSettings().find((setting) => setting.key === 'build.pricing');
    if (pricing && this.pricingChanged(pricing.value)) {
      changed['build.pricing'] = this.rebuildPricing(pricing.value);
    }

    if (Object.keys(changed).length === 0) {
      this.settingsMessage.set('Aucune modification à enregistrer.');
      return;
    }

    this.settingsSaving.set(true);
    this.settingsActionError.set(null);
    this.settingsMessage.set(null);

    this.admin.updateSettings(changed).subscribe({
      next: (snapshot) => {
        this.rawSettings.set(snapshot.settings);
        this.notificationEvents.set(snapshot.notification_events);
        this.settingsSaving.set(false);
        this.settingsMessage.set('Paramètres enregistrés.');
      },
      error: (error: HttpErrorResponse) => {
        this.settingsSaving.set(false);
        this.settingsActionError.set(this.messageFor(error));
      },
    });
  }

  // ===========================================================================
  // Onglet Notifications
  // ===========================================================================

  protected toggleEvent(value: string, enabled: boolean): void {
    this.notificationEvents.update((events) =>
      events.map((event) => (event.value === value ? { ...event, enabled } : event)),
    );
  }

  /** Enregistre les canaux et la carte complète des événements. */
  protected saveNotifications(): void {
    const events: Record<string, boolean> = {};
    for (const event of this.notificationEvents()) events[event.value] = event.enabled;

    this.notificationsSaving.set(true);
    this.notificationsError.set(null);
    this.notificationsMessage.set(null);

    this.admin
      .updateSettings({
        'notifications.email_enabled': this.emailEnabled(),
        'notifications.sms_enabled': this.smsEnabled(),
        'notifications.events': events,
      })
      .subscribe({
        next: (snapshot) => {
          this.rawSettings.set(snapshot.settings);
          this.notificationEvents.set(snapshot.notification_events);
          this.notificationsSaving.set(false);
          this.notificationsMessage.set('Notifications mises à jour.');
        },
        error: (error: HttpErrorResponse) => {
          this.notificationsSaving.set(false);
          this.notificationsError.set(this.messageFor(error));
        },
      });
  }

  // ===========================================================================
  // Onglet Contenu — pages
  // ===========================================================================

  protected switchContentTab(tab: ContentTab): void {
    if (this.contentTab() === tab) return;
    this.contentTab.set(tab);
    if (tab === 'faqs' && !this.faqsLoaded()) this.loadFaqs();
  }

  protected loadPages(): void {
    this.pagesLoading.set(true);
    this.pagesError.set(false);
    this.admin.pages().subscribe({
      next: (pages) => {
        this.pages.set(pages);
        this.pagesLoaded.set(true);
        this.pagesLoading.set(false);
      },
      error: () => {
        this.pagesError.set(true);
        this.pagesLoading.set(false);
      },
    });
  }

  protected newPage(): void {
    this.pageActionError.set(null);
    this.pageForm = { slug: '', title: '', body: '', is_published: false };
    this.editingPage.set('new');
  }

  protected editPage(page: ContentPage): void {
    this.pageActionError.set(null);
    this.pageForm = {
      slug: page.slug,
      title: page.title,
      body: page.body,
      is_published: page.is_published,
    };
    this.editingPage.set(page);
  }

  protected cancelPage(): void {
    this.editingPage.set(null);
    this.pageActionError.set(null);
  }

  protected savePage(): void {
    const editing = this.editingPage();
    if (!editing) return;

    this.pageActionError.set(null);
    const payload = {
      slug: this.pageForm.slug.trim(),
      title: this.pageForm.title.trim(),
      body: this.pageForm.body,
      is_published: this.pageForm.is_published,
    };

    const request$ =
      editing === 'new'
        ? this.admin.createPage(payload)
        : // On adresse l'ANCIEN slug : c'est la clé de route côté serveur.
          this.admin.updatePage(editing.slug, payload);

    request$.subscribe({
      next: () => {
        this.editingPage.set(null);
        this.loadPages();
      },
      error: (error: HttpErrorResponse) => this.pageActionError.set(this.messageFor(error)),
    });
  }

  protected deletePage(page: ContentPage): void {
    this.pageActionError.set(null);
    this.admin.deletePage(page.slug).subscribe({
      next: () => this.pages.update((list) => list.filter((item) => item.id !== page.id)),
      error: (error: HttpErrorResponse) => this.pageActionError.set(this.messageFor(error)),
    });
  }

  // ===========================================================================
  // Onglet Contenu — FAQ
  // ===========================================================================

  protected loadFaqs(): void {
    this.faqsLoading.set(true);
    this.faqsError.set(false);
    this.admin.faqs().subscribe({
      next: (faqs) => {
        this.faqs.set(faqs);
        this.faqsLoaded.set(true);
        this.faqsLoading.set(false);
      },
      error: () => {
        this.faqsError.set(true);
        this.faqsLoading.set(false);
      },
    });
  }

  protected newFaq(): void {
    this.faqActionError.set(null);
    // Position par défaut = à la suite, pour ne pas bousculer l'ordre existant.
    const next = this.faqs().reduce((max, faq) => Math.max(max, faq.position ?? 0), 0) + 1;
    this.faqForm = { question: '', answer: '', category: '', position: next, is_published: false };
    this.editingFaq.set('new');
  }

  protected editFaq(faq: FaqEntry): void {
    this.faqActionError.set(null);
    this.faqForm = {
      question: faq.question,
      answer: faq.answer,
      category: faq.category ?? '',
      position: faq.position ?? 0,
      is_published: faq.is_published,
    };
    this.editingFaq.set(faq);
  }

  protected cancelFaq(): void {
    this.editingFaq.set(null);
    this.faqActionError.set(null);
  }

  protected saveFaq(): void {
    const editing = this.editingFaq();
    if (!editing) return;

    this.faqActionError.set(null);
    const payload = {
      question: this.faqForm.question.trim(),
      answer: this.faqForm.answer.trim(),
      category: this.faqForm.category.trim() || null,
      position: Number(this.faqForm.position) || 0,
      is_published: this.faqForm.is_published,
    };

    const request$ =
      editing === 'new' ? this.admin.createFaq(payload) : this.admin.updateFaq(editing.id, payload);

    request$.subscribe({
      next: () => {
        this.editingFaq.set(null);
        this.loadFaqs();
      },
      error: (error: HttpErrorResponse) => this.faqActionError.set(this.messageFor(error)),
    });
  }

  protected deleteFaq(faq: FaqEntry): void {
    this.faqActionError.set(null);
    this.admin.deleteFaq(faq.id).subscribe({
      next: () => this.faqs.update((list) => list.filter((item) => item.id !== faq.id)),
      error: (error: HttpErrorResponse) => this.faqActionError.set(this.messageFor(error)),
    });
  }

  // ===========================================================================
  // Onglet Référentiels — villes
  // ===========================================================================

  protected loadGeography(): void {
    this.geoLoading.set(true);
    this.geoError.set(false);
    this.admin.geography().subscribe({
      next: (regions) => {
        this.regions.set(regions);
        this.geoLoaded.set(true);
        this.geoLoading.set(false);
      },
      error: () => {
        this.geoError.set(true);
        this.geoLoading.set(false);
      },
    });
  }

  protected loadReference(): void {
    this.referenceLoading.set(true);
    this.admin.reference().subscribe({
      next: (catalog) => {
        this.reference.set(catalog);
        this.referenceLoading.set(false);
      },
      error: () => this.referenceLoading.set(false),
    });
  }

  protected toggleRegion(region: GeoRegion): void {
    this.openRegionId.update((current) => (current === region.id ? null : region.id));
  }

  /** Sélectionne un département : c'est ce qui charge la liste des communes. */
  protected selectDepartment(department: GeoDepartment): void {
    this.selectedDepartment.set(department);
    this.editingCommune.set(null);
    this.communeSearch = '';
    this.communesPage.set(1);
    this.loadCommunes();
  }

  protected loadCommunes(): void {
    const department = this.selectedDepartment();
    if (!department) return;

    this.communesLoading.set(true);
    this.communeActionError.set(null);

    this.admin
      .communes({
        department_id: department.id,
        q: this.communeSearch.trim() || undefined,
        page: this.communesPage(),
      })
      .subscribe({
        next: (paginated) => {
          this.communes.set(paginated.data);
          this.communesTotal.set(paginated.meta.total);
          this.communesLastPage.set(paginated.meta.last_page);
          this.communesLoading.set(false);
        },
        error: () => {
          this.communesLoading.set(false);
          this.communeActionError.set('Chargement des communes impossible.');
        },
      });
  }

  protected applyCommuneSearch(): void {
    this.communesPage.set(1);
    this.loadCommunes();
  }

  protected goToCommunes(page: number): void {
    if (page < 1 || page > this.communesLastPage() || page === this.communesPage()) return;
    this.communesPage.set(page);
    this.loadCommunes();
  }

  protected newCommune(): void {
    this.communeActionError.set(null);
    this.communeForm = { name: '', type: '' };
    this.editingCommune.set('new');
  }

  protected editCommune(commune: AdminCommune): void {
    this.communeActionError.set(null);
    this.communeForm = { name: commune.name, type: commune.type ?? '' };
    this.editingCommune.set(commune);
  }

  protected cancelCommune(): void {
    this.editingCommune.set(null);
    this.communeActionError.set(null);
  }

  protected saveCommune(): void {
    const editing = this.editingCommune();
    const department = this.selectedDepartment();
    if (!editing || !department) return;

    this.communeActionError.set(null);
    const payload = {
      name: this.communeForm.name.trim(),
      type: this.communeForm.type.trim() || null,
    };

    const request$ =
      editing === 'new'
        ? this.admin.createCommune({ ...payload, department_id: department.id })
        : this.admin.updateCommune(editing.id, payload);

    request$.subscribe({
      next: () => {
        this.editingCommune.set(null);
        // L'arbre porte des compteurs : on le rafraîchit après une création.
        if (editing === 'new') this.loadGeography();
        this.loadCommunes();
      },
      error: (error: HttpErrorResponse) => this.communeActionError.set(this.messageFor(error)),
    });
  }

  protected deleteCommune(commune: AdminCommune): void {
    this.communeActionError.set(null);
    this.admin.deleteCommune(commune.id).subscribe({
      next: () => {
        this.communes.update((list) => list.filter((item) => item.id !== commune.id));
        this.communesTotal.update((total) => Math.max(0, total - 1));
        this.loadGeography();
      },
      error: (error: HttpErrorResponse) => this.communeActionError.set(this.messageFor(error)),
    });
  }

  // --- Départements -----------------------------------------------------------

  protected newDepartment(region: GeoRegion): void {
    this.departmentActionError.set(null);
    this.departmentForm = { name: '', region_id: region.id };
    this.editingDepartment.set('new');
  }

  protected editDepartment(department: GeoDepartment): void {
    this.departmentActionError.set(null);
    this.departmentForm = { name: department.name, region_id: department.region_id };
    this.editingDepartment.set(department);
  }

  protected cancelDepartment(): void {
    this.editingDepartment.set(null);
    this.departmentActionError.set(null);
  }

  protected saveDepartment(): void {
    const editing = this.editingDepartment();
    if (!editing) return;

    this.departmentActionError.set(null);
    const name = this.departmentForm.name.trim();

    const request$ =
      editing === 'new'
        ? this.admin.createDepartment({ region_id: this.departmentForm.region_id, name })
        : this.admin.updateDepartment(editing.id, { name });

    request$.subscribe({
      next: () => {
        this.editingDepartment.set(null);
        this.loadGeography();
      },
      error: (error: HttpErrorResponse) => this.departmentActionError.set(this.messageFor(error)),
    });
  }

  protected deleteDepartment(department: GeoDepartment): void {
    this.departmentActionError.set(null);
    this.admin.deleteDepartment(department.id).subscribe({
      next: () => {
        if (this.selectedDepartment()?.id === department.id) {
          this.selectedDepartment.set(null);
          this.communes.set([]);
        }
        this.loadGeography();
      },
      error: (error: HttpErrorResponse) => this.departmentActionError.set(this.messageFor(error)),
    });
  }

  // ===========================================================================
  // Barème de construction : aplatissement / reconstruction
  // ===========================================================================

  /**
   * Aplatit le barème imbriqué en une liste de feuilles numériques.
   * `{ price_m2: { extension: 220000 } }` → `price_m2.extension`, section
   * `price_m2`. Les valeurs non numériques sont ignorées (non éditables ici).
   */
  private flattenPricing(value: unknown, prefix = '', section = ''): PricingField[] {
    if (typeof value !== 'object' || value === null) return [];

    const fields: PricingField[] = [];
    for (const [key, child] of Object.entries(value as Record<string, unknown>)) {
      const path = prefix ? `${prefix}.${key}` : key;
      const currentSection = section || key;

      if (typeof child === 'number') {
        fields.push({
          path,
          section: currentSection,
          label: this.pricingLabel(path, currentSection),
          value: child,
        });
      } else if (typeof child === 'object' && child !== null) {
        fields.push(...this.flattenPricing(child, path, currentSection));
      }
    }
    return fields;
  }

  /** Libellé d'une feuille : les segments sous la section, traduits. */
  private pricingLabel(path: string, section: string): string {
    const segments = path.split('.').filter((segment) => segment !== section);
    if (segments.length === 0) return this.pricingSectionLabel(section);
    return segments.map((segment) => this.pricingLabels[segment] ?? segment).join(' · ');
  }

  protected pricingSectionLabel(section: string): string {
    return this.pricingLabels[section] ?? section.replace(/_/g, ' ');
  }

  /** Une valeur du barème a-t-elle changé depuis le chargement ? */
  private pricingChanged(original: unknown): boolean {
    return this.pricingFields().some((field) => this.readPath(original, field.path) !== field.value);
  }

  /** Reconstruit le barème complet en réinjectant les valeurs éditées. */
  private rebuildPricing(original: unknown): unknown {
    const clone = structuredClone(original) as Record<string, unknown>;
    for (const field of this.pricingFields()) this.writePath(clone, field.path, field.value);
    return clone;
  }

  private readPath(source: unknown, path: string): unknown {
    return path
      .split('.')
      .reduce<unknown>(
        (node, segment) =>
          typeof node === 'object' && node !== null
            ? (node as Record<string, unknown>)[segment]
            : undefined,
        source,
      );
  }

  private writePath(target: Record<string, unknown>, path: string, value: number): void {
    const segments = path.split('.');
    const last = segments.pop();
    if (!last) return;

    let node: Record<string, unknown> = target;
    for (const segment of segments) {
      const child = node[segment];
      if (typeof child !== 'object' || child === null) return;
      node = child as Record<string, unknown>;
    }
    node[last] = value;
  }

  // ===========================================================================
  // Présentation
  // ===========================================================================

  protected shortDate(iso: string | null): string {
    if (!iso) return '—';
    return new Date(iso).toLocaleDateString('fr-FR', {
      day: '2-digit',
      month: 'short',
      year: 'numeric',
    });
  }

  private messageFor(error: HttpErrorResponse): string {
    if (error.status === 409) {
      // Garde-fou de suppression du référentiel : le serveur explique combien
      // d'objets retiennent l'élément — on relaie son message tel quel.
      const body = error.error as { message?: string } | null;
      return body?.message ?? 'Suppression impossible : l’élément est encore utilisé.';
    }
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
