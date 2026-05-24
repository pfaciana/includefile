<?php

/*
 * NOTE: This example shows that `IncludeFile::get_files()` is totally independent
 * of the `IncludeFile` class otherwise, and can be used in any context.
 */

use Render\IncludeFile;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$baseDir = dirname( __DIR__ );

$dirs = [
	'src',
	'examples',
];

$baseDirs = array_map( fn( $dir ) => $baseDir . '/' . $dir, $dirs );

$files = IncludeFile::get_files( $baseDirs, [
	'filterByExt' => 'php',
] );

$entries = iterator_to_array( $files );

echo implode( "\n", $entries );
printf( "\n\n\nFound %d matching files\n\n", count( $entries ) );
