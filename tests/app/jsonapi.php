<?php

declare(strict_types=1);

/** @var array{describer: array<string, mixed>} $config */
$config = include __DIR__ . '/../../config/jsonapi.php';

$config['describer']['when-has'] = true;

return $config;
