<?php

namespace Tests\Feature\Integration;

use App\Models\Integration\ApiClient;
use App\Models\Registry\StartupAlias;

class CommandsTest extends IntegrationTestCase
{
    public function test_api_client_command_creates_a_client_and_prints_secrets_once(): void
    {
        $this->artisan('nsp:api-client', ['client_id' => 'ims-staging', '--name' => 'IMS staging',
            '--redirect-uri' => ['https://staging.ims.test/nsp-callback'], '--webhook-url' => 'https://staging.ims.test/nsp-webhook'])
            ->expectsOutputToContain('NSP_CLIENT_SECRET=')
            ->assertSuccessful();

        $client = ApiClient::query()->where('client_id', 'ims-staging')->sole();
        $this->assertSame(['https://staging.ims.test/nsp-callback'], $client->redirect_uris);
        $this->assertNotSame('', $client->secret_hash);
        $this->artisan('nsp:api-client', ['client_id' => 'ims-staging', '--webhook-url' => 'ftp://bad'])->assertFailed();
        $this->artisan('nsp:api-client', ['client_id' => 'ims-staging', '--disable' => true])->assertSuccessful();
        $this->assertFalse($client->fresh()->is_active);
    }

    public function test_import_dry_run_changes_nothing_and_apply_links_only_strong_matches(): void
    {
        $strong = $this->startupWithConsent(core: ['legal_name' => 'Kampala Bikes Ltd', 'display_name' => 'Kampala Bikes', 'registration_number' => 'UG-500']);
        $this->startupWithConsent(core: ['legal_name' => 'Sunny Foods', 'display_name' => 'Sunny Foods']);
        $csv = tempnam(sys_get_temp_dir(), 'ims').'.csv';
        file_put_contents($csv, "application_id,startup_name,registration_number,website\n"
            ."101,KB Mobility,ug 500,\n"
            ."102,Sunny Foods,,\n"
            ."103,Brand New Co,,\n");

        $this->artisan('registry:import-ims', ['csv' => $csv])->expectsOutputToContain('DRY RUN')->assertSuccessful();
        $this->assertSame(0, StartupAlias::query()->where('source_system', 'ims')->count());

        $this->artisan('registry:import-ims', ['csv' => $csv, '--apply' => true])->expectsOutputToContain('link=1 already_linked=0 conflict=0 review=1 no_match=1')->assertSuccessful();
        $this->assertSame($strong->uuid, StartupAlias::query()->where('source_system', 'ims')->sole()->startup_uuid);
        @unlink($csv);
    }
}
