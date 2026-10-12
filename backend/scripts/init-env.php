<?php

// Bootstrap local: nunca sobrescreve uma configuração existente nem imprime segredos.
$destination = dirname(__DIR__).'/.env';

if (file_exists($destination)) {
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
