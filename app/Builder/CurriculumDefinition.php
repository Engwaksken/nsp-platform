<?php

namespace App\Builder;

use App\Models\Builder\BuilderCurriculum;
use App\Models\Builder\BuilderModule;
use App\Models\Builder\BuilderStepDefinition;

/**
 * The curriculum as a plain array (used by the seed file and the admin JSON editor),
 * its validation rules, and conversion from the database rows.
 *
 * Structure rules apply whenever a draft is saved. The 6 modules / 24 steps rule
 * applies when a version is published.
 */
class CurriculumDefinition
{
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{1,79}$/';

    public const BUILT_IN_PLACEHOLDERS = ['startup_name', 'step_title', 'module_title', 'date'];

    /** @return list<string> problems that block publishing (empty = publishable) */
    public static function publishProblems(array $definition): array
    {
        $problems = self::structureProblems($definition);
        $activeModules = array_values(array_filter($definition['modules'] ?? [], fn ($m) => is_array($m) && ($m['active'] ?? true)));
        $activeSteps = 0;
        foreach ($activeModules as $module) {
            $count = count(array_filter($module['steps'] ?? [], fn ($s) => is_array($s) && ($s['active'] ?? true)));
            if ($count === 0) {
                $problems[] = 'Module "'.($module['title'] ?? $module['key'] ?? '?').'" has no active steps.';
            }
            $activeSteps += $count;
        }
        $requiredModules = (int) config('builder.modules', 6);
        $requiredSteps = (int) config('builder.steps', 24);
        if (count($activeModules) !== $requiredModules) {
            $problems[] = "A published curriculum needs exactly {$requiredModules} active modules; this one has ".count($activeModules).'.';
        }
        if ($activeSteps !== $requiredSteps) {
            $problems[] = "A published curriculum needs exactly {$requiredSteps} active steps; this one has {$activeSteps}.";
        }

        return $problems;
    }

