<?php

declare(strict_types=1);

namespace SpCrap;

final class CheckCommand
{
    public const int EXIT_SUCCESS = 0;
    public const int EXIT_THRESHOLD_EXCEEDED = 1;
    public const int EXIT_INVALID_INPUT = 2;
    public const int EXIT_REPORT_NOT_FOUND = 3;
    public const int EXIT_INVALID_XML = 4;
    public const int EXIT_NO_METHODS = 5;
    public const int EXIT_STALE_REPORT = 6;

    /**
     * @param array<string, mixed> $options
     */
    public function run(string $reportPath, array $options, Io $io): int
    {
        $thresholdRaw = (string) ($options['threshold'] ?? '30');
        $format = (string) ($options['format'] ?? 'text');
        $maxViolationsRaw = $options['max-violations'] ?? null;
        $maxAgeRaw = $options['max-age'] ?? null;
        $verbose = (bool) ($options['verbose'] ?? false);
        $matcher = $options['ignore_matcher'] ?? new IgnoreMatcher();

        if (!is_numeric($thresholdRaw)) {
            $io->error(sprintf('Invalid threshold "%s": must be a number.', $thresholdRaw));

            return self::EXIT_INVALID_INPUT;
        }

        if (!in_array($format, ['text', 'json'], true)) {
            $io->error(sprintf('Invalid format "%s": must be "text" or "json".', $format));

            return self::EXIT_INVALID_INPUT;
        }

        $maxViolations = null;

        if ($maxViolationsRaw !== null) {
            if (!is_string($maxViolationsRaw) && !is_int($maxViolationsRaw)) {
                $io->error('Invalid --max-violations value.');

                return self::EXIT_INVALID_INPUT;
            }

            $maxViolationsString = (string) $maxViolationsRaw;

            if (!is_numeric($maxViolationsString) || (int) $maxViolationsString < 0) {
                $io->error(sprintf(
                    'Invalid --max-violations "%s": must be a non-negative integer.',
                    $maxViolationsString,
                ));

                return self::EXIT_INVALID_INPUT;
            }

            $maxViolations = (int) $maxViolationsString;
        }

        $maxAgeSeconds = null;

        if ($maxAgeRaw !== null) {
            if (!is_string($maxAgeRaw)) {
                $io->error('Invalid --max-age value.');

                return self::EXIT_INVALID_INPUT;
            }

            $maxAgeSeconds = $this->parseAge($maxAgeRaw);

            if ($maxAgeSeconds === null) {
                $io->error(sprintf(
                    'Invalid --max-age "%s": use minutes (e.g. 60) or a duration like 30m or 2h.',
                    $maxAgeRaw,
                ));

                return self::EXIT_INVALID_INPUT;
            }

            $staleExit = $this->checkReportAge($reportPath, $maxAgeSeconds, $io);

            if ($staleExit !== null) {
                Branding::emitOpening($io, $options, $format);
                Branding::emitClosing($io, $options, $format, $staleExit);

                return $staleExit;
            }
        }

        $threshold = (float) $thresholdRaw;

        Branding::emitOpening($io, $options, $format);

        try {
            $methods = (new Crap4jParser())->parse($reportPath);
        } catch (ReportNotFoundException $e) {
            $io->writeln($e->getMessage());
            $io->writeln('');
            $io->writeln('Generate it with:');
            $io->writeln('php -d pcov.enabled=1 vendor/bin/phpunit --coverage-crap4j build/crap4j.xml');

            $exitCode = self::EXIT_REPORT_NOT_FOUND;
            Branding::emitClosing($io, $options, $format, $exitCode);

            return $exitCode;
        } catch (InvalidReportException $e) {
            $io->error($e->getMessage());
            $exitCode = self::EXIT_INVALID_XML;
            Branding::emitClosing($io, $options, $format, $exitCode);

            return $exitCode;
        }

        if ($methods === []) {
            $io->comment('No methods found in report.');
            $exitCode = self::EXIT_NO_METHODS;
            Branding::emitClosing($io, $options, $format, $exitCode);

            return $exitCode;
        }

        [$checkedMethods, $ignoredCount] = $this->filterIgnored($methods, $matcher);
        $nonPublicSkipped = 0;

        if ((bool) ($options['public_methods_only'] ?? false)) {
            ProjectAutoload::registerFromWorkingDirectory();
            [$checkedMethods, $nonPublicSkipped] = (new PublicMethodFilter())->filterToPublicOnly($checkedMethods);
        }

        if ($checkedMethods === []) {
            $io->comment('No methods left to check after ignore and visibility rules.');
            $exitCode = self::EXIT_NO_METHODS;
            Branding::emitClosing($io, $options, $format, $exitCode);

            return $exitCode;
        }

        $violations = (new CrapAnalyzer())->findViolations($checkedMethods, $threshold);
        $exitCode = $this->resolveExitCode($violations, $maxViolations);

        if ($format === 'json') {
            $io->writeln($this->encodeJson(
                $threshold,
                count($methods),
                count($checkedMethods),
                $ignoredCount,
                $nonPublicSkipped,
                (bool) ($options['public_methods_only'] ?? false),
                $violations,
                $exitCode,
                $maxViolations,
            ));

            return $exitCode;
        }

        $exitCode = $this->renderText(
            $io,
            $violations,
            $threshold,
            $maxViolations,
            count($methods),
            count($checkedMethods),
            $ignoredCount,
            $nonPublicSkipped,
            $verbose,
            $exitCode,
        );
        Branding::emitClosing($io, $options, $format, $exitCode);

        return $exitCode;
    }

