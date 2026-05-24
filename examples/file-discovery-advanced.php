<?php

use Render\IncludeFile;

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Build a custom reusable discovery function around IncludeFile.
function getEntries ( array $config ): \Generator
{
	$config = array_merge( [
		'patterns' => [ '*' ],
		'cwd'      => getcwd(),
		'filter'   => TRUE,
		'maxDepth' => 25,
		'flags'    => NULL,
		'mode'     => NULL,
	], $config );

	if ( !IncludeFile::is_absolute_path( $config['cwd'] ) ) {
		$config['cwd'] = IncludeFile::add_trailing_slash( getcwd() ) . IncludeFile::strip_preceding_slash( $config['cwd'] ?? NULL ?: '' );
	}

	$includeFiles = new IncludeFile( $config['patterns'] );

	$baseDir = IncludeFile::add_trailing_slash( IncludeFile::normalize( $config['cwd'] ) );

	$filter = FALSE;
	if ( is_callable( $config['filter'] ) ) {
		$includeDirs = IncludeFile::get_terminating_directory_instance( $includeFiles->patterns );
		$filter      = fn( $fileInfo, $absPath ) => $config['filter']( $fileInfo, $absPath, $baseDir, $includeFiles, $includeDirs );
	}
	elseif ( $config['filter'] ) {
		$filter = $includeFiles->getFilter( $baseDir );
	}

	return IncludeFile::get_files( $baseDir, array_filter( [
		'filter'   => $filter,
		'maxDepth' => $config['maxDepth'],
		'flags'    => $config['flags'],
		'mode'     => $config['mode'],
	], fn( $v ) => isset( $v ) ) );
}

$files = getEntries( [
	'cwd'      => '..',
	'patterns' => [ '**/*.php', '!.git', '!node_modules', '!tests', '!vendor' ],
	'filter'   => function ( $fileInfo, $absPath, $baseDir, $includeFiles, $includeDirs ): bool {
		$relPath = IncludeFile::strip_base( $absPath, $baseDir );

		if ( is_dir( $absPath ) ) { // circuit-break if pattern is a terminal dir
			return $includeDirs ? $includeDirs->includes( $relPath ) : TRUE;
		}

		// Add cheap pre-check before regex-backed include matching when the caller knows the target shape.
		if ( !str_ends_with( strtolower( $relPath ), '.php' ) ) {
			return FALSE;
		}

		return $includeFiles->includes( $relPath );
	},
] );

$entries = iterator_to_array( $files );

echo implode( "\n", $entries );
printf( "\n\n\nFound %d matching files\n\n", count( $entries ) );
