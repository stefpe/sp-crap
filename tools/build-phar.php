<?php

declare(strict_types=1);

if (ini_get('phar.readonly') !== '0' && ini_get('phar.readonly') !== '') {
    fwrite(STDERR, "Run with: php -d phar.readonly=0 tools/build-phar.php\n");
    exit(1);
}

$root = dirname(__DIR__);
$out = $root . '/sp-crap-check.phar';

if (file_exists($out)) {
    unlink($out);
}

$phar = new Phar($out);
$phar->startBuffering();

$phar->addFile($root . '/sp-crap-check', 'sp-crap-check');

foreach (glob($root . '/src/*.php') ?: [] as $file) {
    $phar->addFile($file, 'src/' . basename($file));
}

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('sp-crap-check.phar');
require 'phar://sp-crap-check.phar/src/bootstrap.php';
exit(SpCrap\Application::run($_SERVER['argv'] ?? []));
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

chmod($out, 0755);

fwrite(STDOUT, "Built {$out}\n");