    /**
     * @param list<MethodMetric> $methods
     * @return array{0: list<MethodMetric>, 1: int}
     */
    private function filterIgnored(array $methods, IgnoreMatcher $matcher): array
    {
        $checked = [];
        $ignored = 0;

        foreach ($methods as $method) {
            if ($matcher->isIgnored($method)) {
                $ignored++;

                continue;
            }

            $checked[] = $method;
        }

        return [$checked, $ignored];
    }

    /**
     * @param list<MethodMetric> $violations
     */
    private function renderText(
        Io $io,
        array $violations,
        float $threshold,
        ?int $maxViolations,
        int $totalMethods,
        int $checkedMethods,
        int $ignoredCount,
        int $nonPublicSkipped,
        bool $verbose,
        int $exitCode,
    ): int {
        $thresholdLabel = $this->formatNumber($threshold);

        if ($violations === []) {
            $io->writeln(sprintf('CRAP threshold OK. Max allowed: %s', $thresholdLabel));
            $io->writeln(sprintf('Analyzed methods: %d', $checkedMethods));

            if ($ignoredCount > 0) {
                $io->writeln(sprintf('Ignored methods: %d', $ignoredCount));
            }

            if ($nonPublicSkipped > 0) {
                $io->writeln(sprintf('Skipped non-public methods: %d', $nonPublicSkipped));
            }

            $io->writeln('Violations: 0');

            if ($verbose) {
                $io->writeln(sprintf(
                    'Report: %d methods in file, %d checked, %d ignored, %d non-public skipped, 0 above threshold %s.',
                    $totalMethods,
                    $checkedMethods,
                    $ignoredCount,
                    $nonPublicSkipped,
                    $thresholdLabel,
                ));
            }

            return self::EXIT_SUCCESS;
        }

        $io->writeln(sprintf('CRAP threshold exceeded. Max allowed: %s', $thresholdLabel));
        $io->writeln('');

        $count = count($violations);
        $limit = $maxViolations !== null ? sprintf(' (limit: %d)', $maxViolations) : '';
        $io->writeln(sprintf('%d violation%s found%s:', $count, $count === 1 ? '' : 's', $limit));
        $io->writeln('');

        foreach ($violations as $i => $violation) {
            $this->writeViolation($io, $i + 1, $violation);
        }

        if ($verbose) {
            $io->writeln(sprintf('Analyzed methods: %d', $checkedMethods));

            if ($ignoredCount > 0) {
                $io->writeln(sprintf('Ignored methods: %d', $ignoredCount));
            }

            if ($nonPublicSkipped > 0) {
                $io->writeln(sprintf('Skipped non-public methods: %d', $nonPublicSkipped));
            }

            if ($maxViolations !== null) {
                $status = $exitCode === self::EXIT_SUCCESS ? 'within budget' : 'over budget';
                $io->writeln(sprintf(
                    'Violation budget: %d / %d (%s).',
                    $count,
                    $maxViolations,
                    $status,
                ));
            }
        }

        return $exitCode;
    }

