<?php

declare(strict_types=1);

namespace SpCrap;

final class Application
{
    private const string VERSION = '1.1.4';

    public static function version(): string
    {
        return self::VERSION;
    }

    /**
     * @param list<string> $argv
     */
    public static function run(array $argv): int
    {
        $io = new Io();
        $args = array_slice($argv, 1);

        if ($args === [] || in_array($args[0], ['-h', '--help', 'help'], true)) {
            Branding::emitForDoctor($io, ['logo' => true]);
            self::printHelp($io);

            return CheckCommand::EXIT_SUCCESS;
        }

        if (in_array($args[0], ['-V', '--version', 'version'], true)) {
            Branding::emitForDoctor($io, []);

            return CheckCommand::EXIT_SUCCESS;
        }

        if ($args[0] === 'doctor') {
            array_shift($args);
            $doctorOptions = self::parseBrandingOptions($args);

            return (new DoctorCommand())->run($io, $doctorOptions);
        }

        $positional = [];

        if ($args[0] === 'check') {
            array_shift($args);
        } elseif (!str_starts_with($args[0], '-')) {
            $positional[] = array_shift($args);
        }

        $options = [
            'threshold' => null,
            'format' => null,
            'max-violations' => null,
            'max-age' => null,
            'verbose' => false,
            'quiet' => false,
            'logo' => false,
            'public-only' => false,
            'config' => null,
        ];

        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            if ($arg === '-v' || $arg === '--verbose') {
                $options['verbose'] = true;

                continue;
            }

            if ($arg === '-q' || $arg === '--quiet') {
                $options['quiet'] = true;
                $options['verbose'] = false;

                continue;
            }

            if ($arg === '--logo') {
                $options['logo'] = true;

                continue;
            }

            if ($arg === '--public-only') {
                $options['public-only'] = true;

                continue;
            }

            if (str_starts_with($arg, '--config=')) {
                $options['config'] = substr($arg, strlen('--config='));

                continue;
            }

            if ($arg === '--config') {
                $value = self::optionalValue($args, $i, '--config', $io);

                if ($value === null) {
                    return CheckCommand::EXIT_INVALID_INPUT;
                }

                $options['config'] = $value;

                continue;
            }

            if (str_starts_with($arg, '--threshold=')) {
                $options['threshold'] = substr($arg, strlen('--threshold='));

                continue;
            }

            if ($arg === '--threshold') {
                $value = self::optionalValue($args, $i, '--threshold', $io);

                if ($value === null) {
                    return CheckCommand::EXIT_INVALID_INPUT;
                }

                $options['threshold'] = $value;

                continue;
            }

            if (str_starts_with($arg, '--max-violations=')) {
                $options['max-violations'] = substr($arg, strlen('--max-violations='));

                continue;
            }

            if ($arg === '--max-violations') {
                $value = self::optionalValue($args, $i, '--max-violations', $io);

                if ($value === null) {
                    return CheckCommand::EXIT_INVALID_INPUT;
                }

                $options['max-violations'] = $value;

                continue;
            }

            if (str_starts_with($arg, '--format=')) {
                $options['format'] = substr($arg, strlen('--format='));

                continue;
            }

            if ($arg === '--format') {
                $value = self::optionalValue($args, $i, '--format', $io);

                if ($value === null) {
                    return CheckCommand::EXIT_INVALID_INPUT;
                }

                $options['format'] = $value;

                continue;
            }

            if (str_starts_with($arg, '--max-age=')) {
                $options['max-age'] = substr($arg, strlen('--max-age='));

                continue;
            }

            if ($arg === '--max-age') {
                $value = self::optionalValue($args, $i, '--max-age', $io);

                if ($value === null) {
                    return CheckCommand::EXIT_INVALID_INPUT;
                }

                $options['max-age'] = $value;

                continue;
            }

            if (str_starts_with($arg, '-')) {
                $io->error(sprintf('Unknown option: %s', $arg));
                $io->writeln('Run sp-crap-check --help for usage.');

                return CheckCommand::EXIT_INVALID_INPUT;
            }

            $positional[] = $arg;
        }

        $workingDir = (string) getcwd();
        $configPath = ConfigLoader::discoverPath($options['config'], $workingDir);
        $fileConfig = new Config();

        if ($configPath !== null) {
            try {
                $fileConfig = (new ConfigLoader())->load($configPath);
            } catch (ConfigException $e) {
                $io->error($e->getMessage());

                return CheckCommand::EXIT_INVALID_INPUT;
            }
        }

        $reportPath = $positional[0] ?? $fileConfig->report ?? 'build/crap4j.xml';
        $threshold = $options['threshold'] ?? ($fileConfig->threshold !== null ? (string) $fileConfig->threshold : '30');
        $format = $options['format'] ?? $fileConfig->format ?? 'text';
        $maxViolations = $options['max-violations'] ?? ($fileConfig->maxViolations !== null ? (string) $fileConfig->maxViolations : null);
        $maxAge = $options['max-age'] ?? $fileConfig->maxAge;
        $publicMethodsOnly = $fileConfig->publicMethodsOnly;

        if ($options['public-only']) {
            $publicMethodsOnly = true;
        }

