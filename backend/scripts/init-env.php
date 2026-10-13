<?php

// Preserva valores existentes; adiciona somente a senha de demonstração ausente.
$destination = dirname(__DIR__).'/.env';

if (file_exists($destination)) {
    $existing = file_get_contents($destination);

    if ($existing === false) {
        throw new RuntimeException('Não foi possível ler o ambiente local.');
    }

    if (! preg_match('/^SEED_USER_PASSWORD=[^\r\n]+/m', $existing)) {
        $entry = 'SEED_USER_PASSWORD='.bin2hex(random_bytes(24));
        $updated = preg_match('/^SEED_USER_PASSWORD=\r?$/m', $existing)
            ? preg_replace('/^SEED_USER_PASSWORD=\r?$/m', $entry, $existing)
            : rtrim($existing).PHP_EOL.$entry.PHP_EOL;

        if (file_put_contents($destination, $updated, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível adicionar a senha local de demonstração.');
        }
    }

    fwrite(STDOUT, ".env existente preservado.\n");
    exit(0);
}

$contents = file_get_contents(dirname(__DIR__).'/.env.example');

if ($contents === false) {
    throw new RuntimeException('Não foi possível ler .env.example.');
}

$values = [
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'DB_PASSWORD' => bin2hex(random_bytes(24)),
    'DB_ROOT_PASSWORD' => bin2hex(random_bytes(24)),
    'SEED_USER_PASSWORD' => bin2hex(random_bytes(24)),
];

foreach ($values as $key => $value) {
    $contents = preg_replace('/^'.preg_quote($key, '/').'=$/m', $key.'='.$value, $contents, 1, $count);

    if ($count !== 1) {
        throw new RuntimeException('Campo ausente ou inesperado no exemplo: '.$key);
    }
}

$handle = fopen($destination, 'x');

if ($handle === false) {
    throw new RuntimeException('Não foi possível criar .env.');
}

fwrite($handle, $contents);
fclose($handle);
chmod($destination, 0600);
fwrite(STDOUT, ".env criado com credenciais locais aleatórias.\n");
