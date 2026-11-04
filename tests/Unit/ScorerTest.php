<?php

namespace Tests\Unit;

use App\Growth\FrameworkDefinition;
use App\Growth\Scorer;
use PHPUnit\Framework\TestCase;

class ScorerTest extends TestCase
{
    private function definition(): array
    {
        $options = fn () => [
            ['key' => 'low', 'label' => 'Low', 'score' => 20, 'tip' => 'Do better'],
            ['key' => 'mid', 'label' => 'Mid', 'score' => 60],
            ['key' => 'high', 'label' => 'High', 'score' => 100],
        ];

        return [
            'dimensions' => [
                ['key' => 'team', 'title' => 'Team', 'weight' => 1, 'questions' => [
                    ['key' => 't1', 'prompt' => 'Q1', 'weight' => 1, 'options' => $options()],
                    ['key' => 't2', 'prompt' => 'Q2', 'weight' => 3, 'options' => $options()],
                ]],
                ['key' => 'money', 'title' => 'Money', 'weight' => 3, 'questions' => [
                    ['key' => 'm1', 'prompt' => 'Q3', 'weight' => 1, 'options' => $options()],
                ]],
            ],
            'bands' => [
                ['min' => 0, 'max' => 49.9, 'label' => 'Early'],
                ['min' => 50, 'max' => 100, 'label' => 'Later'],
            ],
        ];
    }

    public function test_weighted_scores_match_a_hand_calculation(): void
    {
        // Team = (20*1 + 100*3) / 4 = 80.0; Money = 60.0; Total = (80*1 + 60*3) / 4 = 65.0
        $result = Scorer::score($this->definition(), ['t1' => 'low', 't2' => 'high', 'm1' => 'mid']);

        $this->assertSame(65.0, $result['score']);
        $this->assertSame('Later', $result['band']['label']);
        $this->assertSame([80.0, 60.0], array_column($result['dimensions'], 'score'));
        $this->assertSame(['t1'], array_column($result['gaps'], 'question_key'));
        $this->assertSame('Do better', $result['gaps'][0]['tip']);
    }

    public function test_results_are_deterministic_and_answer_order_does_not_matter(): void
    {
        $a = Scorer::score($this->definition(), ['t1' => 'low', 't2' => 'mid', 'm1' => 'high']);
        $b = Scorer::score($this->definition(), ['m1' => 'high', 't2' => 'mid', 't1' => 'low']);
        $c = Scorer::score($this->definition(), ['t1' => 'low', 't2' => 'mid', 'm1' => 'mid']);

        $this->assertSame($a, $b);
        $this->assertNotSame($a['hash'], $c['hash']);
    }

    public function test_band_boundaries(): void
    {
        $definition = $this->definition();
        $this->assertSame('Early', Scorer::band($definition, 49.9)['label']);
        $this->assertSame('Later', Scorer::band($definition, 50.0)['label']);
        $this->assertSame('Later', Scorer::band($definition, 100.0)['label']);
    }

    public function test_missing_or_unknown_answers_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Scorer::score($this->definition(), ['t1' => 'low', 't2' => 'nope', 'm1' => 'mid']);
    }

    public function test_validator_explains_problems(): void
    {
        $this->assertSame([], FrameworkDefinition::validate($this->definition()));

        $broken = $this->definition();
        $broken['dimensions'][1]['questions'][0]['key'] = 't1';
        $broken['dimensions'][0]['questions'][0]['options'][0]['score'] = 150;
        $broken['bands'][1]['min'] = 60;
        $errors = implode(' | ', FrameworkDefinition::validate($broken));
        $this->assertStringContainsString('used twice', $errors);
        $this->assertStringContainsString('score from 0 to 100', $errors);
        $this->assertStringContainsString('without gaps', $errors);
        $this->assertNotSame([], FrameworkDefinition::validate('not an object'));
    }

    public function test_the_seeded_starter_rubric_is_valid(): void
    {
        $definition = require __DIR__.'/../../database/data/vgi_starter_draft.php';
        $this->assertSame([], FrameworkDefinition::validate($definition));
    }
}
