<?php

namespace App\Growth;

/**
 * Shape and rules of a VGI rubric (stored as JSON on vgi_frameworks.definition):
 *
 * {
 *   "dimensions": [{
 *     "key": "team", "title": "Team", "description": "...", "weight": 20,
 *     "questions": [{
 *       "key": "team_roles", "prompt": "...", "help": "...", "weight": 1, "evidence": "none|optional|required",
 *       "evidence_hint": "What counts as evidence (optional)",
 *       "options": [{"key": "a", "label": "...", "score": 0, "tip": "What to do next"},
 *                   {"key": "unknown", "label": "We don't know yet", "insufficient": true, "tip": "How to find out"}]
 *     }],
 *     "anchors": {"0": "What a low score looks like", "100": "What a strong score looks like"}   (optional)
 *   }],
 *   "bands": [{"min": 0, "max": 24.9, "label": "Idea", "description": "..."}],
 *   "scoring": {"min_answered_share": 0.5, "min_scored_weight_share": 0.5, "gap_below": 50, "strength_from": 70}   (optional)
 *   "readiness": {"investment": {"label": "...", "dimensions": ["..."], "threshold": 70, "min_each": 50}}   (optional)
 * }
 *
 * An option marked "insufficient" (for example "We don't know yet") is never
 * scored. Readiness indicators exist only when a rubric defines them.
 */
class FrameworkDefinition
{
    private const KEY = '/^[a-z][a-z0-9_]{1,59}$/';

