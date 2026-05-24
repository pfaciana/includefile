<?php

declare( strict_types=1 );

use PHPUnit\Framework\Assert;
use Render\IncludeFile;

// ========================================================================
// Helpers
// ========================================================================

function gfFixtureDir (): string
{
	return dirname( __DIR__ ) . '/Fixtures/e2e/GetFiles/project-dir';
}

function gfNormalize ( string $path ): string
{
	return str_replace( '\\', '/', $path );
}

function gfRelative ( string $path ): string
{
	$base = gfNormalize( gfFixtureDir() ) . '/';
	$path = gfNormalize( $path );

	return str_starts_with( $path, $base ) ? substr( $path, strlen( $base ) ) : $path;
}

function gfResolve ( string $relative ): string
{
	return $relative === '' ? gfFixtureDir() : gfFixtureDir() . '/' . ltrim( $relative, '/' );
}

function gfCollect ( string|array $dirs = '', mixed $config = NULL ): array
{
	$absDirs = is_array( $dirs )
		? array_map( fn( string $d ): string => gfResolve( $d ), $dirs )
		: gfResolve( $dirs );

	$results = [];
	foreach ( IncludeFile::get_files( $absDirs, $config ) as $path => $file ) {
		$results[] = gfRelative( (string) $path );
	}

	sort( $results );

	return $results;
}

function gfFmt ( mixed $v ): string
{
	if ( is_array( $v ) ) {
		return '[' . implode( ',', array_map( 'gfFmt', $v ) ) . ']';
	}
	if ( is_string( $v ) ) {
		return "'{$v}'";
	}
	if ( is_null( $v ) ) {
		return 'null';
	}
	if ( is_bool( $v ) ) {
		return $v ? 'true' : 'false';
	}
	if ( is_object( $v ) ) {
		return 'object(' . $v::class . ')';
	}

	return (string) $v;
}

function gfDump ( array $results, int $limit = 4 ): string
{
	$n = count( $results );
	if ( $n === 0 ) {
		return '[empty]';
	}
	if ( $n <= $limit ) {
		return '[' . implode( ',', $results ) . ']';
	}

	return '[' . implode( ',', array_slice( $results, 0, $limit ) ) . ",...+" . ( $n - $limit ) . ']';
}

function gfCheck ( array $result, string $label, int $count, array $contains, array $excludes ): void
{
	$n = count( $result );

	Assert::assertCount(
		$count,
		$result,
		"[{$label}] count {$count} != {$n} - GOT " . gfDump( $result ),
	);

	foreach ( $contains as $path ) {
		Assert::assertContains(
			$path,
			$result,
			"[{$label}] missing '{$path}' - GOT " . gfDump( $result ),
		);
	}

	foreach ( $excludes as $path ) {
		Assert::assertNotContains(
			$path,
			$result,
			"[{$label}] unexpected '{$path}' - GOT " . gfDump( $result ),
		);
	}
}

function gfRun ( string $dir, mixed $config, int $count, array $contains, array $excludes ): void
{
	$label  = "dir='{$dir}' cfg=" . gfFmt( $config );
	$result = gfCollect( $dir, $config );
	gfCheck( $result, $label, $count, $contains, $excludes );
}

// ========================================================================
// Exceptions
// ========================================================================

describe( 'get_files - exceptions', function () {

	it( 'throws InvalidArgumentException for non-existent paths', function ( string|array $input ) {
		$resolved = is_array( $input ) ? array_map( fn( string $d ) => gfResolve( $d ), $input ) : gfResolve( $input );
		iterator_to_array( IncludeFile::get_files( $resolved ) );
	} )->with( [
		'non-existent string'           => [ 'does-not-exist' ],
		'non-existent deep path'        => [ 'src/does/not/exist' ],
		'one missing in array (first)'  => [ [ 'missing-dir', 'src' ] ],
		'one missing in array (last)'   => [ [ 'src', 'missing-dir' ] ],
		'one missing in array (middle)' => [ [ 'src', 'missing-dir', 'lib' ] ],
		'all missing in array'          => [ [ 'missing-1', 'missing-2' ] ],
	] )->throws( InvalidArgumentException::class, 'Not a directory' );

	it( 'throws InvalidArgumentException when given a file path', function ( string $input ) {
		iterator_to_array( IncludeFile::get_files( gfResolve( $input ) ) );
	} )->with( [
		'LICENSE (no ext)'          => [ 'LICENSE' ],
		'composer.json (json file)' => [ 'composer.json' ],
		'no-extension (no ext)'     => [ 'no-extension' ],
		'UPPERCASE.PHP'             => [ 'UPPERCASE.PHP' ],
		'index.php'                 => [ 'index.php' ],
		'hidden .bootstrap.php'     => [ '.bootstrap.php' ],
	] )->throws( InvalidArgumentException::class, 'Not a directory' );

} );

// ========================================================================
// Empty result cases
// ========================================================================

