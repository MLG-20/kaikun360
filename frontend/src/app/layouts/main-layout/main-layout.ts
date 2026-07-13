import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { FooterComponent } from '../../shared/components/footer/footer';
import { HeaderComponent } from '../../shared/components/header/header';

/**
 * Layout principal du site public (F1.1) : en-tête global + contenu routé + pied
 * de page. Toutes les pages « site » (accueil, catalogues, fiches…) sont rendues
 * dans son `router-outlet`. Les pages d'authentification utilisent un autre
 * layout, sans ce décor.
 */
@Component({
  selector: 'app-main-layout',
  imports: [RouterOutlet, HeaderComponent, FooterComponent],
  templateUrl: './main-layout.html',
  styleUrl: './main-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MainLayoutComponent {}
