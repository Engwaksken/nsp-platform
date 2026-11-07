<?php

namespace Tests\Feature\Knowledge;

use App\Models\Knowledge\KnowledgeDownload;
use App\Models\Knowledge\KnowledgeResource;
use App\Models\User;
use App\Support\Features;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KnowledgeDownloadLicensingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Features::set('knowledge', true, null);
    }

    private function resource(string $slug, string $visibility = 'public', array $extra = []): KnowledgeResource
    {
        $resource = KnowledgeResource::query()->create(array_merge([
            'slug' => $slug, 'title' => 'Research resource '.$slug, 'summary' => 'Published reference.',
            'resource_type' => 'research_publication', 'license' => 'cc0', 'visibility' => $visibility,
            'external_url' => 'https://research.example.org/report',
        ], $extra));
        $resource->forceFill(['status' => 'published', 'published_at' => now(), 'current_version' => 3])->save();

        return $resource;
    }

    private function editorFields(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Uploaded research source', 'slug' => 'uploaded-research-source',
            'summary' => 'Research summary.', 'resource_type' => 'research_publication',
            'license' => 'cc-by', 'visibility' => 'public', 'source' => 'Research office',
        ], $overrides);
    }

    public function test_link_only_licence_requires_source_and_rejects_hosted_files(): void
    {
        Storage::fake('local');
        $editor = User::factory()->withRole('content_editor')->create();

        $this->actingAs($editor)->from(route('admin.knowledge.create'))
            ->post(route('admin.knowledge.store'), $this->editorFields([
                'license' => 'all-rights-reserved-linked-only',
                'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
            ]))->assertSessionHasErrors('external_url');

        $this->actingAs($editor)->from(route('admin.knowledge.create'))
            ->post(route('admin.knowledge.store'), $this->editorFields([
                'license' => 'all-rights-reserved-linked-only',
                'external_url' => 'https://publisher.example.org/report',
                'file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'),
            ]))->assertSessionHasErrors('file');

        $this->assertDatabaseCount('knowledge_resources', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unsupported_upload_is_rejected_and_leaves_no_file(): void
    {
        Storage::fake('local');
        $editor = User::factory()->withRole('content_editor')->create();

        $this->actingAs($editor)->from(route('admin.knowledge.create'))
            ->post(route('admin.knowledge.store'), $this->editorFields([
                'file' => UploadedFile::fake()->create('payload.exe', 10, 'application/x-msdownload'),
            ]))->assertSessionHasErrors('file');

        $this->assertDatabaseCount('knowledge_resources', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_link_download_is_audited_with_version_and_private_resource_access_is_enforced(): void
    {
        $resource = $this->resource('public-link');
        $this->get(route('knowledge.download', $resource))->assertRedirect('https://research.example.org/report');
        $download = KnowledgeDownload::query()->sole();
        $this->assertSame('link', $download->kind);
        $this->assertSame(3, $download->version);
        $this->assertNotNull($download->ip_hash);
        $this->assertSame(1, $resource->fresh()->download_count);

        $staffOnly = $this->resource('staff-only-link', 'staff');
        $this->get(route('knowledge.download', $staffOnly))->assertNotFound();
        $this->assertSame(1, KnowledgeDownload::query()->count());
    }
}