describe( 'get_files - yields nothing', function () {

	it( 'yields nothing for empty array of directories', function () {
		$result = gfCollect( [] );
		expect( $result )->toBe( [], 'empty dirs array must yield zero files, got ' . gfDump( $result ) );
	} );

	it( 'yields nothing for an empty directory', function () {
		$tmp = gfFixtureDir() . '/__empty_test_dir__';
		if ( !is_dir( $tmp ) ) {
			mkdir( $tmp );
		}
		try {
			$results = [];
			foreach ( IncludeFile::get_files( $tmp ) as $path => $file ) {
				$results[] = gfRelative( (string) $path );
			}
			expect( $results )->toBe( [], 'empty directory must yield zero files, got ' . gfDump( $results ) );
		}
		finally {
			@rmdir( $tmp );
		}
	} );

	it( 'yields zero files for string filterByExt with no matches', function ( string $dir, string $config ) {
		$label  = "dir='{$dir}' cfg=" . gfFmt( $config );
		$result = gfCollect( $dir, $config );
		expect( $result )->toBe( [], "[{$label}] expected empty - GOT " . gfDump( $result ) );
	} )->with( [
		"'xyz' nonexistent ext"          => [ '', 'xyz' ],
		"'no-such-ext' nonexistent"      => [ '', 'no-such-ext' ],
		"'foo.bar' compound no match"    => [ '', 'foo.bar' ],
		"'xml' in src (no xml in src)"   => [ 'src', 'xml' ],
		"'json' in src/Admin (no json)"  => [ 'src/Admin', 'json' ],
		"'bak' in src (no bak in src)"   => [ 'src', 'bak' ],
		"'lock' in lib (no lock in lib)" => [ 'lib', 'lock' ],
		"'neon' in inc (no neon)"        => [ 'inc', 'neon' ],
	] );

	it( 'yields zero files for array filterByExt with no matches (new feature)', function ( string $dir, array $config ) {
		$label  = "dir='{$dir}' cfg=" . gfFmt( $config );
		$result = gfCollect( $dir, $config );
		expect( $result )->toBe( [], "[{$label}] expected empty - GOT " . gfDump( $result ) );
	} )->with( [
		"['xyz']"               => [ '', [ 'xyz' ] ],
		"['xyz','abc']"         => [ '', [ 'xyz', 'abc' ] ],
		"[123] (cast to '123')" => [ 'src/Admin', [ 123 ] ],
		"[true] (cast to '1')"  => [ 'src/Admin', [ TRUE ] ],
		"['xml'] in src/Admin"  => [ 'src/Admin', [ 'xml' ] ],
		"['xml','neon'] in lib" => [ 'lib', [ 'xml', 'neon' ] ],
	] );

} );

// ========================================================================
// filterByExt (string form) — simple extension matching
// ========================================================================

describe( 'get_files - filterByExt (string form) — simple extension', function () {

	it( "'php' matches files via getExtension() across directories", function ( string $dir, string $ext, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $ext, $count, $contains, $excludes );
	} )->with( [
		"'php' in src/Helpers (2)"         => [ 'src/Helpers', 'php', 2, [ 'src/Helpers/arrays.inc.php', 'src/Helpers/functions.php' ], [] ],
		"'php' in src/Admin (2)"           => [ 'src/Admin', 'php', 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] ],
		"'php' in src/Deep (3)"            => [ 'src/Deep', 'php', 3, [ 'src/Deep/Nested/Nested/File.php', 'src/Deep/Nested/Nested/Nested/File.php', 'src/Deep/Nested/Nested/Nested/Path/File.php' ], [] ],
		"'php' in src (11, excludes .md)"  => [ 'src', 'php', 11, [ 'src/.hidden.php', 'src/Plugin.php', 'src/bootstrap.php', 'src/Helpers/arrays.inc.php' ], [ 'src/README.md' ] ],
		"'php' in lib (1)"                 => [ 'lib', 'php', 1, [ 'lib/Legacy.php' ], [] ],
		"'php' in inc (2, excludes .json)" => [ 'inc', 'php', 2, [ 'inc/setup.php', 'inc/sub/extra.php' ], [ 'inc/config.json' ] ],
		"'php' in build (1)"               => [ 'build', 'php', 1, [ 'build/compiled.php' ], [] ],
		"'php' in cache (1)"               => [ 'cache', 'php', 1, [ 'cache/templates.php' ], [] ],
		"'php' in tests (1)"               => [ 'tests', 'php', 1, [ 'tests/PluginTest.php' ], [] ],
		"'php' in vendor (1)"              => [ 'vendor', 'php', 1, [ 'vendor/acme/utils/src/Helper.php' ], [] ],
		"'php' in node_modules (1)"        => [ 'node_modules', 'php', 1, [ 'node_modules/wp-scripts/php/loader.php' ], [ 'node_modules/.package-lock.json' ] ],
		"'php' at root (24, full tree)"    => [
			'',
			'php',
			24,
			[
				'.bootstrap.php',
				'.git/hooks/pre-commit.php',
				'UPPERCASE.PHP',
				'build/compiled.php',
				'cache/templates.php',
				'config.php',
				'inc/setup.php',
				'inc/sub/extra.php',
				'index.php',
				'lib/Legacy.php',
				'node_modules/wp-scripts/php/loader.php',
				'src/.hidden.php',
				'src/Admin/Settings.php',
				'src/Admin/ajax-handler.php',
				'src/Deep/Nested/Nested/File.php',
				'src/Deep/Nested/Nested/Nested/File.php',
				'src/Deep/Nested/Nested/Nested/Path/File.php',
				'src/Excluded/debug.php',
				'src/Helpers/arrays.inc.php',
				'src/Helpers/functions.php',
				'src/Plugin.php',
				'src/bootstrap.php',
				'tests/PluginTest.php',
				'vendor/acme/utils/src/Helper.php',
			],
			[ 'file.php.bak', 'README.md', 'LICENSE', 'composer.json' ],
		],
	] );

	it( 'matches non-php extensions across directories', function ( string $dir, string $ext, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $ext, $count, $contains, $excludes );
	} )->with( [
		"'json' at root (5)"         => [ '', 'json', 5, [ 'composer.json', 'package.json', 'package-lock.json', 'inc/config.json', 'node_modules/.package-lock.json' ], [ 'index.php', 'composer.lock' ] ],
		"'json' in inc (1)"          => [ 'inc', 'json', 1, [ 'inc/config.json' ], [ 'inc/setup.php' ] ],
		"'json' in node_modules (1)" => [ 'node_modules', 'json', 1, [ 'node_modules/.package-lock.json' ], [ 'node_modules/wp-scripts/php/loader.php' ] ],
		"'bak' at root (1)"          => [ '', 'bak', 1, [ 'file.php.bak' ], [ 'index.php', 'composer.json' ] ],
		"'lock' at root (1)"         => [ '', 'lock', 1, [ 'composer.lock' ], [ 'composer.json', 'package-lock.json' ] ],
		"'md' at root (2)"           => [ '', 'md', 2, [ 'README.md', 'src/README.md' ], [ 'index.php' ] ],
		"'neon' at root (1)"         => [ '', 'neon', 1, [ 'phpstan.neon' ], [] ],
		"'xml' at root (1)"          => [ '', 'xml', 1, [ 'phpunit.xml' ], [] ],
		"'editorconfig' at root (1)" => [ '', 'editorconfig', 1, [ '.editorconfig' ], [] ],
		"'gitignore' at root (1)"    => [ '', 'gitignore', 1, [ '.gitignore' ], [] ],
		"'example' at root (1)"      => [ '', 'example', 1, [ '.env.example' ], [] ],
	] );

} );

