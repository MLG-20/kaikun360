import { ChangeDetectionStrategy, Component, ElementRef, effect, forwardRef, input, signal, viewChild } from '@angular/core';
import { ControlValueAccessor, FormsModule, NG_VALUE_ACCESSOR } from '@angular/forms';

import { formatRichText, sanitizeRichText } from './rich-text.sanitizer';

/** Un bouton de la barre d'outils (mise en forme de caractère ou de bloc). */
interface ToolbarButton {
  /** Identifiant de la commande d'édition. */
  readonly command: string;
  /** Valeur associée (nom de bloc pour `formatBlock`). */
  readonly value?: string;
  /** Symbole affiché sur le bouton. */
  readonly icon: string;
  /** Infobulle et libellé accessible. */
  readonly label: string;
}

/**
 * Éditeur de texte enrichi (F8.3).
 *
 * **Pourquoi ce composant existe.** Le corps d'une page de contenu et la
 * réponse d'une entrée de FAQ sont stockés en HTML et rendus sur le site public
 * via `[innerHTML]`. Or le back-office n'offrait qu'un `<textarea>` : pour
 * écrire les mentions légales, un agent devait taper `<h2>`, `<p>`,
 * `<ul><li>` à la main. En pratique, deux issues, toutes deux mauvaises — il
 * appelle un développeur pour changer une virgule, ou il tape du texte brut qui
 * arrive en ligne d'un seul bloc sur le site.
 *
 * L'éditeur remplace la saisie de balises par des boutons, sans rien changer au
 * **format stocké** : c'est le même HTML qu'avant, donc les pages déjà en base
 * s'ouvrent sans conversion et le rendu public n'a pas bougé.
 *
 * **Trois partis pris :**
 *
 *   - **aucune dépendance**. Une bibliothèque d'édition apporte son propre
 *     format de document et ~150 Ko au premier chargement du site, pour six
 *     boutons ; `contenteditable` et `execCommand` sont vieux mais universels ;
 *   - **liste blanche stricte** ([`rich-text.sanitizer.ts`](./rich-text.sanitizer.ts)) :
 *     ce qui est collé depuis Word est nettoyé à l'entrée, pas seulement au
 *     rendu. Sans cela la page publique hérite des styles du traitement de texte ;
 *   - **la vue « code HTML » reste accessible.** Elle ne sert plus à écrire,
 *     mais elle permet de vérifier ou de rattraper un contenu abîmé — retirer
 *     l'échappatoire aurait été une régression pour qui sait s'en servir.
 *
 * S'utilise comme un champ de formulaire ordinaire : il implémente
 * `ControlValueAccessor`, donc `[(ngModel)]="…"` fonctionne tel quel.
 */
@Component({
  selector: 'app-rich-text-editor',
  imports: [FormsModule],
  templateUrl: './rich-text-editor.html',
  styleUrl: './rich-text-editor.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
  providers: [
    {
      provide: NG_VALUE_ACCESSOR,
      useExisting: forwardRef(() => RichTextEditorComponent),
      multi: true,
    },
  ],
})
export class RichTextEditorComponent implements ControlValueAccessor {
  /** Texte d'invite affiché tant que le contenu est vide. */
  readonly placeholder = input('Rédigez le contenu…');

  /** Libellé accessible : la zone d'édition n'est pas un `<input>` étiquetable. */
  readonly ariaLabel = input('Contenu');

  /** Hauteur minimale de la zone de saisie, en lignes de texte. */
  readonly rows = input(12);

  /** Zone d'édition (`contenteditable`). */
  private readonly surface = viewChild<ElementRef<HTMLElement>>('surface');

  /** Champ d'adresse du lien, pour lui rendre le focus à l'ouverture. */
  private readonly linkInput = viewChild<ElementRef<HTMLInputElement>>('linkInput');

  /** Valeur assainie connue du formulaire (source de vérité hors saisie). */
  protected readonly html = signal('');

  /** Le contenu est-il vide ? Pilote l'affichage du texte d'invite. */
  protected readonly empty = signal(true);

  /** Commandes actuellement actives sous le curseur (gras, liste…). */
  protected readonly active = signal<ReadonlySet<string>>(new Set<string>());

  /** Vue « code HTML » dépliée. */
  protected readonly sourceOpen = signal(false);

  /** Contenu de la vue « code HTML » pendant son édition. */
  protected readonly source = signal('');

  /** Barre d'insertion de lien ouverte. */
  protected readonly linkOpen = signal(false);

  /** Adresse saisie dans la barre de lien. */
  protected linkUrl = '';

  /** Champ désactivé par le formulaire hôte. */
  protected readonly disabled = signal(false);

  /** Le curseur est-il dans la zone d'édition ? (bloque la réécriture du DOM) */
  private focused = false;