    /**
     * @param list<MethodMetric> $violations
     */
    private function resolveExitCode(array $violations, ?int $maxViolations): int
    {
        if ($violations === []) {
            return self::EXIT_SUCCESS;
        }

        if ($maxViolations !== null && count($violations) <= $maxViolations) {
            return self::EXIT_SUCCESS;
        }

        return self::EXIT_THRESHOLD_EXCEEDED;
    }

    private function checkReportAge(string $reportPath, int $maxAgeSeconds, Io $io): ?int
    {
        if (!file_exists($reportPath)) {
            return null;
        }

        $mtime = filemtime($reportPath);

        if ($mtime === false) {
            return null;
        }

        $ageSeconds = time() - $mtime;

        if ($ageSeconds > $maxAgeSeconds) {
            $ageMinutes = (int) round($ageSeconds / 60);
            $maxMinutes = (int) round($maxAgeSeconds / 60);
            $io->error(sprintf(
                'Report is stale: generated %d minute%s ago (max: %d).',
                $ageMinutes,
                $ageMinutes === 1 ? '' : 's',
                $maxMinutes,
            ));

            return self::EXIT_STALE_REPORT;
        }

        return null;
    }

    private function parseAge(string $value): ?int
    {
        if (is_numeric($value) && (int) $value > 0) {
            return (int) $value * 60;
        }

        if (preg_match('/^(\d+)m$/', $value, $matches) === 1) {
            return (int) $matches[1] * 60;
        }

        if (preg_match('/^(\d+)h$/', $value, $matches) === 1) {
            return (int) $matches[1] * 3600;
        }

        return null;
    }

    private function writeViolation(Io $io, int $index, MethodMetric $method): void
    {
        $className = $method->className ?? 'unknown';
        $io->writeln(sprintf('%d) %s::%s()', $index, $className, $method->name));

        $file = $method->file ?? 'unknown';
        $line = $method->line !== null ? ':' . $method->line : '';
        $io->writeln(sprintf('   File: %s%s', $file, $line));
        $io->writeln(sprintf('   CRAP: %.2f', $method->crap));

        if ($method->complexity !== null) {
            $io->writeln(sprintf('   Complexity: %d', $method->complexity));
        }

        if ($method->coverage !== null) {
            $io->writeln(sprintf('   Coverage: %.2f%%', $method->coverage));
        }

        $io->writeln('');
    }

    /**
     * @param list<MethodMetric> $violations
     */
    private function encodeJson(
        float $threshold,
        int $totalMethods,
        int $checkedMethods,
        int $ignoredCount,
        int $nonPublicSkipped,
        bool $publicMethodsOnly,
        array $violations,
        int $exitCode,
        ?int $maxViolations,
    ): string {
        $data = [
            'threshold' => $threshold,
            'methods_in_report' => $totalMethods,
            'analyzed' => $checkedMethods,
            'ignored' => $ignoredCount,
            'skipped_non_public' => $nonPublicSkipped,
            'public_methods_only' => $publicMethodsOnly,
            'violations' => count($violations),
            'passed' => $exitCode === self::EXIT_SUCCESS,
            'max_violations' => $maxViolations,
            'methods' => array_map(
                static fn (MethodMetric $method): array => [
                    'name' => $method->name,
                    'class_name' => $method->className,
                    'file' => $method->file,
                    'line' => $method->line,
                    'crap' => $method->crap,
                    'complexity' => $method->complexity,
                    'coverage' => $method->coverage,
                ],
                $violations,
            ),
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function formatNumber(float $value): string
    {
        return $value === (float) (int) $value ? (string) (int) $value : (string) $value;
    }
}
