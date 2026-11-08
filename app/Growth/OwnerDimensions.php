<?php

namespace App\Growth;

/**
 * The ten VGI dimensions named by the owner (M03). Keys are fixed so other
 * modules (Venture Builder, Intelligence, dashboards) can refer to them;
 * titles must match exactly. A rubric can only be approved when it uses
 * exactly these ten dimensions (config vgi.require_owner_dimensions).
 */
final class OwnerDimensions
{
    /** @var array<string, string> key => exact owner title, in the owner's order */
    public const ALL = [
        'problem_clarity' => 'Problem Clarity',
        'customer_validation' => 'Customer Validation',
        'market_understanding' => 'Market Understanding',
        'product_readiness' => 'Product Readiness',
        'business_model_strength' => 'Business Model Strength',
        'market_traction' => 'Market Traction',
        'team_capacity' => 'Team Capacity',
        'financial_readiness' => 'Financial Readiness',
        'scalability' => 'Scalability',
        'commercialization_readiness' => 'Commercialization Readiness',
    ];

    /**
     * Ecosystem services that commonly help with each dimension (feature key => label).
     * Links are only shown when the feature is on and its route exists.
     *
     * @var array<string, list<string>>
     */
    public const SERVICES = [
        'problem_clarity' => ['knowledge', 'experts'],
        'customer_validation' => ['knowledge', 'experts'],
        'market_understanding' => ['knowledge', 'experts'],
        'product_readiness' => ['experts', 'knowledge'],
        'business_model_strength' => ['experts', 'knowledge'],
        'market_traction' => ['opportunities', 'experts', 'knowledge'],
        'team_capacity' => ['experts', 'knowledge'],
        'financial_readiness' => ['funding', 'experts', 'knowledge'],
        'scalability' => ['opportunities', 'experts', 'knowledge'],
        'commercialization_readiness' => ['opportunities', 'funding', 'experts', 'knowledge'],
    ];

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    public static function title(string $key): ?string
    {
        return self::ALL[$key] ?? null;
    }

    /**
     * Why a rubric does not use exactly the ten owner dimensions; empty means it does.
     *
     * @return list<string>
     */
    public static function problems(mixed $definition): array
    {
        $dimensions = is_array($definition) && is_array($definition['dimensions'] ?? null) ? $definition['dimensions'] : [];
        $found = [];
        foreach ($dimensions as $dimension) {
            if (is_array($dimension) && is_string($dimension['key'] ?? null)) {
                $found[$dimension['key']] = is_string($dimension['title'] ?? null) ? $dimension['title'] : '';
            }
        }
        $errors = [];
        foreach (self::ALL as $key => $title) {
            if (! array_key_exists($key, $found)) {
                $errors[] = "The owner dimension \"$title\" (key $key) is missing.";
            } elseif ($found[$key] !== $title) {
                $errors[] = "Dimension $key must be titled exactly \"$title\" (it is \"{$found[$key]}\").";
            }
        }
        foreach (array_diff(array_keys($found), self::keys()) as $extra) {
            $errors[] = "Dimension \"$extra\" is not one of the ten owner dimensions. Move its questions into one of them.";
        }

        return $errors;
    }

    public static function matches(mixed $definition): bool
    {
        return self::problems($definition) === [];
    }
}
