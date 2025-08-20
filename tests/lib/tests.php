<?php
declare(strict_types=1);

/**
 * tests.php
 * - Loads test arrays from /tests/*.php
 */

function days_of_week(): array {
    return ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
}

function load_boolean_tests(): array {
    $path = __DIR__ . '/../tests/boolean.php';
    if (!file_exists($path)) return [];
    /** @var array $arr */
    $arr = require $path;
    return $arr;
}

function load_rendered_examples(): array {
    $path = __DIR__ . '/../tests/rendered.php';
    if (!file_exists($path)) return [];
    /** @var array $arr */
    $arr = require $path;
    return $arr;
}

function load_snapshot_sets(): array {
    $path = __DIR__ . '/../tests/snapshots.php';
    if (!file_exists($path)) return [];
    /** @var array $arr */
    $arr = require $path;
    return $arr;
}

function load_error_tests(): array {
    $path = __DIR__ . '/../tests/errors.php';
    if (!file_exists($path)) return [];
    /** @var array $arr */
    $arr = require $path;
    return $arr;
}
