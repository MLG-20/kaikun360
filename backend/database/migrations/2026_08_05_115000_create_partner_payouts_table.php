<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table `partner_payouts` — le VERSEMENT réellement effectué à un partenaire
 * (F8.16.a). Une ligne = un virement, qui solde une ou plusieurs dettes.
 *
 * ⚠️ **Un lot, pas une ligne par dette.** Un virement par réservation coûterait
 * des frais à chaque nuit vendue. Le lot absorbe donc n'importe quelle cadence —
 * hebdomadaire, mensuelle, à la demande — **sans changer le schéma** : c'est
 * l'agent qui décide quand il regroupe, la base n'impose aucun rythme.
 *
 * ⚠️ **Aucun virement automatique, et c'est délibéré.** Le registre calcule et
 * affiche ; l'agent paie par Wave, Orange Money ou virement, exactement comme il
 * le fait déjà en gestion locative, puis pointe le justificatif. Aucun argent ne
 * bouge sans un clic humain. L'automatisation par l'API de transfert PayTech se
 * branchera derrière, comme un `PaytechProvider` — reste à confirmer avec eux que
 * le produit est activable, à quels frais et avec quel KYC des bénéficiaires.
 *
 * ⚠️ **PayTech reverse à KAIKUN, pas aux partenaires** : le client paie PayTech,
 * PayTech crédite le compte marchand Kaikun. Le reversement est donc une **dette
 * de Kaikun**, pas une fonction du prestataire de paiement. Confusion fréquente
 * qui a longtemps fait croire que le sujet était réglé.
 *
 * ⚠️ **Le justificatif est obligatoire au paiement** — voir `proof_path`. La
 * table `owner_payouts` porte le même champ depuis B4.4 et **rien ne l'a jamais
 * écrit** : aucun endpoint ne téléverse de preuve, alors que l'écran Documents du
 * back-office compte les justificatifs de reversement. On ne refait pas ici la
 * même promesse vide.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_payouts', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();

            $table->foreignId('beneficiary_id')->constrained('users')->cascadeOnDelete();

            // Somme des dettes soldées. Stockée : c'est le montant viré, il ne
            // doit pas bouger si une dette est corrigée plus tard.
            $table->unsignedBigInteger('amount_xof');

            $table->string('status')->default('en_attente')->index();

            // Canal réellement employé (wave, orange_money, virement…) et
            // référence de l'opération chez l'opérateur, telle que l'agent la lit
            // sur son reçu.
            $table->string('method')->nullable();
            $table->string('external_reference')->nullable();

            $table->timestamp('paid_at')->nullable();
            // Justificatif sur DISQUE PRIVÉ (jamais public : une preuve de
            // virement porte des coordonnées). Servi par URL signée.
            $table->string('proof_path')->nullable();
            $table->string('proof_disk')->nullable();
            $table->string('proof_original_name')->nullable();

            $table->text('note')->nullable();

            // Qui a préparé, puis qui a payé : une action financière se rattache
            // toujours à une personne (CDC §12, journalisation des actions
            // sensibles).
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['beneficiary_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_payouts');
    }
};
