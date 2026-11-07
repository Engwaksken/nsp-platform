<?php

namespace App\Capital;

use App\Models\Registry\Startup;
use Illuminate\Contracts\Support\Arrayable;
use ReflectionMethod;
use Throwable;

/**
 * Reads VGI and Venture Builder summaries from their own modules
 * (App\Growth\VgiSummary::latestFor and App\Builder\BuilderService::progressFor)
 * and reduces them to the few fields Capital Exchange may show.
 *
 * Both sources are optional: when a module is not installed, or returns
 * nothing, the result is null and pages say "not available". Never returns
 * answers, evidence or personal details, only summary figures and labels.
 */
class VentureSignals
{
    /**
     * @return array{version: ?string, stage: ?string, score: ?float, provisional: bool, assessed_at: ?string, dimensions: list<array{name: string, score: ?float}>}|null
     */
    public function vgi(Startup $startup): ?array
    {
        $raw = $this->call('App\Growth\VgiSummary', 'latestFor', $startup);
        if ($raw === null) {
            return null;
        }
        $data = $this->toArray($raw);
        if ($data === []) {
            return null;
        }
        $status = $this->pick($data, ['methodology_status', 'status']);
        $provisional = $this->pick($data, ['provisional', 'is_provisional']);
        $score = $this->pick($data, ['overall', 'overall_score', 'score']);
        $dimensions = [];
        foreach ((array) ($this->pick($data, ['dimensions', 'dimension_scores']) ?? []) as $key => $dimension) {
            $dimension = $this->toArray($dimension);
            $name = $this->pick($dimension, ['name', 'title', 'label']) ?? (is_string($key) ? $key : null);
            if ($name !== null) {
                $value = $this->pick($dimension, ['score', 'value']);
                $dimensions[] = ['name' => (string) $name, 'score' => is_numeric($value) ? (float) $value : null];
            }
        }
        $version = $this->pick($data, ['rubric_version', 'version', 'framework_version']);
        $assessed = $this->pick($data, ['assessed_at', 'submitted_at', 'date']);

        return [
            'version' => $version !== null ? (string) $version : null,
            'stage' => ($stage = $this->pick($data, ['stage', 'stage_label', 'band', 'band_label'])) !== null ? (string) $stage : null,
            'score' => is_numeric($score) ? (float) $score : null,
            // Anything not explicitly owner-approved is shown as provisional.
            'provisional' => $provisional !== null ? (bool) $provisional : ! in_array($status, ['approved', 'owner_approved', 'official'], true),
            'assessed_at' => $assessed instanceof \DateTimeInterface ? $assessed->format('Y-m-d') : ($assessed !== null ? (string) $assessed : null),
            'dimensions' => $dimensions,
        ];
    }

    /**
     * @return array{completed: int, total: int, percent: int, modules: list<array{name: string, completed: int, total: int}>}|null
     */
    public function builderProgress(Startup $startup): ?array
    {
        $raw = $this->call('App\Builder\BuilderService', 'progressFor', $startup);
        if ($raw === null) {
            return null;
        }
        $data = $this->toArray($raw);
        $completed = $this->pick($data, ['completed', 'completed_steps', 'steps_completed', 'done']);
        $total = $this->pick($data, ['total', 'total_steps', 'steps_total']);
        $percent = $this->pick($data, ['percent', 'percentage', 'progress']);
        if (! is_numeric($completed) && ! is_numeric($percent)) {
            return null;
        }
        $completed = (int) $completed;
        $total = (int) $total;
        $modules = [];
        foreach ((array) ($this->pick($data, ['modules']) ?? []) as $module) {
            $module = $this->toArray($module);
            $name = $this->pick($module, ['name', 'title', 'label']);
            if ($name !== null) {
                $modules[] = ['name' => (string) $name, 'completed' => (int) $this->pick($module, ['completed', 'completed_steps', 'done']), 'total' => (int) $this->pick($module, ['total', 'total_steps'])];
            }
        }

        return [
            'completed' => $completed,
            'total' => $total,
            'percent' => is_numeric($percent) ? (int) round((float) $percent) : ($total > 0 ? (int) round($completed / $total * 100) : 0),
            'modules' => $modules,
        ];
    }

    private function call(string $class, string $method, Startup $startup): mixed
    {
        if (! class_exists($class) || ! method_exists($class, $method)) {
            return null;
        }
        try {
            return (new ReflectionMethod($class, $method))->isStatic()
                ? $class::$method($startup)
                : app($class)->{$method}($startup);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    private function toArray(mixed $value): array
    {
        return match (true) {
            is_array($value) => $value,
            $value instanceof Arrayable => $value->toArray(),
            is_object($value) => get_object_vars($value),
            default => [],
        };
    }

    private function pick(array $data, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && $data[$key] !== null) {
                return $data[$key];
            }
        }

        return null;
    }
}
