<?php

namespace Tests\Feature;

use App\Models\Growth\VgiFramework;
use App\Support\Features;
use Database\Seeders\BuilderCurriculumSeeder;
use Database\Seeders\FaqSeeder;
use Database\Seeders\StarterContentSeeder;
use Database\Seeders\VgiStarterSeeder;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Opens every admin page as a super admin, with every module switched on and the starter data
 * loaded, and fails on any server error. Pages that were never opened by a test (missing views,
 * data the view did not expect) have caused 500s on live before.
 */
class AdminSmokeTest extends TestCase
{
    public function test_every_admin_page_opens_without_a_server_error(): void
    {
        foreach (array_keys(Features::definitions()) as $key) {
            Features::set($key, true, null);
        }
        $this->seed([StarterContentSeeder::class, VgiStarterSeeder::class, BuilderCurriculumSeeder::class, FaqSeeder::class]);
        $admin = $this->userWithRole('super_admin');

        $urls = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true) && str_starts_with($route->uri(), 'admin') && ! str_contains($route->uri(), '{'))
            ->map(fn ($route) => '/'.$route->uri())
            ->merge(['/admin/content/faqs', '/admin/content/testimonials', '/admin/content/partners'])
            ->merge(VgiFramework::query()->pluck('id')->map(fn ($id) => route('admin.vgi.edit', $id, false)))
            ->unique()->values();

        $failures = [];
        foreach ($urls as $url) {
            $status = $this->actingAs($admin)->get($url)->getStatusCode();
            if ($status >= 500) {
                $failures[] = "$url returned $status";
            }
        }

        $this->assertGreaterThan(30, $urls->count());
        $this->assertSame([], $failures);
    }
}
