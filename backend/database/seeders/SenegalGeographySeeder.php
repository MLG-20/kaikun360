<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Region;
use Illuminate\Database\Seeder;

/**
 * Référentiel géographique officiel du Sénégal : 14 régions, 46 départements.
 *
 * Idempotent (firstOrCreate) : peut être rejoué sans créer de doublons.
 * Source : découpage administratif du Sénégal (régions / départements).
 */
class SenegalGeographySeeder extends Seeder
{
    /**
     * Régions et leurs départements.
     *
     * @var array<string, array<int, string>>
     */
    private const GEOGRAPHIE = [
        'Dakar' => ['Dakar', 'Guédiawaye', 'Pikine', 'Rufisque', 'Keur Massar'],
        'Thiès' => ['Thiès', 'Mbour', 'Tivaouane'],
        'Diourbel' => ['Diourbel', 'Bambey', 'Mbacké'],
        'Fatick' => ['Fatick', 'Foundiougne', 'Gossas'],
        'Kaolack' => ['Kaolack', 'Guinguinéo', 'Nioro du Rip'],
        'Kaffrine' => ['Kaffrine', 'Birkelane', 'Koungheul', 'Malem-Hodar'],
        'Kédougou' => ['Kédougou', 'Salémata', 'Saraya'],
        'Kolda' => ['Kolda', 'Médina Yoro Foulah', 'Vélingara'],
        'Louga' => ['Louga', 'Kébémer', 'Linguère'],
        'Matam' => ['Matam', 'Kanel', 'Ranérou Ferlo'],
        'Saint-Louis' => ['Saint-Louis', 'Dagana', 'Podor'],
        'Sédhiou' => ['Sédhiou', 'Bounkiling', 'Goudomp'],
        'Tambacounda' => ['Tambacounda', 'Bakel', 'Goudiry', 'Koumpentoum'],
        'Ziguinchor' => ['Ziguinchor', 'Bignona', 'Oussouye'],
    ];

    public function run(): void
    {
        foreach (self::GEOGRAPHIE as $regionName => $departments) {
            $region = Region::firstOrCreate(['name' => $regionName]);

            foreach ($departments as $departmentName) {
                Department::firstOrCreate([
                    'region_id' => $region->id,
                    'name' => $departmentName,
                ]);
            }
        }
    }
}