    /** @return list<string> problems with the structure (keys, fields, prerequisites, VGI keys …) */
    public static function structureProblems(array $definition): array
    {
        $problems = [];
        $modules = $definition['modules'] ?? null;
        if (! is_array($modules) || ! array_is_list($modules) || $modules === []) {
            return ['"modules" must be a non-empty list.'];
        }
        $vgiKeys = array_keys(config('builder.vgi_dimensions', []));
        $fieldTypes = config('builder.field_types', []);
        $evidenceValues = config('builder.evidence_requirements', []);
        $moduleKeys = [];
        $seenSteps = []; // key => active, in sequence order

        foreach ($modules as $m => $module) {
            $label = 'Module '.($m + 1);
            if (! is_array($module)) {
                $problems[] = "$label must be an object.";

                continue;
            }
            $key = $module['key'] ?? null;
            if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key)) {
                $problems[] = "$label: key must be lowercase letters, digits and underscores (2–80, starting with a letter).";
            } elseif (isset($moduleKeys[$key])) {
                $problems[] = "$label: module key \"$key\" is used twice.";
            }
            $moduleKeys[(string) $key] = true;
            if (! self::text($module['title'] ?? null, 255)) {
                $problems[] = "$label: title is required (max 255 characters).";
            }
            if (isset($module['outputs']) && ! self::stringList($module['outputs'])) {
                $problems[] = "$label: outputs must be a list of text.";
            }
            if (isset($module['active']) && ! is_bool($module['active'])) {
                $problems[] = "$label: active must be true or false.";
            }
            $moduleActive = ($module['active'] ?? true) === true;
            $steps = $module['steps'] ?? null;
            if (! is_array($steps) || ! array_is_list($steps)) {
                $problems[] = "$label: steps must be a list.";

                continue;
            }
            foreach ($steps as $s => $step) {
                $stepLabel = $label.', step '.($s + 1);
                if (! is_array($step)) {
                    $problems[] = "$stepLabel must be an object.";

                    continue;
                }
                array_push($problems, ...self::stepProblems($step, $stepLabel, $seenSteps, $vgiKeys, $fieldTypes, $evidenceValues));
                if (is_string($step['key'] ?? null)) {
                    $seenSteps[$step['key']] = $moduleActive && ($step['active'] ?? true) === true;
                }
            }
        }

        return $problems;
    }

    /** @param array<string, bool> $earlier step keys that come before this one (key => active) */
    private static function stepProblems(array $step, string $label, array $earlier, array $vgiKeys, array $fieldTypes, array $evidenceValues): array
    {
        $problems = [];
        $key = $step['key'] ?? null;
        if (! is_string($key) || ! preg_match(self::KEY_PATTERN, $key)) {
            $problems[] = "$label: key must be lowercase letters, digits and underscores (2–80, starting with a letter).";
        } elseif (array_key_exists($key, $earlier)) {
            $problems[] = "$label: step key \"$key\" is used twice.";
        }
        $label .= is_string($key) ? " ($key)" : '';
        if (! self::text($step['title'] ?? null, 255)) {
            $problems[] = "$label: title is required (max 255 characters).";
        }
        if (isset($step['guidance']) && ! is_string($step['guidance'])) {
            $problems[] = "$label: guidance must be text (Markdown).";
        }

        $fields = $step['fields'] ?? null;
        $fieldKeys = [];
        if (! is_array($fields) || ! array_is_list($fields) || $fields === []) {
            $problems[] = "$label: fields must be a non-empty list.";
        } else {
            foreach ($fields as $f => $field) {
                $fieldLabel = "$label, field ".($f + 1);
                if (! is_array($field)) {
                    $problems[] = "$fieldLabel must be an object.";

                    continue;
                }
                $fieldKey = $field['key'] ?? null;
                if (! is_string($fieldKey) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $fieldKey)) {
                    $problems[] = "$fieldLabel: key must be lowercase letters, digits and underscores.";
                } elseif (isset($fieldKeys[$fieldKey]) || in_array($fieldKey, self::BUILT_IN_PLACEHOLDERS, true)) {
                    $problems[] = "$fieldLabel: key \"$fieldKey\" is used twice or is reserved.";
                } else {
                    $fieldKeys[$fieldKey] = true;
                }
                if (! self::text($field['label'] ?? null, 255)) {
                    $problems[] = "$fieldLabel: label is required.";
                }
                $type = $field['type'] ?? null;
                if (! in_array($type, $fieldTypes, true)) {
                    $problems[] = "$fieldLabel: type must be one of ".implode(', ', $fieldTypes).'.';
                }
                if ($type === 'select') {
                    $options = $field['options'] ?? null;
                    if (! is_array($options) || $options === [] || array_is_list($options) || array_filter($options, fn ($v) => ! is_string($v)) !== []) {
                        $problems[] = "$fieldLabel: a select needs options as {\"value\": \"Label\"}.";
                    }
                }
                if (isset($field['required']) && ! is_bool($field['required'])) {
                    $problems[] = "$fieldLabel: required must be true or false.";
                }
            }
        }

        $checklist = $step['checklist'] ?? [];
        if (! is_array($checklist) || ! array_is_list($checklist)) {
            $problems[] = "$label: checklist must be a list.";
        } else {
            $checkKeys = [];
            foreach ($checklist as $item) {
                $itemKey = is_array($item) ? ($item['key'] ?? null) : null;
                if (! is_string($itemKey) || ! preg_match('/^[a-z][a-z0-9_]{0,59}$/', $itemKey) || ! self::text($item['label'] ?? null, 255)) {
                    $problems[] = "$label: each checklist item needs a key and a label.";
                } elseif (isset($checkKeys[$itemKey])) {
                    $problems[] = "$label: checklist key \"$itemKey\" is used twice.";
                }
                $checkKeys[(string) $itemKey] = true;
            }
        }

        if (! in_array($step['evidence_requirement'] ?? 'none', $evidenceValues, true)) {
            $problems[] = "$label: evidence_requirement must be one of ".implode(', ', $evidenceValues).'.';
        }
        if (isset($step['requires_review']) && ! is_bool($step['requires_review'])) {
            $problems[] = "$label: requires_review must be true or false.";
        }
        if (isset($step['active']) && ! is_bool($step['active'])) {
            $problems[] = "$label: active must be true or false.";
        }

        $template = $step['output_template'] ?? null;
        if ($template !== null && ! is_string($template)) {
            $problems[] = "$label: output_template must be text.";
        } elseif (is_string($template)) {
            preg_match_all('/\{\{\s*([a-z0-9_]+)\s*\}\}/', $template, $matches);
            foreach (array_unique($matches[1]) as $placeholder) {
                if (! isset($fieldKeys[$placeholder]) && ! in_array($placeholder, self::BUILT_IN_PLACEHOLDERS, true)) {
                    $problems[] = "$label: template placeholder {{{$placeholder}}} is not a field of this step.";
                }
            }
        }

        $vgi = $step['vgi_dimension_keys'] ?? [];
        if (! self::stringList($vgi)) {
            $problems[] = "$label: vgi_dimension_keys must be a list.";
        } else {
            foreach ($vgi as $dimension) {
                if (! in_array($dimension, $vgiKeys, true)) {
                    $problems[] = "$label: \"$dimension\" is not a VGI dimension (use ".implode(', ', $vgiKeys).').';
                }
            }
        }

        $prerequisites = $step['prerequisites'] ?? null;
        if ($prerequisites !== null) {
            if (! self::stringList($prerequisites)) {
                $problems[] = "$label: prerequisites must be null (previous step) or a list of step keys.";
            } else {
                foreach ($prerequisites as $prerequisite) {
                    if (! array_key_exists($prerequisite, $earlier)) {
                        $problems[] = "$label: prerequisite \"$prerequisite\" must be an existing step earlier in the sequence.";
                    } elseif (! $earlier[$prerequisite]) {
                        $problems[] = "$label: prerequisite \"$prerequisite\" is not active.";
                    }
                }
            }
        }
        if (isset($step['task_suggestions']) && ! self::stringList($step['task_suggestions'])) {
            $problems[] = "$label: task_suggestions must be a list of text.";
        }

        return $problems;
    }

    /** Export a stored curriculum to the editable array format. */
    public static function fromCurriculum(BuilderCurriculum $curriculum): array
    {
        $curriculum->loadMissing('modules.steps');

        return ['modules' => $curriculum->modules->sortBy('position')->values()->map(fn (BuilderModule $module) => [
            'key' => $module->key,
            'title' => $module->title,
            'description' => $module->description,
            'outputs' => $module->outputs ?? [],
            'active' => $module->is_active,
            'steps' => $module->steps->sortBy('position')->values()->map(fn (BuilderStepDefinition $step) => [
                'key' => $step->key,
                'title' => $step->title,
                'guidance' => $step->guidance,
                'fields' => $step->fields ?? [],
                'checklist' => $step->checklist ?? [],
                'evidence_requirement' => $step->evidence_requirement,
                'requires_review' => $step->requires_review,
                'output_template' => $step->output_template,
                'vgi_dimension_keys' => $step->vgi_dimension_keys ?? [],
                'prerequisites' => $step->prerequisites,
                'task_suggestions' => $step->task_suggestions ?? [],
                'active' => $step->is_active,
            ])->all(),
        ])->all()];
    }

    private static function text(mixed $value, int $max): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;
    }

    private static function stringList(mixed $value): bool
    {
        return is_array($value) && array_is_list($value) && array_filter($value, fn ($v) => ! is_string($v)) === [];
    }
}