// ========================================================================
// filterByExt (string form) — compound extension matching (suffix)
// ========================================================================

describe( 'get_files - filterByExt (string form) — compound extension', function () {

	it( 'matches compound suffixes (filter contains a dot)', function ( string $dir, string $ext, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $ext, $count, $contains, $excludes );
	} )->with( [
		"'inc.php' in src (1)"          => [ 'src', 'inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php', 'src/Plugin.php' ] ],
		"'inc.php' at root (1)"         => [ '', 'inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [ 'index.php' ] ],
		"'.inc.php' in src (1)"         => [ 'src', '.inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [] ],
		"'.inc.php' in src/Helpers (1)" => [ 'src/Helpers', '.inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] ],
		"'php.bak' at root (1)"         => [ '', 'php.bak', 1, [ 'file.php.bak' ], [ 'index.php' ] ],
		"'.php.bak' at root (1)"        => [ '', '.php.bak', 1, [ 'file.php.bak' ], [] ],
	] );

	it( "'php' does NOT match backup files (.bak)", function () {
		$result = gfCollect( '', 'php' );

		Assert::assertNotContains( 'file.php.bak', $result, "'php' must not match file.php.bak - GOT " . gfDump( $result ) );
		Assert::assertContains( 'index.php', $result, "'php' must match index.php" );
	} );

} );

// ========================================================================
// filterByExt (string form) — normalization: variants of 'php'
// ========================================================================

describe( 'get_files - filterByExt (string form) — normalization for "php"', function () {

	it( 'treats php-like inputs as canonical "php"', function ( string $input ) {
		gfRun( 'src/Admin', $input, 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] );
	} )->with( [
		"'.php'"               => [ '.php' ],
		"'*.php'"              => [ '*.php' ],
		"'**.php'"             => [ '**.php' ],
		"'***.php'"            => [ '***.php' ],
		"'****.php'"           => [ '****.php' ],
		"'..php'"              => [ '..php' ],
		"'...php'"             => [ '...php' ],
		"'....php'"            => [ '....php' ],
		"'*..php'"             => [ '*..php' ],
		"'*.*.php'"            => [ '*.*.php' ],
		"'**..**.php'"         => [ '**..**.php' ],
		"'*****.....****.php'" => [ '*****.....****.php' ],
	] );

} );

// ========================================================================
// filterByExt (string form) — normalization: variants of 'inc.php'
// ========================================================================

describe( 'get_files - filterByExt (string form) — normalization for "inc.php"', function () {

	it( 'treats inc.php-like inputs as canonical ".inc.php"', function ( string $input ) {
		gfRun( 'src/Helpers', $input, 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] );
	} )->with( [
		"'.inc.php'"    => [ '.inc.php' ],
		"'*.inc.php'"   => [ '*.inc.php' ],
		"'**.inc.php'"  => [ '**.inc.php' ],
		"'*..inc.php'"  => [ '*..inc.php' ],
		"'***.inc.php'" => [ '***.inc.php' ],
		"'.*.inc.php'"  => [ '.*.inc.php' ],
	] );

} );

// ========================================================================
// filterByExt (string form) — normalization: variants of 'json'
// ========================================================================

describe( 'get_files - filterByExt (string form) — normalization for "json"', function () {

	it( 'treats json-like inputs as canonical "json"', function ( string $input ) {
		gfRun( 'inc', $input, 1, [ 'inc/config.json' ], [ 'inc/setup.php' ] );
	} )->with( [
		"'.json'"    => [ '.json' ],
		"'*.json'"   => [ '*.json' ],
		"'**.json'"  => [ '**.json' ],
		"'***.json'" => [ '***.json' ],
		"'..json'"   => [ '..json' ],
	] );

} );

// ========================================================================
// filterByExt (string form) — case insensitivity
// ========================================================================