        $runOptions = [
            'threshold' => $threshold,
            'format' => $format,
            'max-violations' => $maxViolations,
            'max-age' => $maxAge,
            'verbose' => $options['verbose'],
            'quiet' => $options['quiet'],
            'logo' => $options['logo'],
            'public_methods_only' => $publicMethodsOnly,
            'ignore_matcher' => new IgnoreMatcher(
                $fileConfig->ignoreFiles,
                $fileConfig->ignoreClasses,
                $fileConfig->ignoreMethods,
            ),
        ];

        if ($options['verbose'] && $configPath !== null) {
            $io->writeln(sprintf('Using config: %s', $configPath));
        }

        return (new CheckCommand())->run($reportPath, $runOptions, $io);
    }

    /**
     * @param list<string> $args
     */
    /**
     * @param list<string> $args
     * @return array{verbose: bool, quiet: bool, logo: bool}
     */
    private static function parseBrandingOptions(array $args): array
    {
        $options = ['verbose' => false, 'quiet' => false, 'logo' => false];

        foreach ($args as $arg) {
            if ($arg === '-v' || $arg === '--verbose') {
                $options['verbose'] = true;
            }

            if ($arg === '-q' || $arg === '--quiet') {
                $options['quiet'] = true;
            }

            if ($arg === '--logo') {
                $options['logo'] = true;
            }
        }

        return $options;
    }

    private static function optionalValue(array $args, int &$index, string $name, Io $io): ?string
    {
        if (!isset($args[$index + 1]) || str_starts_with($args[$index + 1], '-')) {
            $io->error(sprintf('Option %s requires a value.', $name));

            return null;
        }

        $index++;

        return $args[$index];
    }

    private static function printHelp(Io $io): void
    {
        $io->writeln('sp-crap-check ' . self::VERSION);
        $io->writeln('');
        $io->writeln('Usage:');
        $io->writeln('  php sp-crap-check check [report] [options]');
        $io->writeln('  php sp-crap-check doctor');
        $io->writeln('');
        $io->writeln('Arguments:');
        $io->writeln('  report                 Path to PHPUnit crap4j.xml (default: build/crap4j.xml)');
        $io->writeln('');
        $io->writeln('Options:');
        $io->writeln('  --config=FILE          JSON config (default: search .crap-checker.json upward)');
        $io->writeln('  --threshold=NUM        Maximum allowed CRAP score per method (default: 30)');
        $io->writeln('  --max-violations=NUM   Allow up to NUM methods above threshold (ratchet)');
        $io->writeln('  --max-age=DURATION     Fail if report is older than DURATION (60, 30m, 2h)');
        $io->writeln('  --format=FORMAT        text or json (default: text)');
        $io->writeln('  -v, --verbose          Extra summary + ASCII logo');
        $io->writeln('  --logo                 ASCII logo (also SP_CRAP_LOGO=1)');
        $io->writeln('  --public-only          Only gate public methods (optional filter)');
        $io->writeln('  -q, --quiet            No branding output');
        $io->writeln('  -h, --help             Show this help');
        $io->writeln('  -V, --version          Show version');
        $io->writeln('');
        $io->writeln('Config file (.crap-checker.json):');
        $io->writeln('  report, threshold, max_violations, format, max_age');
        $io->writeln('  ignore_files, ignore_classes, ignore_methods, public_methods_only');
        $io->writeln('  (ignore_coverage_files is an alias for ignore_files)');
        $io->writeln('');
        $io->writeln('Examples:');
        $io->writeln('  php sp-crap-check check var/coverage/crap4j.xml --threshold=30 --max-violations=111 -v');
        $io->writeln('  php sp-crap-check doctor');
    }
}

final class Io
{
    public function writeln(string $line = ''): void
    {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    public function error(string $message): void
    {
        $this->styled(STDERR, $message, '31');
    }

    public function comment(string $message): void
    {
        $this->styled(STDOUT, $message, '33');
    }

    public function ok(string $message): void
    {
        $this->writeln(sprintf('  %s %s', $this->tag('OK', '32'), $message));
    }

    public function warn(string $message): void
    {
        $this->writeln(sprintf('  %s %s', $this->tag('WARN', '33'), $message));
    }

    public function fail(string $message): void
    {
        $this->writeln(sprintf('  %s %s', $this->tag('FAIL', '31'), $message));
    }

    private function styled($stream, string $message, string $color): void
    {
        if (self::useColor()) {
            fwrite($stream, "\033[" . $color . 'm' . $message . "\033[0m" . PHP_EOL);

            return;
        }

        fwrite($stream, $message . PHP_EOL);
    }

    private function tag(string $label, string $color): string
    {
        if (!self::useColor()) {
            return '[' . $label . ']';
        }

        return "\033[" . $color . 'm[' . $label . "]\033[0m";
    }

    public function brandTitle(string $text): string
    {
        if (!self::useColor()) {
            return $text;
        }

        return "\033[1;36m" . $text . "\033[0m";
    }

    public function brandLine(string $line): void
    {
        $this->writeln($line);
    }

    public function brandRaw(string $line, string $color = '36'): void
    {
        if (!self::useColor()) {
            $this->writeln($line);

            return;
        }

        fwrite(STDOUT, "\033[" . $color . 'm' . $line . "\033[0m" . PHP_EOL);
    }

    public function brandMuted(string $line): void
    {
        if (!self::useColor()) {
            $this->writeln($line);

            return;
        }

        fwrite(STDOUT, "\033[2;90m" . $line . "\033[0m" . PHP_EOL);
    }

    private static function useColor(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        return function_exists('posix_isatty') && posix_isatty(STDOUT);
    }
}