    /** @return list<string> problems in plain language; empty means valid */
    public static function validate(mixed $definition): array
    {
        if (! is_array($definition)) {
            return ['The rubric must be a JSON object with "dimensions" and "bands".'];
        }
        $errors = [];
        $dimensions = $definition['dimensions'] ?? null;
        if (! is_array($dimensions) || $dimensions === []) {
            $errors[] = 'Add at least one dimension.';
            $dimensions = [];
        }
        $dimensionKeys = [];
        $questionKeys = [];
        foreach (array_values($dimensions) as $d => $dimension) {
            $where = 'Dimension '.($d + 1);
            if (! is_array($dimension)) {
                $errors[] = "$where is not an object.";

                continue;
            }
            $key = $dimension['key'] ?? null;
            if (! is_string($key) || ! preg_match(self::KEY, $key)) {
                $errors[] = "$where needs a key of lower-case letters, numbers and underscores.";
            } elseif (in_array($key, $dimensionKeys, true)) {
                $errors[] = "Dimension key \"$key\" is used twice.";
            } else {
                $dimensionKeys[] = $key;
                $where = "Dimension \"$key\"";
            }
            if (! is_string($dimension['title'] ?? null) || trim($dimension['title']) === '') {
                $errors[] = "$where needs a title.";
            }
            if (! is_numeric($dimension['weight'] ?? null) || $dimension['weight'] <= 0) {
                $errors[] = "$where needs a weight above 0.";
            }
            $questions = $dimension['questions'] ?? null;
            if (! is_array($questions) || $questions === []) {
                $errors[] = "$where needs at least one question.";

                continue;
            }
            foreach (array_values($questions) as $q => $question) {
                $qWhere = "$where, question ".($q + 1);
                if (! is_array($question)) {
                    $errors[] = "$qWhere is not an object.";

                    continue;
                }
                $qKey = $question['key'] ?? null;
                if (! is_string($qKey) || ! preg_match(self::KEY, $qKey)) {
                    $errors[] = "$qWhere needs a key of lower-case letters, numbers and underscores.";
                } elseif (in_array($qKey, $questionKeys, true)) {
                    $errors[] = "Question key \"$qKey\" is used twice.";
                } else {
                    $questionKeys[] = $qKey;
                    $qWhere = "Question \"$qKey\"";
                }
                if (! is_string($question['prompt'] ?? null) || trim($question['prompt']) === '') {
                    $errors[] = "$qWhere needs a prompt.";
                }
                if (! is_numeric($question['weight'] ?? 1) || ($question['weight'] ?? 1) <= 0) {
                    $errors[] = "$qWhere needs a weight above 0.";
                }
                if (! in_array($question['evidence'] ?? 'none', ['none', 'optional', 'required'], true)) {
                    $errors[] = "$qWhere: evidence must be none, optional or required.";
                }
                $options = $question['options'] ?? null;
                if (! is_array($options) || count($options) < 2 || count($options) > 7) {
                    $errors[] = "$qWhere needs between 2 and 7 answer options.";

                    continue;
                }
                if (array_key_exists('evidence_hint', $question) && ! is_string($question['evidence_hint'])) {
                    $errors[] = "$qWhere: evidence_hint must be text.";
                }
                $optionKeys = [];
                $insufficient = 0;
                foreach (array_values($options) as $o => $option) {
                    $oWhere = "$qWhere, option ".($o + 1);
                    if (! is_array($option) || ! is_string($option['key'] ?? null) || ! preg_match('/^[a-z0-9_]{1,30}$/', $option['key'])) {
                        $errors[] = "$oWhere needs a short key.";

                        continue;
                    }
                    if (in_array($option['key'], $optionKeys, true)) {
                        $errors[] = "$qWhere uses option key \"{$option['key']}\" twice.";
                    }
                    $optionKeys[] = $option['key'];
                    if (! is_string($option['label'] ?? null) || trim($option['label']) === '') {
                        $errors[] = "$oWhere needs a label.";
                    }
                    if (($option['insufficient'] ?? false) === true) {
                        // "We don't know yet": not scored, so it needs no score.
                        $insufficient++;

                        continue;
                    }
                    if (array_key_exists('insufficient', $option) && ! is_bool($option['insufficient'])) {
                        $errors[] = "$oWhere: insufficient must be true or false.";
                    }
                    if (! is_numeric($option['score'] ?? null) || $option['score'] < 0 || $option['score'] > 100) {
                        $errors[] = "$oWhere needs a score from 0 to 100.";
                    }
                }
                if ($insufficient > 1) {
                    $errors[] = "$qWhere can have only one \"don't know\" (insufficient) option.";
                }
                if ($insufficient >= count($options)) {
                    $errors[] = "$qWhere needs at least one scored option.";
                }
            }
            if (array_key_exists('anchors', $dimension)) {
                $anchors = $dimension['anchors'];
                $valid = is_array($anchors) && $anchors !== [];
                foreach ($valid ? $anchors : [] as $at => $text) {
                    if (! is_numeric($at) || $at < 0 || $at > 100 || ! is_string($text) || trim($text) === '') {
                        $valid = false;
                    }
                }
                if (! $valid) {
                    $errors[] = "$where: anchors must map scores 0–100 to a short description (for example {\"0\": \"...\", \"100\": \"...\"}).";
                }
            }
        }

        $errors = array_merge($errors, self::validateScoring($definition['scoring'] ?? null), self::validateReadiness($definition['readiness'] ?? null, $dimensionKeys));

        $bands = $definition['bands'] ?? null;
        if (! is_array($bands) || $bands === []) {
            $errors[] = 'Add score bands that cover 0 to 100.';
        } else {
            $sorted = array_values($bands);
            usort($sorted, fn ($a, $b) => ($a['min'] ?? 0) <=> ($b['min'] ?? 0));
            $expected = 0.0;
            foreach ($sorted as $band) {
                if (! is_numeric($band['min'] ?? null) || ! is_numeric($band['max'] ?? null) || ! is_string($band['label'] ?? null) || trim($band['label']) === '') {
                    $errors[] = 'Every band needs min, max and a label.';
                    break;
                }
                if (abs((float) $band['min'] - $expected) > 0.0001) {
                    $errors[] = 'Bands must follow each other without gaps or overlaps, starting at 0 (problem near '.$band['label'].').';
                    break;
                }
                if ((float) $band['max'] < (float) $band['min']) {
                    $errors[] = "Band \"{$band['label']}\" ends before it starts.";
                    break;
                }
                $expected = round((float) $band['max'] + 0.1, 1);
            }
            if (! $errors && abs($expected - 100.1) > 0.0001) {
                $errors[] = 'The last band must end at 100. Bands step by 0.1 (for example 0–24.9, 25–49.9, …, 75–100).';
            }
        }

        return $errors;
    }

