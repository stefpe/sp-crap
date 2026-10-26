<?php

declare(strict_types=1);

namespace SpCrap;

final class Branding
{
    /**
     * @param array<string, mixed> $options
     */
    public static function wantsAsciiLogo(array $options): bool
    {
        if ((bool) ($options['quiet'] ?? false)) {
            return false;
        }

        if ((bool) ($options['logo'] ?? false) || (bool) ($options['verbose'] ?? false)) {
            return true;
        }

        $env = getenv('SP_CRAP_LOGO');

        return $env !== false && $env !== '' && $env !== '0';
    }

    /**
     * @param array<string, mixed> $options
     */
    public static function wantsBranding(array $options, string $format): bool
    {
        if ($format === 'json' || (bool) ($options['quiet'] ?? false)) {
            return false;
        }

        return true;
    }

    public static function emitOpening(Io $io, array $options, string $format): void
    {
        if (!self::wantsBranding($options, $format)) {
            return;
        }

        if (self::wantsAsciiLogo($options)) {
            self::writeAsciiLogo($io);

            return;
        }

        self::writeCompactBanner($io);
    }

    public static function emitClosing(Io $io, array $options, string $format, int $exitCode): void
    {
        if (!self::wantsBranding($options, $format)) {
            return;
        }

        $io->writeln('');
        $status = match (true) {
            $exitCode === CheckCommand::EXIT_SUCCESS => 'gate passed',
            $exitCode === CheckCommand::EXIT_THRESHOLD_EXCEEDED => 'gate failed — reduce CRAP or raise budget',
            default => 'finished with errors',
        };
        $io->brandMuted(sprintf(
            '— SP-CRAP · %s · exit %d · v%s',
            $status,
            $exitCode,
            Application::version(),
        ));
    }

    public static function emitForDoctor(Io $io, array $options = []): void
    {
        if ((bool) ($options['quiet'] ?? false)) {
            return;
        }

        if (self::wantsAsciiLogo($options)) {
            self::writeAsciiLogo($io);

            return;
        }

        self::writeCompactBanner($io);
    }

    private static function writeCompactBanner(Io $io): void
    {
        $io->brandLine(sprintf(
            '▸ %s · PHPUnit crap4j change-risk gate · v%s',
            $io->brandTitle('SP-CRAP'),
            Application::version(),
        ));
        $io->writeln('');
    }

    private static function writeAsciiLogo(Io $io): void
    {
        $lines = [
            ['text' => '    ╭──────╮', 'color' => '36'],
            ['text' => ' ╭──┤  S   ├──────────╮', 'color' => '36'],
            ['text' => ' │  ╰──────╯    C      │· · □', 'color' => '34'],
            ['text' => ' │         ╲___________╱', 'color' => '34'],
            ['text' => ' ╰─────────────────────╯', 'color' => '35'],
            ['text' => '      S P - C R A P', 'color' => '35'],
            ['text' => '  change-risk gate for PHPUnit', 'color' => '90'],
        ];

        foreach ($lines as $line) {
            $io->brandRaw($line['text'], $line['color']);
        }

        $io->brandMuted(sprintf('  v%s · https://github.com/stefpe/sp-crap', Application::version()));
        $io->writeln('');
    }
}
