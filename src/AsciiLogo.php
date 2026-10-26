<?php

declare(strict_types=1);

namespace SpCrap;

final class AsciiLogo
{
    /** @var list<string> */
    private const RAW_LINES = [
        ' ##### #####       ##### #####  ###  #####',
        ' #     #   #       #     #   # #   # #   #',
        ' ##### #####  ---  #     ##### ##### #####',
        '     # #           #     #  #  #   # #    ',
        ' ##### #           ##### #   # #   # #    ',
    ];

    /**
     * @return list<string>
     */
    public static function lines(): array
    {
        $out = [];

        foreach (self::RAW_LINES as $line) {
            if ($line === '' || preg_match('/^\.+$/', $line) === 1) {
                continue;
            }

            $trimmed = preg_replace('/^\.+|\.+$/', '', $line);
            if ($trimmed === null || $trimmed === '') {
                continue;
            }

            $out[] = $trimmed;
        }

        return $out;
    }

    public static function renderLine(string $line, bool $color): string
    {
        $plain = str_replace('.', ' ', $line);

        if (!$color) {
            return $plain;
        }

        $result = '';
        $length = strlen($plain);
        $index = 0;

        while ($index < $length) {
            $char = $plain[$index];
            $ansi = self::ansiForChar($char);

            if ($ansi === null) {
                $result .= $char;
                ++$index;

                continue;
            }

            $run = $char;
            $next = $index + 1;

            while ($next < $length && self::ansiForChar($plain[$next]) === $ansi) {
                $run .= $plain[$next];
                ++$next;
            }

            $result .= "\033[" . $ansi . 'm' . $run . "\033[0m";
            $index = $next;
        }

        return $result;
    }

    private static function ansiForChar(string $char): ?string
    {
        return match ($char) {
            '#' => '36',
            '-' => '35',
            default => null,
        };
    }
}
