import { ChangeDetectionStrategy, Component, computed, inject, input } from '@angular/core';
import { toObservable, toSignal } from '@angular/core/rxjs-interop';
import { of } from 'rxjs';
import { catchError, map, switchMap } from 'rxjs/operators';

import { WhatsAppService } from '../../../core/api/whatsapp.service';

/**
 * Bouton WhatsApp contextuel (F2.6).
 *
 * Où qu'il soit posé (fiche d'un bien, page de conversion…), il ouvre une
 * conversation WhatsApp vers le support avec un message DÉJÀ prérempli selon le
 * contexte fourni (`subject` = de quoi il s'agit, `reference` = un numéro à
 * rappeler). Le lien et le numéro proviennent du backend (`GET /whatsapp/link`,
 * B16.3) — jamais codés en dur ici.
 *
 * Comportement :
 * - au montage (et si `subject`/`reference` changent), on demande le lien au
 *   backend ; le bouton reste masqué tant que le lien n'est pas prêt OU si aucun
 *   numéro de support n'est paramétré (on n'affiche pas un bouton mort) ;
 * - une fois prêt, on affiche un simple lien stylé qui ouvre WhatsApp dans un
 *   nouvel onglet (`rel="noopener"` pour la sécurité).
 */
@Component({
  selector: 'app-whatsapp-button',
  templateUrl: './whatsapp-button.html',
  styleUrl: './whatsapp-button.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class WhatsAppButtonComponent {
  private readonly whatsapp = inject(WhatsAppService);

  /** Sujet lisible du message prérempli (ex. titre du bien consulté). */
  readonly subject = input<string | null>(null);

  /** Référence éventuelle à rappeler dans le message (ex. réf. d'une demande). */
  readonly reference = input<string | null>(null);

  /** Libellé du bouton (personnalisable selon la page). */
  readonly label = input('Discuter sur WhatsApp');

  /** Regroupe les entrées pour déclencher un nouvel appel à chaque changement. */
  private readonly context = computed(() => ({
    subject: this.subject(),
    reference: this.reference(),
  }));

  /**
   * URL wa.me résolue par le backend (ou null : en cours de chargement, en
   * échec, ou aucun numéro de support paramétré). `switchMap` annule un appel
   * précédent si le contexte change en cours de route.
   */
  protected readonly url = toSignal(
    toObservable(this.context).pipe(
      switchMap(({ subject, reference }) =>
        this.whatsapp.link(subject, reference).pipe(
          // On n'affiche le bouton que si un numéro de support existe réellement.
          map((env) => (env.data.phone ? env.data.url : null)),
          catchError(() => of(null)),
        ),
      ),
    ),
    { initialValue: null as string | null },
  );
}
