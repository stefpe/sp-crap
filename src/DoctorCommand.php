<?php

declare(strict_types=1);

namespace SpCrap;

final class DoctorCommand
{
    /**
     * @param list<string>|null $loadedExtensions
     */
    public function run(Io $io, string $workingDir = '', ?array $loadedExtensions = null): int
    {
        $io->writeln('sp-crap-check — Doctor');
        $io->writeln('');

        $extensions = $loadedExtensions !== null
            ? array_map(strtolower(...), $loadedExtensions)
            : array_map(strtolower(...), get_loaded_extensions());

        $io->writeln('PHP Runtime');
        $io->ok(sprintf('PHP %s', PHP_VERSION));
        $io->writeln('');

        $io->writeln('Extensions');
        $hasFail = $this->checkExtensions($extensions, $io);
        $io->writeln('');

        $cwd = $workingDir !== '' ? $workingDir : (string) getcwd();
        $io->writeln('PHPUnit Configuration');

        $configPath = $this->findPhpunitConfig($cwd);

        if ($configPath === null) {
            $io->warn('phpunit.xml or phpunit.xml.dist not found');
            $io->writeln(sprintf('     → Expected in: %s', $cwd));
            $io->writeln('');

            return $hasFail ? CheckCommand::EXIT_THRESHOLD_EXCEEDED : CheckCommand::EXIT_SUCCESS;
        }

        $io->ok(basename($configPath) . ' found');

        return $this->checkPhpunitXml($configPath, $hasFail, $io);
    }

    /**
     * @param list<string> $extensions
     */
    private function checkExtensions(array $extensions, Io $io): bool
    {
        $hasFail = false;

        $hasSimpleXml = in_array('simplexml', $extensions, true);

        if ($hasSimpleXml) {
            $io->ok('ext-simplexml loaded');
        } else {
            $io->fail('ext-simplexml not found');
            $io->writeln('     → Enable ext-simplexml in your php.ini');
            $hasFail = true;
        }

        $hasPcov = in_array('pcov', $extensions, true);
        $hasXdebug = in_array('xdebug', $extensions, true);

        if (!$hasPcov && !$hasXdebug) {
            $io->warn('No coverage driver (PCOV or Xdebug) detected');
            $io->writeln('     → Install PCOV or enable Xdebug for coverage generation');

            return $hasFail;
        }

        $driver = $hasPcov ? 'PCOV' : 'Xdebug';
        $io->ok(sprintf('Coverage driver found: %s', $driver));

        return $hasFail;
    }

    private function checkPhpunitXml(string $configPath, bool $hasFail, Io $io): int
    {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_file($configPath);
        libxml_clear_errors();

        if ($xml === false) {
            $io->fail(sprintf('Failed to parse %s', basename($configPath)));
            $io->writeln('');

            return CheckCommand::EXIT_THRESHOLD_EXCEEDED;
        }

        $crap4jPath = $this->findCrap4jPath($xml);

        if ($crap4jPath === null) {
            $io->warn('Crap4J report not configured');
            $io->writeln('    => Add <crap4j outputFile="build/crap4j.xml"/> inside <coverage><report>');
            $io->writeln('');

            return $hasFail ? CheckCommand::EXIT_THRESHOLD_EXCEEDED : CheckCommand::EXIT_SUCCESS;
        }

        $io->ok(sprintf('Crap4J report configured: %s', $crap4jPath));
        $io->writeln('');
        $io->writeln('Report Path');

        $defaultReport = 'build/crap4j.xml';

        if ($crap4jPath === $defaultReport) {
            $io->ok(sprintf('Default report path matches PHPUnit config (%s)', $defaultReport));
            $io->writeln('');

            return $hasFail ? CheckCommand::EXIT_THRESHOLD_EXCEEDED : CheckCommand::EXIT_SUCCESS;
        }

        $io->warn(sprintf(
            'PHPUnit writes to "%s" but checker default is "%s"',
            $crap4jPath,
            $defaultReport,
        ));
        $io->writeln(sprintf('     → Run: sp-crap-check check %s', $crap4jPath));
        $io->writeln('');

        return $hasFail ? CheckCommand::EXIT_THRESHOLD_EXCEEDED : CheckCommand::EXIT_SUCCESS;
    }

    private function findPhpunitConfig(string $dir): ?string
    {
        foreach (['phpunit.xml', 'phpunit.xml.dist'] as $filename) {
            $path = $dir . '/' . $filename;

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function findCrap4jPath(\SimpleXMLElement $xml): ?string
    {
        $nodes = $xml->xpath('coverage/report/crap4j/@outputFile');

        if (is_array($nodes) && $nodes !== []) {
            $path = (string) $nodes[0];

            if ($path !== '') {
                return $path;
            }
        }

        return null;
    }
}
