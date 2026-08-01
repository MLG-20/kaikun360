import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { map } from 'rxjs/operators';

/**
 * Page de retour après un passage chez PayTech (F8.6) — `/paiement/succes` et
 * `/paiement/annule`, déclarées comme `success_url` / `cancel_url`.
 *
 * ⚠️ **Cette page ne prouve RIEN et ne déclenche rien.** Elle est atteinte par
 * une simple redirection du navigateur : n'importe qui peut en taper l'adresse.
 * Seul l'IPN signé, reçu de serveur à serveur, fait passer une réservation à
 * « confirmée ». D'où la formulation, qui n'affirme jamais que le paiement est
 * acquis : « nous avons bien reçu votre retour », « la confirmation arrive ».
 * Annoncer « paiement confirmé » ici serait un mensonge qu'un client pourrait
 * fabriquer lui-même en modifiant l'URL.
 *
 * ⚠️ **Publique, hors espace client, et c'est délibéré.** Le client revient d'un
 * autre domaine, éventuellement après un long moment ou depuis un autre onglet :
 * une garde de rôle le renverrait vers la page de connexion au pire moment —
 * juste après avoir payé. Elle ne montre donc aucune donnée de réservation, rien
 * que la référence déjà présente dans l'URL.
 *
 * L'issue (succès / annulation) vient des `data` de la route, pas de l'URL : une
 * route par cas, et aucun paramètre à interpréter.
 */
@Component({
  selector: 'app-payment-return-page',
  imports: [RouterLink],
  templateUrl: './payment-return-page.html',
  styleUrl: './payment-return-page.scss',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaymentReturnPageComponent {
  private readonly route = inject(ActivatedRoute);

  /** `true` sur `/paiement/succes`, `false` sur `/paiement/annule`. */
  protected readonly succeeded = toSignal(
    this.route.data.pipe(map((data) => data['succeeded'] === true)),
    { initialValue: true },
  );

  /**
   * Référence du règlement, ajoutée à l'URL de retour par le serveur.
   *
   * Purement informative : elle aide le client à en parler au support. On
   * n'interroge rien avec, et son absence n'est pas une anomalie.
   */
  private readonly params = toSignal(this.route.queryParamMap, { initialValue: null });

  protected readonly reference = computed(() => this.params()?.get('ref') ?? null);
}
