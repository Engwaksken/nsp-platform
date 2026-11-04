<?php

namespace App\Growth;

/**
 * Deterministic VGI scoring. The same rubric version and the same answers
 * always give the same result and the same calculation hash:
 *
 *   question score  = score of the chosen option (0–100)
 *   dimension score = weighted average of its question scores
 *   total score     = weighted average of dimension scores (weights are relative)
 *   band            = the band whose [min, max] contains the total
 *
 * Every number is rounded to one decimal at the end of each step.
 */
class Scorer
{
    /**
     * @param  array<string, string>  $answers  question key => option key
     * @return array{score: float, band: array, dimensions: list<array>, gaps: list<array>, hash: string}
     */
    public static function score(array $definition, array $answers): array
    {
        ksort($answers);
        $dimensions = [];
        $gaps = [];
        $weightedTotal = 0.0;
        $totalWeight = 0.0;

        foreach ($definition['dimensions'] as $dimension) {
            $sum = 0.0;
            $weights = 0.0;
            foreach ($dimension['questions'] as $question) {
                $weight = (float) ($question['weight'] ?? 1);
                $option = self::option($question, $answers[$question['key']] ?? null);
                if ($option === null) {
                    throw new \InvalidArgumentException('Question "'.$question['key'].'" has no valid answer.');
                }
                $score = (float) $option['score'];
                $sum += $score * $weight;
                $weights += $weight;
                if ($score < 50) {
                    $gaps[] = [
                        'dimension_key' => $dimension['key'],
                        'dimension_title' => $dimension['title'],
                        'question_key' => $question['key'],
                        'prompt' => $question['prompt'],
                        'answer' => $option['label'],
                        'score' => round($score, 1),
                        'tip' => $option['tip'] ?? null,
                    ];
                }
            }
            $dimensionScore = round($sum / $weights, 1);
            $dimensions[] = [
                'key' => $dimension['key'],
                'title' => $dimension['title'],
                'weight' => (float) $dimension['weight'],
                'score' => $dimensionScore,
            ];
            $weightedTotal += $dimensionScore * (float) $dimension['weight'];
            $totalWeight += (float) $dimension['weight'];
        }

        $score = round($weightedTotal / $totalWeight, 1);
        // Weakest areas first, so the growth plan starts where it matters most.
        usort($gaps, fn ($a, $b) => [$a['score'], $a['dimension_key'], $a['question_key']] <=> [$b['score'], $b['dimension_key'], $b['question_key']]);

        return [
            'score' => $score,
            'band' => self::band($definition, $score),
            'dimensions' => $dimensions,
            'gaps' => $gaps,
            'hash' => hash('sha256', FrameworkDefinition::hash($definition).'|'.json_encode($answers)),
        ];
    }

    public static function band(array $definition, float $score): array
    {
        foreach ($definition['bands'] as $band) {
            if ($score >= (float) $band['min'] && $score <= (float) $band['max'] + 0.0001) {
                return ['label' => $band['label'], 'description' => $band['description'] ?? null];
            }
        }

        return ['label' => 'Unbanded', 'description' => null];
    }

    private static function option(array $question, ?string $key): ?array
    {
        foreach ($question['options'] as $option) {
            if ($option['key'] === $key) {
                return $option;
            }
        }

        return null;
    }
}
