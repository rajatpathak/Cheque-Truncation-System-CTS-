<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CTSRolesSeeder::class,
            CTSParametersSeeder::class,
            CTSReturnReasonsSeeder::class,
        ]);
    }
}
