<?php

namespace App\Growth;

/**
 * Deterministic VGI scoring. The same rubric version, the same answers and
 * the same evidence always give the same result and the same calculation hash:
 *
 *   question score  = score of the chosen option (0–100); a "don't know"
 *                     (insufficient) option is not scored at all
 *   dimension score = weighted average of its scored questions, or
 *                     "insufficient information" when too few were scored
 *                     (rubric scoring.min_answered_share, default 0.5)
 *   total score     = weighted average of the scored dimensions (weights are
 *                     relative), or none when they carry less than
 *                     scoring.min_scored_weight_share of the total weight
 *   band            = the band whose [min, max] contains the total
 *
 * Every number is rounded to one decimal at the end of each step.
 * Evidence never changes a number; it is reported as evidence adequacy.
 */
class Scorer
{
    /** Results calculated with evidence (version 2) also hash which questions had evidence. */
    public const VERSION = 2;

    /**
     * @param  array<string, string>  $answers  question key => option key
     * @param  array<string, bool>|null  $evidence  question key => evidence given; null = legacy (version 1) calculation
     * @return array{score: ?float, band: array, dimensions: list<array>, gaps: list<array>, unknowns: list<array>, strengths: list<string>, gap_dimensions: list<string>, insufficient_dimensions: list<string>, coverage: array, readiness: array, hash: string, version: int}
     */
    public static function score(array $definition, array $answers, ?array $evidence = null): array
    {
        ksort($answers);
        $rules = FrameworkDefinition::scoring($definition);
        $evidenceGiven = $evidence === null ? null : array_keys(array_filter($evidence));
        $dimensions = [];
        $gaps = [];
        $unknowns = [];
        $weightedTotal = 0.0;
        $scoredWeight = 0.0;
        $allWeight = 0.0;

        foreach ($definition['dimensions'] as $dimension) {
            $sum = 0.0;
            $weights = 0.0;
            $scored = 0;
            $expected = 0;
            $provided = 0;
            $requiredMissing = 0;
            foreach ($dimension['questions'] as $question) {
                $option = self::option($question, $answers[$question['key']] ?? null);
                if ($option === null) {
                    throw new \InvalidArgumentException('Question "'.$question['key'].'" has no valid answer.');
                }
                if (self::isInsufficient($option)) {
                    $unknowns[] = [
                        'dimension_key' => $dimension['key'],
                        'dimension_title' => $dimension['title'],
                        'question_key' => $question['key'],
                        'prompt' => $question['prompt'],
                        'answer' => $option['label'],
                        'tip' => $option['tip'] ?? null,
                    ];

                    continue;
                }
                $weight = (float) ($question['weight'] ?? 1);
                $score = (float) $option['score'];
                $sum += $score * $weight;
                $weights += $weight;
                $scored++;
                if (self::expectsEvidence($question, $option)) {
                    $expected++;
                    $has = $evidenceGiven !== null && in_array($question['key'], $evidenceGiven, true);
                    $provided += $has ? 1 : 0;
                    $requiredMissing += (! $has && ($question['evidence'] ?? 'none') === 'required') ? 1 : 0;
                }
                if ($score < $rules['gap_below']) {
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
            $questionCount = count($dimension['questions']);
            $sufficient = $scored > 0 && ($scored / $questionCount) + 0.0001 >= $rules['min_answered_share'];
            $dimensionScore = $sufficient ? round($sum / $weights, 1) : null;
            $dimensionWeight = (float) $dimension['weight'];
            $dimensions[] = [
                'key' => $dimension['key'],
                'title' => $dimension['title'],
                'weight' => $dimensionWeight,
                'score' => $dimensionScore,
                'status' => $sufficient ? 'scored' : 'insufficient',
                'questions' => $questionCount,
                'scored_questions' => $scored,
                'evidence' => self::adequacy($evidenceGiven === null, $expected, $provided, $requiredMissing),
            ];
            $allWeight += $dimensionWeight;
            if ($sufficient) {
                $weightedTotal += $dimensionScore * $dimensionWeight;
                $scoredWeight += $dimensionWeight;
            }
        }

        $weightShare = $allWeight > 0 ? round($scoredWeight / $allWeight, 3) : 0.0;
        $score = $scoredWeight > 0 && $weightShare + 0.0001 >= $rules['min_scored_weight_share']
            ? round($weightedTotal / $scoredWeight, 1) : null;
        // Weakest areas first, so the growth plan starts where it matters most.
        usort($gaps, fn ($a, $b) => [$a['score'], $a['dimension_key'], $a['question_key']] <=> [$b['score'], $b['dimension_key'], $b['question_key']]);

        $scoredDimensions = array_values(array_filter($dimensions, fn ($d) => $d['score'] !== null));
        $byScoreDesc = $scoredDimensions;
        usort($byScoreDesc, fn ($a, $b) => [$b['score'], $a['key']] <=> [$a['score'], $b['key']]);
        $strengths = array_slice(array_column(array_filter($byScoreDesc, fn ($d) => $d['score'] >= $rules['strength_from']), 'key'), 0, 3);
        $weakFirst = array_reverse($byScoreDesc);
        $gapDimensions = array_values(array_column(array_filter($weakFirst, fn ($d) => $d['score'] < $rules['gap_below']), 'key'));

        $hashInput = FrameworkDefinition::hash($definition).'|'.json_encode($answers);
        if ($evidenceGiven !== null) {
            sort($evidenceGiven);
            $hashInput .= '|'.json_encode($evidenceGiven);
        }

        return [
            'score' => $score,
            'band' => $score === null ? ['label' => null, 'description' => null] : self::band($definition, $score),
            'dimensions' => $dimensions,
            'gaps' => $gaps,
            'unknowns' => $unknowns,
            'strengths' => $strengths,
            'gap_dimensions' => $gapDimensions,
            'insufficient_dimensions' => array_values(array_column(array_filter($dimensions, fn ($d) => $d['score'] === null), 'key')),
            'coverage' => [
                'dimensions' => count($dimensions),
                'scored_dimensions' => count($scoredDimensions),
                'scored_weight_share' => $weightShare,
                'unknown_answers' => count($unknowns),
            ],
            'readiness' => self::readiness($definition, $dimensions),
            'hash' => hash('sha256', $hashInput),
            'version' => $evidenceGiven === null ? 1 : self::VERSION,
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

    public static function isInsufficient(array $option): bool
    {
        return ($option['insufficient'] ?? false) === true;
    }

    /**
     * Whether a question asks for evidence for this answer. Evidence is not
     * expected for "don't know" answers or for answers scored 0 ("none yet").
     */
    public static function expectsEvidence(array $question, array $option): bool
    {
        return ($question['evidence'] ?? 'none') !== 'none' && ! self::isInsufficient($option) && (float) ($option['score'] ?? 0) > 0;
    }

    /**
     * Indicators defined by the rubric's optional "readiness" block. Without
     * one, each indicator is "not_configured" — never guessed.
     *
     * @param  list<array>  $dimensions  scored dimensions from score()
     * @return array<string, array>
     */
    public static function readiness(array $definition, array $dimensions): array
    {
        $byKey = array_column($dimensions, null, 'key');
        $out = [];
        foreach (FrameworkDefinition::READINESS as $key) {
            $config = $definition['readiness'][$key] ?? null;
            $label = ucfirst($key).' readiness';
            if (! is_array($config)) {
                $out[$key] = ['key' => $key, 'label' => $label, 'configured' => false, 'status' => 'not_configured', 'score' => null, 'threshold' => null];

                continue;
            }
            $label = is_string($config['label'] ?? null) ? $config['label'] : $label;
            $sum = 0.0;
            $weights = 0.0;
            $missing = false;
            $belowMinimum = false;
            foreach ($config['dimensions'] as $dimensionKey) {
                $dimension = $byKey[$dimensionKey] ?? null;
                if ($dimension === null || $dimension['score'] === null) {
                    $missing = true;

                    continue;
                }
                $sum += $dimension['score'] * $dimension['weight'];
                $weights += $dimension['weight'];
                if (isset($config['min_each']) && $dimension['score'] < (float) $config['min_each']) {
                    $belowMinimum = true;
                }
            }
            $score = ! $missing && $weights > 0 ? round($sum / $weights, 1) : null;
            $out[$key] = [
                'key' => $key,
                'label' => $label,
                'configured' => true,
                'status' => $score === null ? 'insufficient' : ($score >= (float) $config['threshold'] && ! $belowMinimum ? 'met' : 'not_met'),
                'score' => $score,
                'threshold' => (float) $config['threshold'],
                'min_each' => isset($config['min_each']) ? (float) $config['min_each'] : null,
                'dimensions' => array_values($config['dimensions']),
            ];
        }

        return $out;
    }

    private static function adequacy(bool $unknown, int $expected, int $provided, int $requiredMissing): array
    {
        $status = match (true) {
            $unknown => 'unknown',
            $expected === 0 => 'not_requested',
            $provided === $expected => 'adequate',
            $provided === 0 => 'none',
            default => 'partial',
        };

        return ['status' => $status, 'expected' => $expected, 'provided' => $unknown ? null : $provided, 'required_missing' => $unknown ? null : $requiredMissing];
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