describe( 'get_files - filterByExt (string form) — case insensitivity', function () {

	it( 'matches php files case-insensitively (filter side)', function ( string $input ) {
		gfRun( 'src/Admin', $input, 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] );
	} )->with( [
		"'PHP' uppercase"      => [ 'PHP' ],
		"'Php' capitalized"    => [ 'Php' ],
		"'pHp' mixed"          => [ 'pHp' ],
		"'pHP' mixed"          => [ 'pHP' ],
		"'PHp' mixed"          => [ 'PHp' ],
		"'.PHP' uppercase dot" => [ '.PHP' ],
		"'*.PHP' uppercase"    => [ '*.PHP' ],
	] );

	it( 'matches uppercase filenames from lowercase filter (filename side)', function ( string $input ) {
		$result = gfCollect( '', $input );
		Assert::assertContains( 'UPPERCASE.PHP', $result, "filter '{$input}' must match UPPERCASE.PHP, GOT: " . gfDump( $result ) );
		Assert::assertContains( 'index.php', $result, "filter '{$input}' must also match lowercase index.php, GOT: " . gfDump( $result ) );
	} )->with( [
		"'php' lowercase"  => [ 'php' ],
		"'PHP' uppercase"  => [ 'PHP' ],
		"'Php' mixed"      => [ 'Php' ],
		"'.php' lowercase" => [ '.php' ],
		"'*.PHP'"          => [ '*.PHP' ],
	] );

	it( 'matches compound extensions case-insensitively', function ( string $input ) {
		gfRun( 'src/Helpers', $input, 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] );
	} )->with( [
		"'.INC.PHP' uppercase" => [ '.INC.PHP' ],
		"'.Inc.Php' mixed"     => [ '.Inc.Php' ],
		"'.inc.PHP' mixed"     => [ '.inc.PHP' ],
		"'.INC.php' mixed"     => [ '.INC.php' ],
		"'INC.PHP' no-dot"     => [ 'INC.PHP' ],
		"'Inc.Php' no-dot"     => [ 'Inc.Php' ],
		"'*.INC.PHP' wildcard" => [ '*.INC.PHP' ],
	] );

	it( 'matches misc extensions case-insensitively', function ( string $dir, string $ext, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $ext, $count, $contains, $excludes );
	} )->with( [
		"'JSON' uppercase"     => [ 'inc', 'JSON', 1, [ 'inc/config.json' ], [] ],
		"'Json' capitalized"   => [ 'inc', 'Json', 1, [ 'inc/config.json' ], [] ],
		"'MD' uppercase"       => [ '', 'MD', 2, [ 'README.md', 'src/README.md' ], [] ],
		"'BAK' uppercase"      => [ '', 'BAK', 1, [ 'file.php.bak' ], [] ],
		"'LOCK' uppercase"     => [ '', 'LOCK', 1, [ 'composer.lock' ], [] ],
		"'XML' uppercase"      => [ '', 'XML', 1, [ 'phpunit.xml' ], [] ],
		"'NEON' uppercase"     => [ '', 'NEON', 1, [ 'phpstan.neon' ], [] ],
		"'EDITORCONFIG' upper" => [ '', 'EDITORCONFIG', 1, [ '.editorconfig' ], [] ],
		"'GITIGNORE' upper"    => [ '', 'GITIGNORE', 1, [ '.gitignore' ], [] ],
		"'EXAMPLE' upper"      => [ '', 'EXAMPLE', 1, [ '.env.example' ], [] ],
	] );

} );

// ========================================================================
// filterByExt (string form) — extensionless filtering (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (string form) — extensionless (new feature)', function () {

	it( 'normalizes to empty and yields only extensionless files', function ( string $input ) {
		gfRun( '', $input, 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php', 'README.md', 'composer.json', '.bootstrap.php' ] );
	} )->with( [
		"'' (empty string)" => [ '' ],
		"'.' (single dot)"  => [ '.' ],
		"'*.'"              => [ '*.' ],
		"'**'"              => [ '**' ],
	] );

	it( 'yields zero files in directories without extensionless files', function ( string $dir, string $input ) {
		gfRun( $dir, $input, 0, [], [] );
	} )->with( [
		"'' in src"          => [ 'src', '' ],
		"'.' in src"         => [ 'src', '.' ],
		"'*.' in src"        => [ 'src', '*.' ],
		"'**' in src"        => [ 'src', '**' ],
		"'' in lib"          => [ 'lib', '' ],
		"'.' in lib"         => [ 'lib', '.' ],
		"'*.' in lib"        => [ 'lib', '*.' ],
		"'**' in lib"        => [ 'lib', '**' ],
		"'' in inc"          => [ 'inc', '' ],
		"'' in src/Admin"    => [ 'src/Admin', '' ],
		"'' in src/Helpers"  => [ 'src/Helpers', '' ],
		"'' in node_modules" => [ 'node_modules', '' ],
	] );

} );

// ========================================================================
// filterByExt — explicit config form (array with 'filterByExt' key)
// ========================================================================

describe( 'get_files - filterByExt explicit config', function () {

	it( "['filterByExt' => ...] behaves identically to shorthand string", function ( string $dir, mixed $extValue, int $count, array $contains, array $excludes ) {
		gfRun( $dir, [ 'filterByExt' => $extValue ], $count, $contains, $excludes );
	} )->with( [
		"explicit 'php'"             => [ 'src/Admin', 'php', 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] ],
		"explicit '.php'"            => [ 'src/Admin', '.php', 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] ],
		"explicit '*.php'"           => [ 'src/Admin', '*.php', 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] ],
		"explicit 'inc.php'"         => [ 'src/Helpers', 'inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] ],
		"explicit '' (ext-less new)" => [ '', '', 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php' ] ],
		"explicit 'json'"            => [ 'inc', 'json', 1, [ 'inc/config.json' ], [] ],
	] );

	it( "['filterByExt' => ...] disables extension filtering for unsupported values", function ( mixed $extValue ) {
		gfRun(
			'',
			[ 'filterByExt' => $extValue ],
			41,
			[ 'index.php', 'LICENSE', 'composer.json', 'file.php.bak', 'README.md', 'UPPERCASE.PHP', '.bootstrap.php' ],
			[],
		);
	} )->with( [
		'explicit null'   => [ NULL ],
		'explicit false'  => [ FALSE ],
		'explicit true'   => [ TRUE ],
		'explicit 0'      => [ 0 ],
		'explicit 123'    => [ 123 ],
		'explicit object' => [ new stdClass() ],
	] );

} );

