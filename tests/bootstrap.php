<?php
/**
 * PHPUnit bootstrap: no WordPress, only the autoloader, Brain Monkey and a
 * minimal WP_Error.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
require_once __DIR__ . '/stubs.php';
