<?php

declare(strict_types=1);

namespace SpCrap;

final readonly class Config
{
    /**
     * @param list<string> $ignoreFiles
     * @param list<string> $ignoreClasses
     * @param list<string> $ignoreMethods
     */
    public function __construct(
        public ?string $report = null,
        public ?float $threshold = null,
        public ?int $maxViolations = null,
        public ?string $format = null,
        public ?string $maxAge = null,
        public array $ignoreFiles = [],
        public array $ignoreClasses = [],
        public array $ignoreMethods = [],
        public bool $publicMethodsOnly = true,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $ignoreFiles = self::stringList($data['ignore_files'] ?? $data['ignore_coverage_files'] ?? []);
        $ignoreClasses = self::stringList($data['ignore_classes'] ?? []);
        $ignoreMethods = self::stringList($data['ignore_methods'] ?? []);

        $threshold = null;

        if (array_key_exists('threshold', $data) && is_numeric($data['threshold'])) {
            $threshold = (float) $data['threshold'];
        }

        $maxViolations = null;

        if (array_key_exists('max_violations', $data) && is_numeric($data['max_violations'])) {
            $maxViolations = (int) $data['max_violations'];
        }

        $report = isset($data['report']) && is_string($data['report']) ? $data['report'] : null;
        $format = isset($data['format']) && is_string($data['format']) ? $data['format'] : null;
        $maxAge = isset($data['max_age']) && is_string($data['max_age']) ? $data['max_age'] : null;

        $publicMethodsOnly = true;

        if (array_key_exists('public_methods_only', $data)) {
            $publicMethodsOnly = (bool) $data['public_methods_only'];
        }

        if (array_key_exists('include_non_public', $data)) {
            $publicMethodsOnly = !(bool) $data['include_non_public'];
        }

        return new self(
            report: $report,
            threshold: $threshold,
            maxViolations: $maxViolations,
            format: $format,
            maxAge: $maxAge,
            ignoreFiles: $ignoreFiles,
            ignoreClasses: $ignoreClasses,
            ignoreMethods: $ignoreMethods,
            publicMethodsOnly: $publicMethodsOnly,
        );
    }

    /**
     * @return list<string>
     */
    private static function stringList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (is_string($item) && $item !== '') {
                $items[] = $item;
            }
        }

        return $items;
    }
}