// ========================================================================
// filterByExt (array form) — OR logic (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — OR logic (new feature)', function () {

	it( 'returns the union of files matched by each array element', function ( string $dir, array $config, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $config, $count, $contains, $excludes );
	} )->with( [
		"['php','bak'] at root (25)"        => [ '', [ 'php', 'bak' ], 25, [ 'index.php', 'file.php.bak', 'UPPERCASE.PHP' ], [ 'composer.json', 'README.md', 'LICENSE' ] ],
		"['php','json'] at root (29)"       => [ '', [ 'php', 'json' ], 29, [ 'composer.json', 'index.php', 'package-lock.json' ], [ 'file.php.bak', 'README.md', 'LICENSE' ] ],
		"['php','md'] at root (26)"         => [ '', [ 'php', 'md' ], 26, [ 'index.php', 'README.md', 'src/README.md' ], [ 'composer.json', 'LICENSE' ] ],
		"['json','md'] at root (7)"         => [ '', [ 'json', 'md' ], 7, [ 'composer.json', 'README.md', 'src/README.md' ], [ 'index.php', 'LICENSE' ] ],
		"['php','json','md'] at root (31)"  => [ '', [ 'php', 'json', 'md' ], 31, [ 'index.php', 'composer.json', 'README.md' ], [ 'LICENSE', 'file.php.bak' ] ],
		"['xml','neon'] at root (2)"        => [ '', [ 'xml', 'neon' ], 2, [ 'phpstan.neon', 'phpunit.xml' ], [ 'index.php' ] ],
		"['php','bak','lock'] at root (26)" => [ '', [ 'php', 'bak', 'lock' ], 26, [ 'index.php', 'file.php.bak', 'composer.lock' ], [ 'composer.json' ] ],
		"['php','php','PHP'] dedup (24)"    => [ '', [ 'php', 'php', 'PHP' ], 24, [ 'index.php', 'UPPERCASE.PHP' ], [ 'file.php.bak' ] ],
	] );

} );

// ========================================================================
// filterByExt (array form) — mixed simple + compound (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — mixed simple+compound', function () {

	it( 'matches mixed simple and compound extensions in same array', function ( string $dir, array $config, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $config, $count, $contains, $excludes );
	} )->with( [
		"['inc.php','json'] in src/Helpers (1)" => [ 'src/Helpers', [ 'inc.php', 'json' ], 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] ],
		"['inc.php','json'] at root (6)"        => [ '', [ 'inc.php', 'json' ], 6, [ 'src/Helpers/arrays.inc.php', 'composer.json', 'package.json' ], [ 'src/Helpers/functions.php' ] ],
		"['.inc.php','.json'] at root (6)"      => [ '', [ '.inc.php', '.json' ], 6, [ 'src/Helpers/arrays.inc.php', 'composer.json' ], [ 'index.php' ] ],
		"['php.bak','json'] at root (6)"        => [ '', [ 'php.bak', 'json' ], 6, [ 'file.php.bak', 'composer.json' ], [ 'index.php', 'src/Helpers/arrays.inc.php' ] ],
		"['.php.bak','.inc.php'] at root (2)"   => [ '', [ '.php.bak', '.inc.php' ], 2, [ 'file.php.bak', 'src/Helpers/arrays.inc.php' ], [ 'index.php' ] ],
	] );

} );

// ========================================================================
// filterByExt (array form) — normalization per element (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — normalization per element', function () {

	it( 'normalizes each array element independently', function ( string $dir, array $config, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $config, $count, $contains, $excludes );
	} )->with( [
		"['*.php','.json'] in inc (3)"          => [ 'inc', [ '*.php', '.json' ], 3, [ 'inc/setup.php', 'inc/sub/extra.php', 'inc/config.json' ], [] ],
		"['**.php','*.json'] in inc (3)"        => [ 'inc', [ '**.php', '*.json' ], 3, [ 'inc/setup.php', 'inc/sub/extra.php', 'inc/config.json' ], [] ],
		"['*.inc.php','.json'] in src (1)"      => [ 'src', [ '*.inc.php', '.json' ], 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php', 'src/Plugin.php' ] ],
		"['.PHP','.JSON','.MD'] uppercase (31)" => [ '', [ '.PHP', '.JSON', '.MD' ], 31, [ 'index.php', 'composer.json', 'README.md', 'UPPERCASE.PHP' ], [ 'LICENSE', 'file.php.bak' ] ],
		"['*.Bak','*.LoCk'] mixed case (2)"     => [ '', [ '*.Bak', '*.LoCk' ], 2, [ 'file.php.bak', 'composer.lock' ], [ 'composer.json' ] ],
		"['**.inc.php','*..json'] in src (1)"   => [ 'src', [ '**.inc.php', '*..json' ], 1, [ 'src/Helpers/arrays.inc.php' ], [] ],
	] );

} );

