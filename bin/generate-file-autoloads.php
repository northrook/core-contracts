#!/usr/bin/env php
<?php

declare(strict_types=1);

use Northrook\PhpGenerator;

require_once __DIR__ . '/../vendor/autoload.php';

$root = \dirname(__DIR__);

foreach (['constants', 'functions'] as $dir) {
    $source = "$root/php/$dir";
    $files  = \glob("$source/*.php") ?: [];
    $names  = [];

    foreach ($files as $file) {
        $basename = \basename($file, '.php');
        $names[]  = "require_once __DIR__ . '/{$dir}/{$basename}.php';";
    }

    \sort($names, SORT_STRING);

    $require_files = $names === []
        ? ''
        : \implode("\n", $names);

    $timestamp = \date('U');
    $checksum  = \Northrook\Hash::checksum($require_files);
    $generator = new PhpGenerator(
        bodyCode: $require_files,
        banner  : 'This file is auto-generated, do not edit.',
    );

    $target = "$root/php/$dir.php";

    $generator->saveToFile($target);

    echo "Generated $target (" . \count($names) . " files)\n";
}
