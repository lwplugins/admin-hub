<?php
/**
 * Copy the LW Plugins hub into a host plugin. Run `php bin/sync.php --help`.
 *
 * @package LightweightPlugins\AdminHub
 */

declare(strict_types=1);

namespace LightweightPlugins\AdminHub\Tools\Sync;

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

require dirname( __DIR__ ) . '/vendor/autoload.php';

global $argv;

exit( Command::run( $argv ) );
