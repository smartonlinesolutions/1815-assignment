<?php
// Renames the project and adds the Havenpost\ PSR-4 namespace (src/) to composer.json.
$file = $argv[1] ?? 'composer.json';

$json = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
$json['name'] = 'havenpost/app';
$json['autoload']['psr-4']['Havenpost\\'] = 'src/';

file_put_contents(
    $file,
    json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);
