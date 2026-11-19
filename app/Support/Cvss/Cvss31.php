<?php

namespace App\Support\Cvss;

/**
 * CVSS 3.0 / 3.1 base score from a vector string, per the FIRST CVSS v3.1 specification (section 7).
 * Temporal and environmental metrics after the base metrics are ignored. Other versions return null.
 */
class Cvss31
{
    private const WEIGHTS = [
        'AV' => ['N' => 0.85, 'A' => 0.62, 'L' => 0.55, 'P' => 0.2],
        'AC' => ['L' => 0.77, 'H' => 0.44],
        'UI' => ['N' => 0.85, 'R' => 0.62],
        'C' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
        'I' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
        'A' => ['H' => 0.56, 'L' => 0.22, 'N' => 0.0],
    ];

    /** Privileges Required depends on Scope. */
    private const PR = [
        'U' => ['N' => 0.85, 'L' => 0.62, 'H' => 0.27],
        'C' => ['N' => 0.85, 'L' => 0.68, 'H' => 0.5],
    ];

    public static function score(string $vector): ?float
    {
        if (! preg_match('#^CVSS:3\.[01]/(.+)$#', trim($vector), $m)) {
            return null;
        }

        $metrics = [];
        foreach (explode('/', $m[1]) as $part) {
            [$key, $value] = array_pad(explode(':', $part, 2), 2, null);
            $metrics[$key] = $value;
        }

        $scope = $metrics['S'] ?? null;
        if (! in_array($scope, ['U', 'C'], true) || ! isset(self::PR[$scope][$metrics['PR'] ?? ''])) {
            return null;
        }
        foreach (self::WEIGHTS as $key => $values) {
            if (! isset($values[$metrics[$key] ?? ''])) {
                return null;
            }
        }

        $w = fn (string $key) => self::WEIGHTS[$key][$metrics[$key]];
        $iss = 1 - ((1 - $w('C')) * (1 - $w('I')) * (1 - $w('A')));
        $impact = $scope === 'U'
            ? 6.42 * $iss
            : 7.52 * ($iss - 0.029) - 3.25 * (($iss - 0.02) ** 15);
        $exploitability = 8.22 * $w('AV') * $w('AC') * self::PR[$scope][$metrics['PR']] * $w('UI');

        if ($impact <= 0) {
            return 0.0;
        }

        $base = $scope === 'U'
            ? min($impact + $exploitability, 10)
            : min(1.08 * ($impact + $exploitability), 10);

        return self::roundUp($base);
    }

    /** The specification's Roundup: smallest number with one decimal that is >= the input, float-safe. */
    private static function roundUp(float $value): float
    {
        $int = (int) round($value * 100000);

        return $int % 10000 === 0 ? $int / 100000 : (floor($int / 10000) + 1) / 10.0;
    }
}
