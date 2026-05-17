<?php

use Render\IncludeFile;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$baseDir = dirname( __DIR__ );

$include = new IncludeFile( [
	'**/*.php',
	'!.git',
	'!node_modules',
	'!tests',
	'!vendor',
] );

$files = IncludeFile::get_files( $baseDir, [
	'filter' => $include->getDefaultCallbackFilter( $baseDir ),
] );

$entries = iterator_to_array( $files );

echo implode( "\n", $entries );
printf( "\n\n\nFound %d matching files\n\n", count( $entries ) );
