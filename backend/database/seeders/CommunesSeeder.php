<?php

namespace Database\Seeders;

use App\Models\Commune;
use App\Models\Department;
use App\Models\Region;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Importe les communes depuis database/data/communes.json.
 *
 * Format attendu : { "Région": { "Département": ["Commune", ...] } }.
 * Idempotent (firstOrCreate). Doit s'exécuter APRÈS SenegalGeographySeeder
 * (les régions et départements doivent déjà exister).
 *
 * Ce fichier est volontairement "branché sur un fichier" pour pouvoir y verser
 * la liste officielle complète des ~557 communes (source ANSD) sans toucher au code.
 */
class CommunesSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('data/communes.json');

        if (! File::exists($path)) {
            $this->command?->warn("communes.json introuvable ({$path}) — import des communes ignoré.");

            return;
        }

        $data = json_decode(File::get($path), true);

        foreach ($data as $regionName => $departments) {
            // On ignore les éventuelles clés de commentaire (ex. "_comment").
            if (! is_array($departments)) {
                continue;
            }

            $region = Region::where('name', $regionName)->first();
            if (! $region) {
                continue;
            }

            foreach ($departments as $departmentName => $communes) {
                $department = Department::where('region_id', $region->id)
                    ->where('name', $departmentName)
                    ->first();

                if (! $department) {
                    continue;
                }

                foreach ($communes as $communeName) {
                    Commune::firstOrCreate([
                        'department_id' => $department->id,
                        'name' => $communeName,
                    ]);
                }
            }
        }
    }
}
