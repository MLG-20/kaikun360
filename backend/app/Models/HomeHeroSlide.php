<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Une photo du diaporama de fond du héros de l'accueil (F15.1).
 */
class HomeHeroSlide extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = ['image_path', 'position', 'updated_by'];

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }
}
