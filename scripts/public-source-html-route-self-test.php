#!/usr/bin/env php
<?php

require_once __DIR__ . '/../includes/public_route_compatibility_helpers.php';

$assertions = 0;
$failures = 0;

$assertSame = static function ($expected, $actual, $label) use (&$assertions, &$failures) {
    $assertions++;
    if ($expected !== $actual) {
        $failures++;
        fwrite(STDERR, sprintf("FAIL: %s (expected %s, got %s)\n", $label, var_export($expected, true), var_export($actual, true)));
        return;
    }
    fwrite(STDOUT, "PASS: $label\n");
};

$assertSame('contacto', red_public_route_article_alias('contacto.html', 2), 'root HTML route resolves to its editor-safe alias');
$assertSame('escuela-canto', red_public_route_article_alias('escuela-canto.HTML', 2), 'HTML suffix matching is case insensitive');
$assertSame('', red_public_route_article_alias('index.html', 2), 'index.html resolves to the homepage');
$assertSame('contacto.html', red_public_route_article_alias('contacto.html', 3), 'nested HTML paths keep their literal article segment');
$assertSame('contacto.php', red_public_route_article_alias('contacto.php', 2), 'non-HTML suffixes remain unchanged');
$assertSame('bad.name.html', red_public_route_article_alias('bad.name.html', 2), 'unsupported dotted aliases remain unchanged');
$assertSame(
    '/clases-de-musica/',
    red_public_route_legacy_canonical_path('/clases-de-musica.html', 'adriana-granobles'),
    'Adriana parent HTML route resolves to its canonical Section URL'
);
$assertSame(
    '/clases-de-musica/canto',
    red_public_route_legacy_canonical_path('/escuela-canto.html', 'adriana-granobles'),
    'Adriana child HTML route resolves to its canonical nested Article URL'
);
$assertSame(
    '/voz-y-transformacion/',
    red_public_route_legacy_canonical_path('/canto.html', 'adriana-granobles'),
    'Voz y Transformacion resolves to its canonical Section URL'
);
$assertSame(
    null,
    red_public_route_legacy_canonical_path('/escuela-canto.html', 'starter-reference'),
    'legacy HTML redirects remain inactive for unrelated themes'
);
$assertSame(
    null,
    red_public_route_legacy_canonical_path('/not-mapped.html', 'adriana-granobles'),
    'unknown Adriana HTML routes remain unmatched'
);

if ($failures > 0) {
    fwrite(STDERR, sprintf("%d of %d source HTML route assertions failed.\n", $failures, $assertions));
    exit(1);
}

fwrite(STDOUT, sprintf("All %d source HTML route assertions passed.\n", $assertions));
