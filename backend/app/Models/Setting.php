<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Paramètre global de la plateforme (couche transversale, B13.4).
 *
 * Une ligne = une surcharge back-office d'une clé de configuration. La valeur
 * est stockée en texte et retypée par le {@see \App\Support\SettingsRepository}
 * selon la colonne `type`. On n'interagit quasi jamais avec ce modèle
 * directement : passer par la façade {@see \App\Support\Settings}.
 */
class Setting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'updated_by',
    ];
}
