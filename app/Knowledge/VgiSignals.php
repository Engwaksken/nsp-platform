<?php

namespace App\Knowledge;

use App\Models\Registry\Startup;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the signals the Knowledge Hub uses for recommendations from the VGI module, if that
 * module provides App\Growth\VgiSummary::latestFor($startup). Returns empty signals otherwise.
 *
 * Accepted summary shapes (array, Arrayable or object):
 *   ['weakest' => ['customer_validation', ...] | [['key' => ..., 'name' => ...], ...], 'stage' => 'validation']
 *   ['dimensions' => [['key'|'name' => ..., 'score' => 42.0], ...] | ['customer_validation' => 42.0, ...]]
 */
class VgiSignals
{
    /** @return array{weakest: array<string>, stage: ?string} keys from config('knowledge.vgi_dimensions') / ('knowledge.stages') */
    public function for(Startup $startup): array
    {
        $empty = ['weakest' => [], 'stage' => null];
        $class = 'App\\Growth\\VgiSummary';
        if (! class_exists($class) || ! method_exists($class, 'latestFor')) {
            return $empty;
        }
        try {
            $summary = (new \ReflectionMethod($class, 'latestFor'))->isStatic()
                ? $class::latestFor($startup)
                : app($class)->latestFor($startup);
        } catch (Throwable) {
            return $empty;
        }

        return $summary === null ? $empty : $this->normalise($summary);
    }

    public function normalise(mixed $summary): array
    {
        $data = $this->toArray($summary);
        $weakest = [];
        foreach ((array) ($data['weakest'] ?? $data['weakest_dimensions'] ?? $data['gaps'] ?? []) as $item) {
            $weakest[] = $this->dimensionKey($item);
        }
        if (! $weakest && ! empty($data['dimensions'])) {
            $scores = [];
            foreach ((array) $data['dimensions'] as $key => $item) {
                $item = $this->toArray($item);
                $score = is_numeric($item) ? (float) $item : (isset($item['score']) && is_numeric($item['score']) ? (float) $item['score'] : null);
                $dim = is_string($key) ? $this->dimensionKey($key) : $this->dimensionKey($item);
                if ($score !== null && $dim) {
                    $scores[$dim] = $score;
                }
            }
            asort($scores);
            $weakest = array_slice(array_keys($scores), 0, 2);
        }
        $known = config('knowledge.vgi_dimensions', []);
        $weakest = array_values(array_unique(array_filter($weakest, fn ($k) => $k && isset($known[$k]))));

        $stage = $data['stage'] ?? null;
        $stage = is_string($stage) ? Str::snake(Str::lower($stage)) : null;

        return ['weakest' => $weakest, 'stage' => isset(config('knowledge.stages', [])[$stage ?? '']) ? $stage : null];
    }

    private function dimensionKey(mixed $item): ?string
    {
        $item = $this->toArray($item);
        $raw = is_string($item) ? $item : ($item['key'] ?? $item['name'] ?? $item['label'] ?? null);

        return is_string($raw) ? Str::snake(Str::lower(trim($raw))) : null;
    }

    private function toArray(mixed $value): mixed
    {
        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }

            return get_object_vars($value);
        }

        return $value;
    }
}
