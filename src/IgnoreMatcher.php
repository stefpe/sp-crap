<?php

declare(strict_types=1);

namespace SpCrap;

final class IgnoreMatcher
{
    /**
     * @param list<string> $ignoreFiles
     * @param list<string> $ignoreClasses
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        private array $ignoreFiles = [],
        private array $ignoreClasses = [],
        private array $ignoreMethods = [],
    ) {
    }

    public function isIgnored(MethodMetric $method): bool
    {
        if ($this->matchesMethodRules($method)) {
            return true;
        }

        if ($this->matchesClassRules($method)) {
            return true;
        }

        if ($this->matchesFileRules($method)) {
            return true;
        }

        return false;
    }

    private function matchesMethodRules(MethodMetric $method): bool
    {
        foreach ($this->ignoreMethods as $rule) {
            if ($this->methodRuleMatches($rule, $method)) {
                return true;
            }
        }

        return false;
    }

    private function matchesClassRules(MethodMetric $method): bool
    {
        $className = $method->className;

        if ($className === null || $className === '') {
            return false;
        }

        foreach ($this->ignoreClasses as $rule) {
            if ($this->wildcardMatch($rule, $className)) {
                return true;
            }
        }

        return false;
    }

    private function matchesFileRules(MethodMetric $method): bool
    {
        foreach ($this->ignoreFiles as $rule) {
            if ($method->file !== null && $this->pathRuleMatches($rule, $method->file)) {
                return true;
            }

            if ($method->className !== null && $this->fileRuleMatchesClass($rule, $method->className)) {
                return true;
            }
        }

        return false;
    }

    private function methodRuleMatches(string $rule, MethodMetric $method): bool
    {
        $rule = trim($rule);

        if ($rule === '') {
            return false;
        }

        if (!str_contains($rule, '::')) {
            return $method->name === $rule;
        }

        [$classPattern, $methodPattern] = explode('::', $rule, 2);
        $className = $method->className ?? '';

        if (!$this->wildcardMatch($classPattern, $className)) {
            return false;
        }

        if ($methodPattern === '*' || $methodPattern === '') {
            return true;
        }

        return $this->wildcardMatch($methodPattern, $method->name);
    }

    private function fileRuleMatchesClass(string $rule, string $className): bool
    {
        $normalizedRule = self::normalizePath($rule);
        $base = basename($normalizedRule, '.php');

        if ($base === '') {
            return false;
        }

        if (str_ends_with($className, '\\' . $base) || $className === $base) {
            return true;
        }

        $suffix = str_replace('/', '\\', preg_replace('#^src/#', '', $normalizedRule) ?? $normalizedRule);
        $suffix = preg_replace('/\.php$/', '', $suffix) ?? $suffix;

        return $suffix !== '' && str_ends_with($className, $suffix);
    }

    private function pathRuleMatches(string $rule, string $filePath): bool
    {
        $normalizedFile = self::normalizePath($filePath);
        $normalizedRule = self::normalizePath($rule);

        if ($normalizedFile === $normalizedRule) {
            return true;
        }

        if (str_ends_with($normalizedFile, '/' . $normalizedRule)) {
            return true;
        }

        return $this->wildcardMatch($normalizedRule, $normalizedFile);
    }

    private function wildcardMatch(string $pattern, string $value): bool
    {
        if ($pattern === $value) {
            return true;
        }

        if (!str_contains($pattern, '*') && !str_contains($pattern, '?')) {
            return false;
        }

        $regex = '/^';
        $length = strlen($pattern);

        for ($offset = 0; $offset < $length; $offset++) {
            $char = $pattern[$offset];

            if ($char === '*') {
                $regex .= '.*';

                continue;
            }

            if ($char === '?') {
                $regex .= '.';

                continue;
            }

            $regex .= preg_quote($char, '/');
        }

        $regex .= '$/u';

        return preg_match($regex, $value) === 1;
    }

    private static function normalizePath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
