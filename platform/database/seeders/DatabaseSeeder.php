<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UniverseSeeder::class,
            CircleSeeder::class,
            CategorySeeder::class,
            DemoContentSeeder::class,
            // After DemoContentSeeder — the course is taught by a persona it creates.
            CourseSeeder::class,
            // Last: it marks passages as the readers the demo seeder created.
            FlagshipArticleSeeder::class,
        ]);
    }
}