  /**
   * Sélection mémorisée avant l'ouverture de la barre de lien.
   *
   * Cliquer dans le champ d'adresse fait perdre la sélection du texte : sans
   * cette copie, le lien s'appliquerait à rien.
   */
  private savedRange: Range | null = null;

  private onChange: (value: string) => void = () => {};
  private onTouched: () => void = () => {};

  /** Mise en forme de caractère. */
  protected readonly inlineTools: readonly ToolbarButton[] = [
    { command: 'bold', icon: 'G', label: 'Gras' },
    { command: 'italic', icon: 'I', label: 'Italique' },
    { command: 'underline', icon: 'S', label: 'Souligné' },
  ];

  /** Mise en forme de bloc. */
  protected readonly blockTools: readonly ToolbarButton[] = [
    { command: 'formatBlock', value: 'p', icon: '¶', label: 'Paragraphe' },
    { command: 'formatBlock', value: 'h2', icon: 'T1', label: 'Titre' },
    { command: 'formatBlock', value: 'h3', icon: 'T2', label: 'Sous-titre' },
    { command: 'formatBlock', value: 'blockquote', icon: '❝', label: 'Citation' },
  ];

  /** Listes. */
  protected readonly listTools: readonly ToolbarButton[] = [
    { command: 'insertUnorderedList', icon: '•', label: 'Liste à puces' },
    { command: 'insertOrderedList', icon: '1.', label: 'Liste numérotée' },
  ];

  constructor() {
    // Synchronise le DOM éditable avec la valeur du formulaire — jamais pendant
    // la frappe, sous peine de renvoyer le curseur en début de zone à chaque
    // caractère.
    effect(() => {
      const element = this.surface()?.nativeElement;
      const value = this.html();
      if (!element || this.focused) {
        return;
      }
      if (element.innerHTML !== value) {
        element.innerHTML = value;
      }
      this.empty.set(!element.textContent?.trim());
    });
  }

  // --- ControlValueAccessor -------------------------------------------------

  writeValue(value: string | null): void {
    this.html.set(sanitizeRichText(value ?? ''));
  }

  registerOnChange(fn: (value: string) => void): void {
    this.onChange = fn;
  }

  registerOnTouched(fn: () => void): void {
    this.onTouched = fn;
  }

  setDisabledState(isDisabled: boolean): void {
    this.disabled.set(isDisabled);
  }

  // --- Saisie ---------------------------------------------------------------

  /**
   * Frappe au clavier : on remonte la valeur brute au formulaire.
   *
   * L'assainissement complet est reporté à la sortie du champ : reconstruire
   * l'arbre à chaque touche déplacerait le curseur.
   */
  protected onInput(): void {
    const element = this.surface()?.nativeElement;
    if (!element) {
      return;
    }
    this.empty.set(!element.textContent?.trim());
    this.onChange(element.innerHTML);
  }

  protected onFocus(): void {
    this.focused = true;
    // `execCommand` produit des `<span style>` quand ce réglage est actif ;
    // on veut des balises sémantiques (`<strong>`, `<em>`).
    this.tryExec('styleWithCSS', 'false');
    this.refreshActive();
  }

  /** Sortie du champ : c'est ici que le contenu est nettoyé et normalisé. */
  protected onBlur(): void {
    const element = this.surface()?.nativeElement;
    this.focused = false;
    if (element) {
      const clean = sanitizeRichText(element.innerHTML);
      this.html.set(clean);
      this.onChange(clean);
    }
    this.onTouched();
  }

