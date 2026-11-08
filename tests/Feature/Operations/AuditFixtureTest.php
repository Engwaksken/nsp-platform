<?php

namespace Tests\Feature\Operations;

use App\Models\Registry\Startup;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AuditFixtureTest extends TestCase
{
    public function test_fixture_covers_every_module_lists_pages_and_cleans_up_only_its_own_data(): void
    {
        Storage::fake('local');
        Mail::fake();
        $bystander = User::factory()->withRole('startup_user')->create();

        $this->artisan('nsp:audit-fixture', ['--password' => 'Audit-Pass-12345'])->assertSuccessful();
        $pages = json_decode((string) file_get_contents(base_path('tests/a11y/pages.json')), true);
        $this->assertGreaterThan(10, count($pages['public']));
        $this->assertGreaterThan(50, count($pages['signedIn']));
        $this->assertSame(2, Startup::query()->where('registration_number', 'like', 'A11Y-%')->count());
        $this->actingAs(User::query()->where('email', 'admin@a11y.nsp.test')->firstOrFail());
        foreach (['/investors', '/experts', '/talent', '/marketplace/messages', $pages['signedIn'][6]] as $url) {
            $this->get($url)->assertOk();
        }
        auth()->logout();

        $this->artisan('nsp:audit-fixture', ['--clean' => true])->assertSuccessful();
        $this->assertSame(0, User::query()->where('email', 'like', '%a11y.nsp.test')->count());
        $this->assertSame(0, Startup::query()->where('registration_number', 'like', 'A11Y-%')->count());
        $this->assertFileDoesNotExist(base_path('tests/a11y/pages.json'));
        $this->assertNotNull($bystander->fresh(), 'Other data is untouched.');
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('nsp:audit-fixture')->expectsOutputToContain('only runs in local or testing')->assertFailed();
        $this->assertSame(0, User::query()->where('email', 'like', '%a11y.nsp.test')->count());
    }
}