    /** Scoring rules of a rubric, with defaults for rubrics written before they existed. */
    public static function scoring(array $definition): array
    {
        $scoring = is_array($definition['scoring'] ?? null) ? $definition['scoring'] : [];

        return [
            'min_answered_share' => (float) ($scoring['min_answered_share'] ?? self::DEFAULT_SCORING['min_answered_share']),
            'min_scored_weight_share' => (float) ($scoring['min_scored_weight_share'] ?? self::DEFAULT_SCORING['min_scored_weight_share']),
            'gap_below' => (float) ($scoring['gap_below'] ?? self::DEFAULT_SCORING['gap_below']),
            'strength_from' => (float) ($scoring['strength_from'] ?? self::DEFAULT_SCORING['strength_from']),
        ];
    }

    public const DEFAULT_SCORING = [
        // A dimension needs at least this share of its questions answered with a scored option.
        'min_answered_share' => 0.5,
        // The overall score needs scored dimensions carrying at least this share of the total weight.
        'min_scored_weight_share' => 0.5,
        // Answers and dimensions below this are gaps; dimensions at or above strength_from are strengths.
        'gap_below' => 50,
        'strength_from' => 70,
    ];

    /** @return list<string> */
    private static function validateScoring(mixed $scoring): array
    {
        if ($scoring === null) {
            return [];
        }
        if (! is_array($scoring)) {
            return ['"scoring" must be an object.'];
        }
        $errors = [];
        foreach (['min_answered_share', 'min_scored_weight_share'] as $share) {
            if (array_key_exists($share, $scoring) && (! is_numeric($scoring[$share]) || $scoring[$share] <= 0 || $scoring[$share] > 1)) {
                $errors[] = "scoring.$share must be a share above 0 and at most 1 (for example 0.5).";
            }
        }
        foreach (['gap_below', 'strength_from'] as $limit) {
            if (array_key_exists($limit, $scoring) && (! is_numeric($scoring[$limit]) || $scoring[$limit] < 0 || $scoring[$limit] > 100)) {
                $errors[] = "scoring.$limit must be from 0 to 100.";
            }
        }

        return $errors;
    }

    /**
     * Optional readiness indicators. Only a rubric that defines them gets them:
     * {"investment": {"label": "...", "dimensions": ["..."], "threshold": 70, "min_each": 50}, "commercialization": {...}}
     *
     * @param  list<string>  $dimensionKeys
     * @return list<string>
     */
    private static function validateReadiness(mixed $readiness, array $dimensionKeys): array
    {
        if ($readiness === null) {
            return [];
        }
        if (! is_array($readiness)) {
            return ['"readiness" must be an object with "investment" and/or "commercialization".'];
        }
        $errors = [];
        foreach ($readiness as $key => $indicator) {
            $where = "Readiness \"$key\"";
            if (! in_array($key, self::READINESS, true)) {
                $errors[] = "$where is not supported (use investment or commercialization).";

                continue;
            }
            if (! is_array($indicator)) {
                $errors[] = "$where must be an object.";

                continue;
            }
            $dims = $indicator['dimensions'] ?? null;
            if (! is_array($dims) || $dims === [] || array_diff($dims, $dimensionKeys) !== []) {
                $errors[] = "$where needs a list of existing dimension keys.";
            }
            if (! is_numeric($indicator['threshold'] ?? null) || $indicator['threshold'] < 0 || $indicator['threshold'] > 100) {
                $errors[] = "$where needs a threshold from 0 to 100.";
            }
            if (array_key_exists('min_each', $indicator) && (! is_numeric($indicator['min_each']) || $indicator['min_each'] < 0 || $indicator['min_each'] > 100)) {
                $errors[] = "$where: min_each must be from 0 to 100.";
            }
        }

        return $errors;
    }

    public const READINESS = ['investment', 'commercialization'];

    public static function hash(array $definition): string
    {
        return hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, array> question key => question (with its dimension key) */
    public static function questions(array $definition): array
    {
        $questions = [];
        foreach ($definition['dimensions'] as $dimension) {
            foreach ($dimension['questions'] as $question) {
                $questions[$question['key']] = $question + ['dimension_key' => $dimension['key']];
            }
        }

        return $questions;
    }
}
