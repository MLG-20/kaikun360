<?php

namespace App\Models;

use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Entrée de foire aux questions (contenu éditorial transversal, B13.4).
 */
class Faq extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'question',
        'answer',
        'category',
        'position',
        'is_published',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Entrées publiées, triées pour l'affichage public.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->orderBy('position')->orderBy('id');
    }

    protected static function newFactory(): FaqFactory
    {
        return FaqFactory::new();
    }
}
