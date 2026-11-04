<?php

namespace App\Console\Commands;

use App\Models\Integration\ApiClient;
use App\Support\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ManageApiClient extends Command
{
    protected $signature = 'nsp:api-client
        {client_id : Short identifier, e.g. ims-live}
        {--name= : Display name, e.g. "Hive IMS"}
        {--recipient=ims : Which system it is (decides the consent purpose)}
        {--redirect-uri=* : Allowed connect redirect URIs (exact)}
        {--webhook-url= : Where NSP sends events (https)}
        {--scopes=registry.read,registry.link,events.write : Comma-separated scopes}
        {--rotate : Issue new secrets for an existing client}
        {--disable : Turn the client off}';

    protected $description = 'Create, update, rotate or disable a connected system (API client). Secrets are printed once.';

    public function handle(): int
    {
        $clientId = (string) $this->argument('client_id');
        if (! preg_match('/^[a-z0-9][a-z0-9-]{1,58}$/', $clientId)) {
            $this->error('Use lower-case letters, numbers and hyphens for the client ID.');

            return self::FAILURE;
        }
        $client = ApiClient::query()->firstOrNew(['client_id' => $clientId]);
        $isNew = ! $client->exists;

        if ($this->option('disable')) {
            if ($isNew) {
                $this->error('No such client.');

                return self::FAILURE;
            }
            $client->forceFill(['is_active' => false])->save();
            Cache::forget('nsp.connect_origins');
            Audit::log('integration.client_disabled', 'api_clients', ['client_id' => $clientId]);
            $this->info("{$clientId} disabled.");

            return self::SUCCESS;
        }

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('scopes')))));
        if (array_diff($scopes, ApiClient::SCOPES)) {
            $this->error('Unknown scope. Allowed: '.implode(', ', ApiClient::SCOPES));

            return self::FAILURE;
        }
        $redirects = array_values(array_filter((array) $this->option('redirect-uri')));
        $webhook = $this->option('webhook-url');
        foreach (array_merge($redirects, array_filter([$webhook])) as $url) {
            $secure = str_starts_with($url, 'https://') || (app()->environment(['local', 'testing']) && str_starts_with($url, 'http://'));
            if (! filter_var($url, FILTER_VALIDATE_URL) || ! $secure) {
                $this->error("Not an allowed URL (https required): {$url}");

                return self::FAILURE;
            }
        }

        $client->fill([
            'name' => $this->option('name') ?: ($client->name ?? $clientId),
            'recipient_system' => (string) $this->option('recipient'),
            'scopes' => $scopes,
            'is_active' => true,
        ]);
        if ($redirects) {
            $client->redirect_uris = $redirects;
        }
        if ($webhook !== null) {
            $client->webhook_url = $webhook ?: null;
        }
        if (! $client->consentPurpose()) {
            $this->error('No data-sharing purpose exists for recipient "'.$client->recipient_system.'". Check config/nsp.php.');

            return self::FAILURE;
        }

        if ($isNew || $this->option('rotate')) {
            if ($isNew) {
                $client->secret_hash = '';
                $client->webhook_secret = '';
            }
            $client->save();
            $secrets = $client->rotateSecrets();
            $this->warn('Copy these now. They will not be shown again.');
            $this->line('NSP_CLIENT_ID='.$client->client_id);
            $this->line('NSP_CLIENT_SECRET='.$secrets['client_secret']);
            $this->line('NSP_WEBHOOK_SECRET='.$secrets['webhook_secret']);
        } else {
            $client->save();
        }
        Cache::forget('nsp.connect_origins');
        Audit::log($isNew ? 'integration.client_created' : 'integration.client_updated', 'api_clients', ['client_id' => $clientId, 'rotated' => (bool) $this->option('rotate')]);
        $this->info(($isNew ? 'Created ' : 'Updated ').$clientId.'.');

        return self::SUCCESS;
    }
}
