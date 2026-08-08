import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { AssistantPanelComponent } from '../../shared/components/assistant/assistant-panel';
import { FooterComponent } from '../../shared/components/footer/footer';
import { HeaderComponent } from '../../shared/components/header/header';

/**
 * Layout principal du site public (F1.1) : en-tête global + contenu routé + pied
 * de page. Toutes les pages « site » (accueil, catalogues, fiches…) sont rendues
 * dans son `router-outlet`. Les pages d'authentification utilisent un autre
 * layout, sans ce décor.
 *
 * S'y ajoute depuis F10.1 la bulle de l'**assistant Kaikun** : elle est montée
 * ici (et dans `space-layout`) plutôt que dans la racine applicative, ce qui la
 * tient hors du back-office et hors du parcours d'authentification.
 */
@Component({
  selector: 'app-main-layout',
  imports: [RouterOutlet, HeaderComponent, FooterComponent, AssistantPanelComponent],
  templateUrl: './main-layout.html',
  styleUrl: './main-layout.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class MainLayoutComponent {}