// ========================================================================
// filterByExt (array form) — extensionless inclusion (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — extensionless inclusion', function () {

	it( 'array containing empty string yields extensionless files alongside others', function ( string $dir, array $config, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $config, $count, $contains, $excludes );
	} )->with( [
		"['php',''] at root (27)"       => [ '', [ 'php', '' ], 27, [ 'index.php', 'LICENSE', 'Makefile', 'no-extension', 'UPPERCASE.PHP' ], [ 'composer.json', 'README.md', 'file.php.bak' ] ],
		"['','md'] at root (5)"         => [ '', [ '', 'md' ], 5, [ 'LICENSE', 'Makefile', 'no-extension', 'README.md', 'src/README.md' ], [ 'index.php' ] ],
		"['','json'] at root (8)"       => [ '', [ '', 'json' ], 8, [ 'LICENSE', 'composer.json', 'no-extension' ], [ 'index.php' ] ],
		"['','bak'] at root (4)"        => [ '', [ '', 'bak' ], 4, [ 'LICENSE', 'Makefile', 'no-extension', 'file.php.bak' ], [ 'index.php' ] ],
		"['','php','md'] at root (29)"  => [ '', [ '', 'php', 'md' ], 29, [ 'LICENSE', 'index.php', 'README.md' ], [ 'composer.json' ] ],
		"all extensions + '' (41 full)" => [ '', [ 'php', 'json', 'md', 'xml', 'neon', 'lock', 'bak', 'editorconfig', 'example', 'gitignore', '' ], 41, [ 'index.php', 'LICENSE', 'composer.json', 'README.md', 'file.php.bak', '.editorconfig', '.gitignore' ], [] ],
	] );

} );

// ========================================================================
// filterByExt (array form) — empty array and singleton (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — empty array and singletons', function () {

	it( 'empty array [] means no filter (yields everything)', function ( string $dir, int $count, array $contains, array $excludes ) {
		gfRun( $dir, [], $count, $contains, $excludes );
	} )->with( [
		'[] at root (41 full tree)' => [ '', 41, [ 'index.php', 'LICENSE', 'composer.json', 'file.php.bak', 'README.md', 'UPPERCASE.PHP', '.bootstrap.php' ], [] ],
		'[] in src/Admin (2)'       => [ 'src/Admin', 2, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ], [] ],
		'[] in lib (1)'             => [ 'lib', 1, [ 'lib/Legacy.php' ], [] ],
		'[] in inc (3)'             => [ 'inc', 3, [ 'inc/setup.php', 'inc/sub/extra.php', 'inc/config.json' ], [] ],
		'[] in src/Helpers (2)'     => [ 'src/Helpers', 2, [ 'src/Helpers/arrays.inc.php', 'src/Helpers/functions.php' ], [] ],
	] );

	it( "[''] (singleton empty) yields only extensionless files", function ( string $dir, int $count, array $contains, array $excludes ) {
		gfRun( $dir, [ '' ], $count, $contains, $excludes );
	} )->with( [
		"[''] at root (3)" => [ '', 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php', 'README.md' ] ],
		"[''] in src (0)"  => [ 'src', 0, [], [ 'src/Plugin.php' ] ],
		"[''] in lib (0)"  => [ 'lib', 0, [], [ 'lib/Legacy.php' ] ],
	] );

	it( 'duplicates of empty string still match only extensionless files', function ( array $config ) {
		gfRun( '', $config, 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php' ] );
	} )->with( [
		"['','']"       => [ [ '', '' ] ],
		"['','','']"    => [ [ '', '', '' ] ],
		"['','','','']" => [ [ '', '', '', '' ] ],
	] );

} );

// ========================================================================
// filterByExt (array form) — non-string type casts (NEW FEATURE)
// ========================================================================

