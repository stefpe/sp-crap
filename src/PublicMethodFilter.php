<?php

declare(strict_types=1);

namespace SpCrap;

use ReflectionException;
use ReflectionMethod;

final class PublicMethodFilter
{
    /**
     * @param list<MethodMetric> $methods
     * @return array{0: list<MethodMetric>, 1: int}
     */
    public function filterToPublicOnly(array $methods): array
    {
        $public = [];
        $skipped = 0;

        foreach ($methods as $method) {
            if ($this->isPublicApiMethod($method)) {
                $public[] = $method;

                continue;
            }

            $skipped++;
        }

        return [$public, $skipped];
    }

    private function isPublicApiMethod(MethodMetric $method): bool
    {
        if ($method->className === null || $method->className === '') {
            return true;
        }

        if (!class_exists($method->className) && !interface_exists($method->className, false)) {
            return true;
        }

        try {
            $reflection = new ReflectionMethod($method->className, $method->name);
        } catch (ReflectionException) {
            return true;
        }

        return $reflection->isPublic();
    }
}
