<?php

declare(strict_types=1);

namespace SpCrap;

final class Crap4jParser
{
    /**
     * @return list<MethodMetric>
     */
    public function parse(string $path): array
    {
        if (!is_readable($path)) {
            throw new ReportNotFoundException(sprintf('Report not found: %s', $path));
        }

        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_file($path);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($xml === false || $errors !== []) {
            throw new InvalidReportException(sprintf('Invalid XML report: %s', $path));
        }

        $methods = [];

        foreach ($xml->methods->method ?? [] as $method) {
            $name = (string) $method->methodName;
            $crapRaw = (string) $method->crap;

            if ($name === '' || $crapRaw === '') {
                continue;
            }

            $className = (string) $method->className;
            $complexityRaw = (string) $method->complexity;
            $coverageRaw = (string) $method->coverage;

            $file = (string) ($method->file ?? $method->fileName ?? '');
            $lineRaw = (string) ($method->line ?? $method->lineNumber ?? '');

            $methods[] = new MethodMetric(
                name: $name,
                crap: (float) $crapRaw,
                className: $className !== '' ? $className : null,
                file: $file !== '' ? $file : null,
                line: $lineRaw !== '' ? (int) $lineRaw : null,
                complexity: $complexityRaw !== '' ? (int) $complexityRaw : null,
                coverage: $coverageRaw !== '' ? (float) $coverageRaw : null,
            );
        }

        return $methods;
    }
}

final class ReportNotFoundException extends \RuntimeException
{
}

final class InvalidReportException extends \RuntimeException
{
}
