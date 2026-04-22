<?php

/**
 * PHPUnit bootstrap — strips production env vars before Laravel loads.
 *
 * Why this file exists:
 * Docker-compose injects DB_CONNECTION=pgsql etc. as real OS env vars into
 * backend-app-1 so the live app talks to Postgres. Those same vars are
 * present in $_SERVER when tests run. Laravel's env() reads $_SERVER via
 * phpdotenv's immutable repository — so phpunit.xml's <env force="true">
 * entries are overridden by the already-set $_SERVER values, and tests
 * end up hitting the PRODUCTION Postgres database. RefreshDatabase then
 * wipes prod data.
 *
 * Fix: strip these vars from $_SERVER / $_ENV / putenv before autoload,
 * letting phpunit.xml's <env> entries set the test values cleanly.
 *
 * If you add new DB_* or external-service creds to docker-compose.yml,
 * add them here too — otherwise tests can leak into prod state.
 */

$sensitiveEnvVars = [
    // Database — the critical one. Tests must use sqlite :memory:.
    'DB_CONNECTION',
    'DB_HOST',
    'DB_PORT',
    'DB_DATABASE',
    'DB_USERNAME',
    'DB_PASSWORD',
    'DB_URL',

    // External services — tests must never dial real APIs.
    'ANTHROPIC_API_KEY',
    'FIREWORKS_API_KEY',
    'FIREWORKS_URL',
    'OPENAI_API_KEY',
    'TELEGRAM_BOT_TOKEN',
    'TELEGRAM_CHAT_ID',

    // Queue / broadcast — avoid hitting the real Redis during unit tests.
    'QUEUE_CONNECTION',
    'BROADCAST_CONNECTION',
];

foreach ($sensitiveEnvVars as $name) {
    unset($_SERVER[$name]);
    unset($_ENV[$name]);
    putenv($name);
}

// Seed with the sqlite defaults so phpdotenv (immutable mode) locks them in
// even if the <env force> phase runs later. This is the belt that backs up the
// suspenders: if PHPUnit for any reason skips force, we still have sqlite.
$_SERVER['DB_CONNECTION'] = 'sqlite';
$_SERVER['DB_DATABASE']   = ':memory:';
$_ENV['DB_CONNECTION']    = 'sqlite';
$_ENV['DB_DATABASE']      = ':memory:';
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');

require __DIR__ . '/../vendor/autoload.php';
