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
 *       "options": [{"key": "a", "label": "...", "score": 0, "tip": "What to do next"}]
 *     }]
 *   }],
 *   "bands": [{"min": 0, "max": 24.9, "label": "Idea", "description": "..."}]
 * }
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
                $optionKeys = [];
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
                    if (! is_numeric($option['score'] ?? null) || $option['score'] < 0 || $option['score'] > 100) {
                        $errors[] = "$oWhere needs a score from 0 to 100.";
                    }
                }
            }
        }

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
