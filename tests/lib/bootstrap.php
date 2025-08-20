<?php
declare(strict_types=1);

/**
 * bootstrap.php
 * - Minimal DokuWiki stubs and plugin loader.
 */

// -----------------------------
// Minimal DokuWiki stubs (enough to load syntax.php)
// -----------------------------
if (!class_exists('DokuWiki_Syntax_Plugin')) { class DokuWiki_Syntax_Plugin {} }
if (!class_exists('Doku_Renderer')) { class Doku_Renderer { public string $doc = ''; } }
if (!class_exists('Doku_Handler')) { class Doku_Handler {} }
if (!function_exists('p_render')) { function p_render($mode, $instructions, &$info) { return $instructions; } }
if (!function_exists('p_get_instructions')) { function p_get_instructions($content) { return $content; } }
if (!function_exists('plugin_load_config')) { function plugin_load_config($plugin) { return []; } }
if (!function_exists('dbglog')) { function dbglog($string) { return null; } }

// -----------------------------
// Load plugin under test
// -----------------------------
define('DOKU_INC', true);
$syntaxPath = __DIR__ . '/../../syntax.php';
if (!file_exists($syntaxPath)) {
    fwrite(STDERR, "FATAL: Could not find syntax.php at $syntaxPath\n");
    exit(2);
}
require_once $syntaxPath;

// Create the plugin instance (class must exist in syntax.php)
if (!class_exists('syntax_plugin_ifday')) {
    fwrite(STDERR, "FATAL: Class 'syntax_plugin_ifday' not found after including syntax.php.\n");
    exit(2);
}
$plugin = new syntax_plugin_ifday();
