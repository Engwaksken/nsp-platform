<?php

namespace Tests\Feature\Knowledge;

use App\Knowledge\Recommender;
use App\Knowledge\VgiSignals;
use App\Models\User;
use App\Registry\ActiveStartup;
use App\Registry\RegistryService;
use Tests\TestCase;

class KnowledgeRecommenderTest extends TestCase
{
    public function test_vgi_recommendation_context_uses_only_the_selected_startup(): void
    {
        $user = User::factory()->withRole('startup_user')->create();
        $registry = app(RegistryService::class);
        $first = $registry->createStartup($user, [
            'legal_name' => 'First Venture Ltd', 'display_name' => 'First Venture', 'registration_number' => 'UG-FIRST',
            'country' => 'UG', 'year_founded' => 2024, 'description' => null, 'website' => null,
        ], []);
        $second = $registry->createStartup($user, [
            'legal_name' => 'Second Venture Ltd', 'display_name' => 'Second Venture', 'registration_number' => 'UG-SECOND',
            'country' => 'UG', 'year_founded' => 2023, 'description' => null, 'website' => null,
        ], []);

        $signals = $this->mock(VgiSignals::class);
        $signals->shouldReceive('for')->once()->withArgs(fn ($startup) => $startup->is($second))
            ->andReturn(['weakest' => ['team'], 'stage' => 'validation']);
        ActiveStartup::switchTo($second);

        $context = app(Recommender::class)->context($user);

        $this->assertSame(['founders'], $context['audiences']);
        $this->assertSame(['team'], $context['weakest']);
        $this->assertSame('validation', $context['stage']);
        $this->assertNotSame($first->uuid, $second->uuid);
    }
}
