import { Directive, ElementRef, Renderer2, afterNextRender, inject } from '@angular/core';

/** Icône « œil ouvert » (afficher) — SVG statique de confiance. */
const EYE = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg>`;

/** Icône « œil barré » (masquer). */
const EYE_OFF = `<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.9 4.24A9.12 9.12 0 0 1 12 4c6.5 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"/><path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3.5 7 10 7a9.74 9.74 0 0 0 5.39-1.61"/><line x1="2" y1="2" x2="22" y2="22"/></svg>`;

/**
 * Ajoute un bouton « œil » à un champ mot de passe pour afficher/masquer sa
 * saisie (F1, retour utilisateur : pouvoir vérifier ce qu'on a tapé).
 *
 * Usage : ajouter l'attribut `appPasswordReveal` sur un `<input type="password">`.
 * La directive enveloppe le champ, glisse le bouton à droite et bascule le type
 * `password`/`text` au clic. Elle ne touche pas à la valeur : elle fonctionne donc
 * telle quelle avec les formulaires réactifs (`formControlName`).
 *
 * ⚠️ **Compatibilité SSR / hydratation** : le remodelage du DOM (envelopper le
 * champ dans un `<span>`, insérer le bouton) est fait dans **`afterNextRender`**,
 * qui ne s'exécute **que dans le navigateur, APRÈS l'hydratation**. Le serveur
 * rend l'`<input>` nu, le client l'hydrate à l'identique, PUIS on l'améliore.
 * Le faire dans `ngOnInit` (qui tourne aussi côté serveur) plaçait un `<span>`
 * là où le template attend un `<input>` → **mismatch d'hydratation `NG0500`**
 * qui cassait le formulaire de connexion/inscription au 1er rendu SSR.
 */
@Directive({
  selector: 'input[appPasswordReveal]',
})
export class PasswordRevealDirective {
  private readonly host: ElementRef<HTMLInputElement> = inject(ElementRef);
  private readonly renderer = inject(Renderer2);
  private revealed = false;

  constructor() {
    // Navigateur uniquement, après l'hydratation : aucune manipulation du DOM
    // pendant le rendu serveur ni pendant la réconciliation d'hydratation.
    afterNextRender(() => this.enhance());
  }

  /** Enveloppe le champ et ajoute le bouton œil (post-hydratation, côté client). */
  private enhance(): void {
    const input = this.host.nativeElement;
    const parent = this.renderer.parentNode(input);

    // On enveloppe le champ dans un conteneur positionné, pour y ancrer l'œil.
    // Le nœud <input> est DÉPLACÉ (pas recréé) : sa liaison de formulaire reste
    // intacte.
    const wrap = this.renderer.createElement('span');
    this.renderer.addClass(wrap, 'k-input-wrap');
    this.renderer.insertBefore(parent, wrap, input);
    this.renderer.appendChild(wrap, input);
    this.renderer.addClass(input, 'k-input--with-eye');

    // Le bouton œil (type=button pour ne pas soumettre le formulaire).
    const button = this.renderer.createElement('button');
    this.renderer.setAttribute(button, 'type', 'button');
    this.renderer.addClass(button, 'k-input-eye');
    this.render(button, input);
    this.renderer.listen(button, 'click', () => {
      this.revealed = !this.revealed;
      this.render(button, input);
    });
    this.renderer.appendChild(wrap, button);
  }

  /** Met le type du champ et l'icône/le libellé du bouton en cohérence. */
  private render(button: HTMLElement, input: HTMLInputElement): void {
    this.renderer.setProperty(input, 'type', this.revealed ? 'text' : 'password');
    this.renderer.setProperty(button, 'innerHTML', this.revealed ? EYE_OFF : EYE);
    this.renderer.setAttribute(
      button,
      'aria-label',
      this.revealed ? 'Masquer le mot de passe' : 'Afficher le mot de passe',
    );
  }
}