  /**
   * Collé depuis un traitement de texte : on nettoie avant l'insertion.
   *
   * C'est le cas d'usage réel — les mentions légales arrivent d'un Word envoyé
   * par le juriste, pas d'une frappe dans le navigateur.
   */
  protected onPaste(event: ClipboardEvent): void {
    const clipboard = event.clipboardData;
    if (!clipboard) {
      return;
    }
    event.preventDefault();

    const html = clipboard.getData('text/html');
    if (html) {
      this.tryExec('insertHTML', sanitizeRichText(html));
    } else {
      // Texte brut : chaque ligne devient un paragraphe, sinon tout se colle
      // en un seul bloc — le défaut qu'on cherche à supprimer.
      const paragraphs = clipboard
        .getData('text/plain')
        .split(/\r?\n\s*\r?\n|\r?\n/)
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => `<p>${line.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' })[c]!)}</p>`)
        .join('');
      this.tryExec('insertHTML', paragraphs);
    }
    this.onInput();
  }

  /** Met à jour l'état des boutons après un déplacement du curseur. */
  protected refreshActive(): void {
    if (typeof document === 'undefined') {
      return;
    }
    const state = new Set<string>();
    for (const tool of [...this.inlineTools, ...this.listTools]) {
      try {
        if (document.queryCommandState(tool.command)) {
          state.add(tool.command);
        }
      } catch {
        // Commande non supportée : le bouton reste simplement inactif.
      }
    }
    try {
      const block = (document.queryCommandValue('formatBlock') || '').toLowerCase();
      if (block) {
        state.add(`formatBlock:${block}`);
      }
    } catch {
      // idem
    }
    this.active.set(state);
  }

  protected isActive(tool: ToolbarButton): boolean {
    const key = tool.value ? `${tool.command}:${tool.value}` : tool.command;
    return this.active().has(key);
  }

  // --- Barre d'outils -------------------------------------------------------

  /** Applique une commande de mise en forme à la sélection courante. */
  protected apply(tool: ToolbarButton): void {
    const element = this.surface()?.nativeElement;
    if (!element || this.disabled()) {
      return;
    }
    element.focus();
    // `formatBlock` attend le nom de balise entre chevrons sur certains
    // navigateurs, et l'accepte partout sous cette forme.
    this.tryExec(tool.command, tool.value ? `<${tool.value}>` : undefined);
    this.onInput();
    this.refreshActive();
  }

  /** Retire toute mise en forme de la sélection (y compris les liens). */
  protected clearFormat(): void {
    const element = this.surface()?.nativeElement;
    if (!element || this.disabled()) {
      return;
    }
    element.focus();
    this.tryExec('removeFormat');
    this.tryExec('unlink');
    this.onInput();
    this.refreshActive();
  }

  /** Ouvre la barre d'adresse, en mémorisant la sélection à lier. */
  protected openLink(): void {
    if (this.disabled() || typeof window === 'undefined') {
      return;
    }
    const selection = window.getSelection();
    this.savedRange = selection && selection.rangeCount > 0 ? selection.getRangeAt(0).cloneRange() : null;
    this.linkUrl = this.currentHref() ?? 'https://';
    this.linkOpen.set(true);
    queueMicrotask(() => this.linkInput()?.nativeElement.focus());
  }

  /** Adresse du lien sous le curseur, s'il y en a un (mode « modifier »). */
  private currentHref(): string | null {
    const node = this.savedRange?.startContainer;
    const element = node?.nodeType === Node.ELEMENT_NODE ? (node as Element) : (node?.parentElement ?? null);
    return element?.closest('a')?.getAttribute('href') ?? null;
  }

  /** Pose le lien sur la sélection mémorisée. */
  protected applyLink(): void {
    const element = this.surface()?.nativeElement;
    const url = this.linkUrl.trim();
    this.linkOpen.set(false);

    if (!element || !url || url === 'https://') {
      return;
    }

    element.focus();
    const selection = typeof window !== 'undefined' ? window.getSelection() : null;
    if (selection && this.savedRange) {
      selection.removeAllRanges();
      selection.addRange(this.savedRange);
    }

    if (selection?.isCollapsed) {
      // Aucun texte sélectionné : on insère l'adresse comme libellé, plutôt que
      // de ne rien faire — l'agent verrait un bouton sans effet.
      this.tryExec('insertHTML', `<a href="${url.replace(/"/g, '&quot;')}">${url}</a>`);
    } else {
      this.tryExec('createLink', url);
    }

    this.savedRange = null;
    this.onInput();
  }

  protected cancelLink(): void {
    this.linkOpen.set(false);
    this.savedRange = null;
  }

  // --- Vue « code HTML » ----------------------------------------------------

  /** Bascule entre l'édition visuelle et l'édition du balisage. */
  protected toggleSource(): void {
    if (this.sourceOpen()) {
      const clean = sanitizeRichText(this.source());
      this.html.set(clean);
      this.onChange(clean);
      this.sourceOpen.set(false);
      return;
    }
    const element = this.surface()?.nativeElement;
    this.source.set(formatRichText(element?.innerHTML ?? this.html()));
    this.sourceOpen.set(true);
  }

  /**
   * Frappe dans la vue « code HTML ».
   *
   * On remonte la valeur assainie au formulaire à chaque touche : sans cela,
   * enregistrer sans avoir refermé la vue perdrait la saisie. Le texte affiché
   * dans le champ, lui, n'est pas réécrit — l'assainissement au vol couperait
   * une balise en cours de frappe sous les doigts de l'agent.
   */
  protected onSourceInput(value: string): void {
    this.source.set(value);
    this.onChange(sanitizeRichText(value));
  }

  /**
   * Exécute une commande d'édition en tolérant son absence.
   *
   * `execCommand` est déprécié : aucun navigateur ne l'a retiré, mais on ne
   * veut pas qu'un jour la page entière tombe pour un bouton indisponible.
   */
  private tryExec(command: string, value?: string): void {
    if (typeof document === 'undefined') {
      return;
    }
    try {
      document.execCommand(command, false, value);
    } catch {
      // Commande refusée par le navigateur : la saisie reste possible.
    }
  }
}
