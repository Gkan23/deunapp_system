<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(CatalogSeeder::class);

        if (app()->environment('local')) {
            $this->call(DemoUserSeeder::class);
        }

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoDataSeeder::class);
        }
    }
}