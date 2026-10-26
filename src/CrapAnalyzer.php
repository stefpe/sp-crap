<?php

declare(strict_types=1);

namespace SpCrap;

final class CrapAnalyzer
{
    /**
     * @param list<MethodMetric> $methods
     * @return list<MethodMetric>
     */
    public function findViolations(array $methods, float $threshold): array
    {
        $violations = array_values(array_filter(
            $methods,
            static fn (MethodMetric $method): bool => $method->crap > $threshold,
        ));

        usort($violations, static function (MethodMetric $left, MethodMetric $right): int {
            if ($left->crap !== $right->crap) {
                return $right->crap <=> $left->crap;
            }

            $complexityLeft = $left->complexity ?? 0;
            $complexityRight = $right->complexity ?? 0;

            if ($complexityLeft !== $complexityRight) {
                return $complexityRight <=> $complexityLeft;
            }

            return $left->name <=> $right->name;
        });

        return $violations;
    }
}
