<?php

declare(strict_types=1);

namespace SpCrap;

final class ConfigLoader
{
    private const string DEFAULT_FILENAME = '.crap-checker.json';

    public static function discoverPath(?string $explicitPath, string $workingDirectory): ?string
    {
        if ($explicitPath !== null) {
            return $explicitPath;
        }

        $dir = $workingDirectory;

        while (true) {
            $candidate = $dir . '/' . self::DEFAULT_FILENAME;

            if (is_file($candidate)) {
                return $candidate;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    public function load(string $path): Config
    {
        if (!is_readable($path)) {
            throw new ConfigException(sprintf('Config not found or not readable: %s', $path));
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new ConfigException(sprintf('Failed to read config: %s', $path));
        }

        try {
            /** @var array<string, mixed> $data */
            $data = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ConfigException(sprintf('Invalid JSON in config %s: %s', $path, $e->getMessage()), previous: $e);
        }

        if (!is_array($data)) {
            throw new ConfigException(sprintf('Config root must be a JSON object: %s', $path));
        }

        return Config::fromArray($data);
    }
}

final class ConfigException extends \RuntimeException
{
}
