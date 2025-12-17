<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/../vendor/autoload.php';

$projectRoot = dirname(__DIR__);

if (class_exists(Dotenv::class) && file_exists($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

$appUrl = getenv('APP_URL') ?: 'http://localhost';

if (!defined('L5_SWAGGER_CONST_HOST')) {
    define('L5_SWAGGER_CONST_HOST', rtrim($appUrl, '/') . '/api');
}
