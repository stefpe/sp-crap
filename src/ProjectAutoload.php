<?php

declare(strict_types=1);

namespace SpCrap;

final class ProjectAutoload
{
    public static function registerFromWorkingDirectory(?string $workingDirectory = null): bool
    {
        $dir = $workingDirectory ?? (string) getcwd();

        while (true) {
            $autoload = $dir . '/vendor/autoload.php';

            if (is_file($autoload)) {
                require_once $autoload;

                return true;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return false;
    }
}
