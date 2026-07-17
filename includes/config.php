<?php

// small env accessor mirroring db/database.php
// local dev reads .env directly production falls back to real env vars
function appConfig($key, $default = null) {
    static $env = null;

    if ($env === null) {
        $envPath = __DIR__ . '/../.env';
        $env = file_exists($envPath) ? (parse_ini_file($envPath) ?: []) : [];
    }

    if (array_key_exists($key, $env) && $env[$key] !== '') {
        return $env[$key];
    }

    $fromEnv = getenv($key);
    if ($fromEnv !== false && $fromEnv !== '') {
        return $fromEnv;
    }

    return $default;
}
