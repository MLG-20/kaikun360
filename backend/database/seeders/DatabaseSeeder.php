<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Point d'entrée du peuplement de la base.
     *
     * Pour l'instant on n'amorce que les rôles & permissions (indispensables au
     * fonctionnement de l'auth). Les jeux de données de démonstration viendront
     * dans des seeders dédiés, phase par phase.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
        ]);
    }
}
