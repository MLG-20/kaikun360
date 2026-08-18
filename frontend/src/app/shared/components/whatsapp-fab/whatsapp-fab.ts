import { ChangeDetectionStrategy, Component, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { of } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

import { WhatsAppService } from '../../../core/api/whatsapp.service';

/**
 * Bulle flottante WhatsApp, coin bas-droite, empilée sous `app-assistant-launcher`
 * (voir `app-floating-dock`).
 *
 * Générique (aucun `subject`/`reference`) — contrairement à `app-whatsapp-button`
 * (F2.6), posé au fil des fiches avec le contexte de la page. Les deux partagent
 * le même backend (`GET /whatsapp/link`) : masquée si aucun numéro de support
 * n'est paramétré, un bouton mort valant moins que pas de bouton.
 */
@Component({
  selector: 'app-whatsapp-fab',
  templateUrl: './whatsapp-fab.html',
  styleUrl: './whatsapp-fab.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WhatsappFabComponent {
  private readonly whatsapp = inject(WhatsAppService);

  protected readonly url = toSignal(
    this.whatsapp.link().pipe(
      map((env) => (env.data.phone ? env.data.url : null)),
      catchError(() => of(null)),
    ),
    { initialValue: null as string | null },
  );
}
