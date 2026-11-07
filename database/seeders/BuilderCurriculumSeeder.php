<?php

namespace Database\Seeders;

use App\Builder\CurriculumService;
use Illuminate\Database\Seeder;

/** Seeds only the provisional curriculum draft; it never publishes it. */
class BuilderCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        app(CurriculumService::class)->seedProvisional();
    }
}