describe( 'get_files - filterByExt (array form) — type casts', function () {

	it( 'casts non-string elements to string', function ( string $dir, array $config, int $count, array $contains, array $excludes ) {
		gfRun( $dir, $config, $count, $contains, $excludes );
	} )->with( [
		"[null] casts to '' (3)"           => [ '', [ NULL ], 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php' ] ],
		"[null,'php'] = ['','php'] (27)"   => [ '', [ NULL, 'php' ], 27, [ 'LICENSE', 'index.php', 'Makefile' ], [ 'composer.json' ] ],
		"[false] casts to '' (3)"          => [ '', [ FALSE ], 3, [ 'LICENSE', 'Makefile', 'no-extension' ], [ 'index.php' ] ],
		"[false,'json'] = ['','json'] (8)" => [ '', [ FALSE, 'json' ], 8, [ 'LICENSE', 'composer.json' ], [ 'index.php' ] ],
		"['php',0,'json'] mixed (3)"       => [ 'inc', [ 'php', 0, 'json' ], 3, [ 'inc/setup.php', 'inc/sub/extra.php', 'inc/config.json' ], [] ],
		"[null,false,'php'] dedupe (27)"   => [ '', [ NULL, FALSE, 'php' ], 27, [ 'LICENSE', 'index.php' ], [ 'composer.json' ] ],
		"[null,'php',null,'php']"          => [ '', [ NULL, 'php', NULL, 'php' ], 27, [ 'LICENSE', 'index.php' ], [ 'composer.json' ] ],
	] );

} );

// ========================================================================
// filter callback - file filtering
// ========================================================================

describe( 'get_files - filter callback (file filtering)', function () {

	it( 'callback filters individual files based on predicate', function ( Closure $filter, string $dir, int $count, array $contains, array $excludes ) {
		$label  = "filter-callback dir='{$dir}'";
		$result = gfCollect( $dir, [ 'filter' => $filter ] );
		gfCheck( $result, $label, $count, $contains, $excludes );
	} )->with( [
		'exclude all php files' => [
			fn( SplFileInfo $f ): bool => $f->isDir() || strcasecmp( $f->getExtension(), 'php' ) !== 0,
			'src/Helpers',
			0,
			[],
			[ 'src/Helpers/functions.php', 'src/Helpers/arrays.inc.php' ],
		],
		'exclude all .md files' => [
			fn( SplFileInfo $f ): bool => $f->isDir() || strcasecmp( $f->getExtension(), 'md' ) !== 0,
			'',
			39,
			[ 'index.php', 'composer.json', 'LICENSE' ],
			[ 'README.md', 'src/README.md' ],
		],
		'allow everything'      => [
			fn( SplFileInfo $f ): bool => TRUE,
			'src/Admin',
			2,
			[ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php' ],
			[],
		],
	] );

} );

// ========================================================================
// filter callback - directory pruning
// ========================================================================

describe( 'get_files - filter callback (directory pruning)', function () {

	it( 'callback prunes subtrees when returning false for a directory', function ( Closure $filter, string $dir, int $count, array $contains, array $excludes ) {
		$label  = "filter-callback prune dir='{$dir}'";
		$result = gfCollect( $dir, [ 'filter' => $filter ] );
		gfCheck( $result, $label, $count, $contains, $excludes );
	} )->with( [
		'prune vendor only'                                  => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && $f->getFilename() === 'vendor' ),
			'',
			40,
			[ 'index.php', 'src/Plugin.php' ],
			[ 'vendor/acme/utils/src/Helper.php' ],
		],
		'prune node_modules only'                            => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && $f->getFilename() === 'node_modules' ),
			'',
			39,
			[ 'index.php', 'vendor/acme/utils/src/Helper.php' ],
			[ 'node_modules/wp-scripts/php/loader.php', 'node_modules/.package-lock.json' ],
		],
		'prune .git only'                                    => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && $f->getFilename() === '.git' ),
			'',
			40,
			[ 'index.php', '.bootstrap.php' ],
			[ '.git/hooks/pre-commit.php' ],
		],
		'prune vendor + node_modules + .git'                 => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && in_array( $f->getFilename(), [ 'vendor', 'node_modules', '.git' ], TRUE ) ),
			'',
			37,
			[ 'index.php', 'src/Plugin.php', 'README.md' ],
			[ 'vendor/acme/utils/src/Helper.php', 'node_modules/wp-scripts/php/loader.php', '.git/hooks/pre-commit.php' ],
		],
		'prune build + cache + node_modules + vendor + .git' => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && in_array( $f->getFilename(), [ 'vendor', 'node_modules', '.git', 'build', 'cache' ], TRUE ) ),
			'',
			35,
			[ 'index.php', 'src/Plugin.php' ],
			[ 'build/compiled.php', 'cache/templates.php', 'vendor/acme/utils/src/Helper.php' ],
		],
		'prune Deep subtree under src'                       => [
			fn( SplFileInfo $f ): bool => !( $f->isDir() && $f->getFilename() === 'Deep' ),
			'src',
			9,
			[ 'src/Plugin.php', 'src/Helpers/functions.php' ],
			[ 'src/Deep/Nested/Nested/File.php', 'src/Deep/Nested/Nested/Nested/File.php', 'src/Deep/Nested/Nested/Nested/Path/File.php' ],
		],
	] );

	it( 'bare callable as $config is normalized to filter', function () {
		$bareCallable = fn( SplFileInfo $f ): bool => !( $f->isDir() && $f->getFilename() === 'vendor' );
		$result       = gfCollect( '', $bareCallable );
		gfCheck( $result, 'bare callable shorthand', 40, [ 'index.php' ], [ 'vendor/acme/utils/src/Helper.php' ] );
	} );

	it( 'pruning prevents traversal of subtree (visited tracking)', function ( array $pruneDirs, array $neverVisited ) {
		$visited = [];
		$filter  = function ( SplFileInfo $file ) use ( &$visited, $pruneDirs ): bool {
			$visited[] = $file->getFilename();
			if ( $file->isDir() && in_array( $file->getFilename(), $pruneDirs, TRUE ) ) {
				return FALSE;
			}

			return TRUE;
		};

		gfCollect( '', [ 'filter' => $filter ] );

		foreach ( $neverVisited as $name ) {
			Assert::assertNotContains( $name, $visited, "'{$name}' should never be visited when pruning " . implode( ',', $pruneDirs ) );
		}
	} )->with( [
		'prune vendor - never visits acme'          => [ [ 'vendor' ], [ 'acme', 'utils', 'Helper.php' ] ],
		'prune node_modules - never visits scripts' => [ [ 'node_modules' ], [ 'wp-scripts', 'loader.php' ] ],
		'prune .git - never visits hooks'           => [ [ '.git' ], [ 'hooks', 'pre-commit.php' ] ],
		'prune multiple dirs'                       => [ [ 'vendor', 'node_modules', '.git' ], [ 'acme', 'wp-scripts', 'hooks' ] ],
	] );

} );

// ========================================================================
// Multiple-directory input
// ========================================================================

describe( 'get_files - multiple directories', function () {

	it( 'combines files from multiple input directories (no filter)', function ( array $dirs, int $count, array $contains, array $excludes ) {
		$label  = 'dirs=[' . implode( ',', $dirs ) . ']';
		$result = gfCollect( $dirs, NULL );
		gfCheck( $result, $label, $count, $contains, $excludes );
	} )->with( [
		"['src/Admin','lib'] (3)"     => [ [ 'src/Admin', 'lib' ], 3, [ 'src/Admin/Settings.php', 'src/Admin/ajax-handler.php', 'lib/Legacy.php' ], [] ],
		"['src/Helpers','inc'] (5)"   => [ [ 'src/Helpers', 'inc' ], 5, [ 'src/Helpers/functions.php', 'src/Helpers/arrays.inc.php', 'inc/setup.php', 'inc/sub/extra.php', 'inc/config.json' ], [] ],
		"['src','lib','inc'] (16)"    => [ [ 'src', 'lib', 'inc' ], 16, [ 'src/Plugin.php', 'lib/Legacy.php', 'inc/config.json' ], [] ],
		"['build','cache','lib'] (3)" => [ [ 'build', 'cache', 'lib' ], 3, [ 'build/compiled.php', 'cache/templates.php', 'lib/Legacy.php' ], [] ],
		"['tests','vendor'] (2)"      => [ [ 'tests', 'vendor' ], 2, [ 'tests/PluginTest.php', 'vendor/acme/utils/src/Helper.php' ], [] ],
	] );

	it( 'combines files from multiple directories with filterByExt', function ( array $dirs, string $config, int $count, array $contains, array $excludes ) {
		$label  = 'dirs=[' . implode( ',', $dirs ) . "] filterByExt='{$config}'";
		$result = gfCollect( $dirs, $config );
		gfCheck( $result, $label, $count, $contains, $excludes );
	} )->with( [
		"['src','lib','inc'] + 'php' (14)"  => [ [ 'src', 'lib', 'inc' ], 'php', 14, [ 'src/Plugin.php', 'lib/Legacy.php', 'inc/setup.php' ], [ 'inc/config.json', 'src/README.md' ] ],
		"['src','lib','inc'] + 'json' (1)"  => [ [ 'src', 'lib', 'inc' ], 'json', 1, [ 'inc/config.json' ], [ 'src/Plugin.php' ] ],
		"['src/Helpers','inc'] + 'php' (4)" => [ [ 'src/Helpers', 'inc' ], 'php', 4, [ 'src/Helpers/functions.php', 'src/Helpers/arrays.inc.php', 'inc/setup.php', 'inc/sub/extra.php' ], [ 'inc/config.json' ] ],
		"['src/Helpers','inc'] + 'inc.php'" => [ [ 'src/Helpers', 'inc' ], 'inc.php', 1, [ 'src/Helpers/arrays.inc.php' ], [ 'src/Helpers/functions.php' ] ],
	] );

	it( 'handles overlapping parent and child directories', function () {
		$label  = 'overlapping src + src/Admin';
		$result = gfCollect( [ 'src', 'src/Admin' ] );

		Assert::assertContains( 'src/Plugin.php', $result, "[{$label}] missing src/Plugin.php" );
		Assert::assertContains( 'src/Admin/Settings.php', $result, "[{$label}] missing src/Admin/Settings.php" );
		Assert::assertNotContains( 'lib/Legacy.php', $result, "[{$label}] unexpected lib/Legacy.php" );
	} );

	it( 'handles single directory as array', function () {
		$asArray  = gfCollect( [ 'lib' ] );
		$asString = gfCollect( 'lib' );

		expect( $asArray )->toBe( $asString );
	} );

} );

// ========================================================================
// Hidden files included by default
// ========================================================================

describe( 'get_files - hidden files by default', function () {

	it( 'yields hidden files (dotfiles) without special config', function ( string $dir, mixed $config, string $hiddenFile ) {
		$label  = "dir='{$dir}' cfg=" . gfFmt( $config );
		$result = gfCollect( $dir, $config );
		Assert::assertContains(
			$hiddenFile,
			$result,
			"[{$label}] missing hidden '{$hiddenFile}' - GOT " . gfDump( $result ),
		);
	} )->with( [
		'root .bootstrap.php (no filter)'        => [ '', NULL, '.bootstrap.php' ],
		'root .editorconfig (no filter)'         => [ '', NULL, '.editorconfig' ],
		'root .env.example (no filter)'          => [ '', NULL, '.env.example' ],
		'root .gitignore (no filter)'            => [ '', NULL, '.gitignore' ],
		"root .bootstrap.php under 'php' filter" => [ '', 'php', '.bootstrap.php' ],
		'src/.hidden.php (no filter)'            => [ 'src', NULL, 'src/.hidden.php' ],
		"src/.hidden.php under 'php' filter"     => [ 'src', 'php', 'src/.hidden.php' ],
		'file inside .git from root'             => [ '', NULL, '.git/hooks/pre-commit.php' ],
		"file inside .git under 'php' filter"    => [ '', 'php', '.git/hooks/pre-commit.php' ],
		'hidden filename in node_modules'        => [ '', NULL, 'node_modules/.package-lock.json' ],
		'hidden filename in node_modules + json' => [ '', 'json', 'node_modules/.package-lock.json' ],
		'inside .git from .git dir'              => [ '.git', NULL, '.git/hooks/pre-commit.php' ],
	] );

	it( 'can exclude hidden files via filter callback', function ( string $dir, array $included, array $excluded ) {
		$filter = fn( SplFileInfo $f ): bool => $f->getFilename()[0] !== '.';
		$result = gfCollect( $dir, [ 'filter' => $filter ] );

		foreach ( $included as $file ) {
			Assert::assertContains( $file, $result, "expected '{$file}' in result" );
		}
		foreach ( $excluded as $file ) {
			Assert::assertNotContains( $file, $result, "unexpected hidden '{$file}' in result" );
		}
	} )->with( [
		'src excludes .hidden.php'        => [ 'src', [ 'src/Plugin.php', 'src/Admin/Settings.php' ], [ 'src/.hidden.php' ] ],
		'root excludes hidden files+dirs' => [ '', [ 'index.php', 'src/Plugin.php' ], [ '.bootstrap.php', '.git/hooks/pre-commit.php', '.editorconfig' ] ],
	] );

} );
