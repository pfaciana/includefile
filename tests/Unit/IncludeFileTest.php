<?php

declare( strict_types=1 );

use Render\IncludeFile;

describe( 'is_file_pattern', function () {

	describe( 'file patterns (returns pattern)', function () {

		it( 'detects files by extension', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'simple php'     => [ 'file.php', 'file.php' ],
			'simple js'      => [ 'script.js', 'script.js' ],
			'simple css'     => [ 'styles.css', 'styles.css' ],
			'multiple dots'  => [ 'file.min.js', 'file.min.js' ],
			'path with file' => [ 'src/file.php', 'src/file.php' ],
			'deep path'      => [ 'a/b/c/file.txt', 'a/b/c/file.txt' ],
		] );

		it( 'preserves negation prefix for files', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'negated file'      => [ '!file.php', '!file.php' ],
			'negated path file' => [ '!src/file.js', '!src/file.js' ],
		] );

	} );

	describe( '@file: prefix (forces file pattern)', function () {

		it( 'treats @file: prefixed patterns as files', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'extensionless'      => [ '@file:Makefile', 'Makefile' ],
			'extensionless path' => [ '@file:bin/console', 'bin/console' ],
			'LICENSE'            => [ '@file:LICENSE', 'LICENSE' ],
			'README'             => [ '@file:README', 'README' ],
			'with extension'     => [ '@file:file.php', 'file.php' ],
		] );

		it( 'preserves negation with @file: prefix', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'negated @file'      => [ '!@file:Makefile', '!Makefile' ],
			'negated @file path' => [ '!@file:bin/console', '!bin/console' ],
		] );

		it( 'handles @file: with wildcards', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'wildcard foo'         => [ '@file:foo*', 'foo*' ],
			'wildcard pattern'     => [ '@file:gpt-pro-*', 'gpt-pro-*' ],
			'negated wildcard foo' => [ '!@file:foo*', '!foo*' ],
			'negated wildcard'     => [ '!@file:gpt-pro-*', '!gpt-pro-*' ],
		] );

	} );

	describe( 'directory patterns (returns false)', function () {

		it( 'detects directories by trailing slash', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'trailing slash'      => [ 'vendor/' ],
			'path trailing slash' => [ 'src/lib/' ],
			'deep trailing'       => [ 'a/b/c/d/' ],
		] );

		it( 'detects directories by /* suffix', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'star suffix'      => [ 'vendor/*' ],
			'path star suffix' => [ 'src/lib/*' ],
		] );

		it( 'detects directories by /** suffix', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'doublestar suffix'      => [ 'vendor/**' ],
			'path doublestar suffix' => [ 'src/lib/**' ],
		] );

		it( 'treats extensionless final segments as directories', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'single segment'     => [ 'vendor' ],
			'path extensionless' => [ 'src/vendor' ],
			'deep extensionless' => [ 'a/b/c/lib' ],
			'uppercase'          => [ 'Makefile' ],
			'with underscore'    => [ 'node_modules' ],
			'with hyphen'        => [ 'my-package' ],
		] );

		it( 'returns false for negated directory patterns', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'negated dir slash'     => [ '!vendor/' ],
			'negated extensionless' => [ '!vendor' ],
			'negated hidden name'   => [ '!.gitignore' ],
			'negated star suffix'   => [ '!vendor/*' ],
			'negated doublestar'    => [ '!vendor/**' ],
		] );

	} );

	describe( 'glob patterns', function () {

		it( 'handles glob wildcards as file patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'star extension'     => [ '*.php', '*.php' ],
			'doublestar path'    => [ '**/*.php', '**/*.php' ],
			'question mark'      => [ 'file?.php', 'file?.php' ],
			'bracket range'      => [ 'file[0-9].php', 'file[0-9].php' ],
			'bracket chars'      => [ 'file[abc].php', 'file[abc].php' ],
			'brace alternatives' => [ 'file.{js,ts}', 'file.{js,ts}' ],
			'complex glob'       => [ '**/test-*.spec.{js,ts}', '**/test-*.spec.{js,ts}' ],
		] );

		it( 'handles glob patterns as directories', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'star only'            => [ '*' ],
			'doublestar only'      => [ '**' ],
			'negated star'         => [ '!*' ],
			'negated doublestar'   => [ '!**' ],
			'star dir'             => [ '*/' ],
			'doublestar dir'       => [ '**/' ],
			'path star dir'        => [ 'src/*/' ],
			'wildcard dir name'    => [ 'foo*' ],
			'wildcard children'    => [ 'foo*/*' ],
			'wildcard descendants' => [ 'foo*/**' ],
			'negated wildcard dir' => [ '!foo*' ],
		] );

	} );

	describe( 'dot edge cases', function () {

		it( 'handles paths with dots in directories', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'dotted dir + file'          => [ 'src.old/file.php', 'src.old/file.php' ],
			'dotted dir + extensionless' => [ 'src.old/vendor', FALSE ],
			'multiple dotted dirs'       => [ 'a.1/b.2/file.txt', 'a.1/b.2/file.txt' ],
			'version dir + file'         => [ 'v1.2.3/index.js', 'v1.2.3/index.js' ],
		] );

		it( 'handles special dot patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single dot'    => [ '.', FALSE ],
			'double dot'    => [ '..', '..' ],
			'triple dot'    => [ '...', '...' ],
			'dot slash'     => [ './', FALSE ],
			'dotdot slash'  => [ '../', FALSE ],
			'relative file' => [ './file.php', './file.php' ],
			'parent file'   => [ '../file.php', '../file.php' ],
			'deep relative' => [ '../../src/file.php', '../../src/file.php' ],
		] );

		it( 'handles trailing dots', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'trailing dot'      => [ 'file.', 'file.' ],
			'trailing dots'     => [ 'file..', 'file..' ],
			'path trailing dot' => [ 'src/file.', 'src/file.' ],
		] );

		it( 'handles extension-only patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'ext only'      => [ '.php', FALSE ],
			'path ext only' => [ 'src/.php', FALSE ],
			'numeric ext'   => [ 'file.123', 'file.123' ],
			'long ext'      => [ 'file.verylongextension', 'file.verylongextension' ],
		] );

	} );

	describe( 'slash edge cases', function () {

		it( 'handles slash variations', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single slash'       => [ '/', FALSE ],
			'double slash'       => [ '//', FALSE ],
			'anchored file'      => [ '/file.php', '/file.php' ],
			'anchored dir'       => [ '/vendor', FALSE ],
			'anchored dir slash' => [ '/vendor/', FALSE ],
			'multi slash file'   => [ 'a//b//file.php', 'a//b//file.php' ],
			'trailing slashes'   => [ 'vendor//', FALSE ],
		] );

	} );

	describe( 'negation edge cases', function () {

		it( 'handles negation variations', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'negated !file.php'  => [ '!!file.php', '!!file.php' ],
			'negated !vendor'    => [ '!!vendor', FALSE ],
			'negated !!file.php' => [ '!!!file.php', '!!!file.php' ],
			'negation only'      => [ '!', FALSE ],
			'bang in middle'     => [ 'file!name.php', 'file!name.php' ],
			'bang dir in path'   => [ 'src/!important/file.php', 'src/!important/file.php' ],
		] );

	} );

	describe( '@file: edge cases', function () {

		it( 'handles @file: variations', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'@file empty'             => [ '@file:', '' ],
			'@file with slash suffix' => [ '@file:dir/', 'dir/' ],
			'@file with star'         => [ '@file:dir/*', 'dir/*' ],
			'@file with doublestar'   => [ '@file:dir/**', 'dir/**' ],
			'double @file'            => [ '@file:@file:name', '@file:name' ],
			'@file in path'           => [ 'src/@file:name', FALSE ],
			'negated @file empty'     => [ '!@file:', '!' ],
		] );

	} );

	describe( 'special characters', function () {

		it( 'handles special characters in patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'space in name'  => [ 'my file.php', 'my file.php' ],
			'hash in name'   => [ 'file#1.php', 'file#1.php' ],
			'at in name'     => [ 'file@2x.png', 'file@2x.png' ],
			'parens in name' => [ 'file (copy).php', 'file (copy).php' ],
			'unicode name'   => [ 'файл.php', 'файл.php' ],
			'emoji name'     => [ '🎉.txt', '🎉.txt' ],
			'backslash path' => [ 'src\\file.php', 'src\\file.php' ],
			'mixed slashes'  => [ 'src\\dir/file.php', 'src\\dir/file.php' ],
		] );

	} );

	describe( 'empty and minimal', function () {

		it( 'handles empty and minimal patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'       => [ '', FALSE ],
			'single char'        => [ 'a', FALSE ],
			'single char dotted' => [ 'a.b', 'a.b' ],
			'just extension'     => [ '.a', FALSE ],
		] );

	} );

	describe( 'whitespace not trimmed', function () {

		it( 'preserves leading/trailing whitespace', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'trailing space' => [ 'index.php ', 'index.php ' ],
			'leading space'  => [ ' index.php', ' index.php' ],
		] );

	} );

	describe( 'hidden directories', function () {

		it( 'detects hidden directories', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'hidden dir slash'    => [ '.config/' ],
			'hidden dir name'     => [ '.git' ],
			'hidden config dir'   => [ '.codex' ],
			'hidden dotfile name' => [ '.gitignore' ],
			'hidden env name'     => [ '.env' ],
			'hidden path name'    => [ 'config/.env' ],
			'hidden dir star'     => [ '.git/*' ],
			'hidden dir dstar'    => [ '.cache/**' ],
		] );

	} );

	describe( 'bracket patterns', function () {

		it( 'handles bracket glob files', function ( string $input, string $expected ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'bracket in name' => [ 'gpt-pro-[23].php', 'gpt-pro-[23].php' ],
			'bracket range'   => [ 'file[a-z].txt', 'file[a-z].txt' ],
			'negated bracket' => [ '!gpt-pro-[23].php', '!gpt-pro-[23].php' ],
		] );

	} );

	describe( 'comment and no-op patterns', function () {

		it( 'comments are not files', function ( string $input ) {
			expect( IncludeFile::is_file_pattern( $input ) )->toBeFalse();
		} )->with( [
			'comment'            => [ '# comment' ],
			'comment with space' => [ ' # comment' ],
			'double hash'        => [ '##' ],
			'just hash'          => [ '#' ],
		] );

	} );

} );

describe( 'test', function () {

	it( 'returns include-oriented match state', function ( string $path, bool $included, bool $notIncluded ) {
		$includeFile = new IncludeFile( [
			'@file:README',
			'src',
			'!src/private',
		] );

		$result = $includeFile->test( $path );

		expect( $result )
			->toHaveKeys( [ 'included', 'notIncluded', 'pattern' ] )
			->not->toHaveKeys( [ 'ignored', 'unignored' ] )
				 ->and( $result['included'] )->toBe( $included )
				 ->and( $result['notIncluded'] )->toBe( $notIncluded );
	} )->with( [
		'included file'       => [ 'README', TRUE, FALSE ],
		'included descendant' => [ 'src/Foo.php', TRUE, FALSE ],
		'excluded descendant' => [ 'src/private/Secret.php', FALSE, TRUE ],
		'default excluded'    => [ 'notes.txt', FALSE, TRUE ],
	] );

} );

describe( 'filtering', function () {

	it( 'keeps only included paths from arrays and iterators', function () {
		$paths = [
			'README',
			'README.md',
			'index.php',
			'src/Foo.php',
			'src/private/Secret.php',
			'vendor/autoload.php',
		];

		$expected = [
			'README',
			'index.php',
			'src/Foo.php',
		];

		$includeFile = new IncludeFile( [
			'@file:README',
			'src',
			'*.php',
			'!src/private',
			'!vendor',
		] );

		expect( array_values( $includeFile->filter( $paths ) ) )->toBe( $expected )
																->and( array_values( iterator_to_array( $includeFile->filterIterator( new ArrayIterator( $paths ) ), FALSE ) ) )->toBe( $expected );
	} );

} );

describe( 'invert_patterns', function () {

	describe( 'basic inversion', function () {

		it( 'inverts include patterns to exclude (adds !)', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'single file'    => [ 'file.php', [ '*', '!*/', '!file.php' ] ],
			'array single'   => [ [ 'file.php' ], [ '*', '!*/', '!file.php' ] ],
			'multiple files' => [ [ 'a.php', 'b.php' ], [ '*', '!*/', '!a.php', '!b.php' ] ],
		] );

		it( 'inverts exclude patterns to include (removes !)', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'negated file'   => [ '!file.php', [ '*', '!*/', 'file.php' ] ],
			'array negated'  => [ [ '!file.php' ], [ '*', '!*/', 'file.php' ] ],
			'mixed negation' => [ [ 'a.php', '!b.php' ], [ '*', '!*/', '!a.php', 'b.php' ] ],
		] );

	} );

	describe( 'string splitting', function () {

		it( 'splits multiline strings into patterns', function ( string $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'two lines'      => [ "a.php\nb.php", [ '*', '!*/', '!a.php', '!b.php' ] ],
			'three lines'    => [ "a.php\nb.php\nc.php", [ '*', '!*/', '!a.php', '!b.php', '!c.php' ] ],
			'mixed negation' => [ "a.php\n!b.php", [ '*', '!*/', '!a.php', 'b.php' ] ],
		] );

	} );

	describe( 'no-op lines', function () {

		it( 'preserves empty lines', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'     => [ '', [ '*', '!*/', '' ] ],
			'whitespace only'  => [ '   ', [ '*', '!*/', '' ] ],
			'empty in array'   => [ [ '' ], [ '*', '!*/', '' ] ],
			'between patterns' => [ "a.php\n\nb.php", [ '*', '!*/', '!a.php', '', '!b.php' ] ],
		] );

		it( 'preserves comment lines', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'comment only'        => [ '# comment', [ '*', '!*/', '# comment' ] ],
			'comment with space'  => [ '  # comment', [ '*', '!*/', '# comment' ] ],
			'comment in array'    => [ [ '# comment' ], [ '*', '!*/', '# comment' ] ],
			'pattern and comment' => [ "a.php\n# comment", [ '*', '!*/', '!a.php', '# comment' ] ],
		] );

	} );

	describe( 'directory pattern handling', function () {

		it( 'adds /** to directory patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'bare dir'       => [ 'vendor', [ '*', '!*/', '!vendor', '!vendor/**' ] ],
			'trailing slash' => [ 'vendor/', [ '*', '!*/', '!vendor', '!vendor/**' ] ],
			'path dir'       => [ 'src/lib', [ '*', '!*/', '!src/lib', '!src/lib/**' ] ],
		] );

		it( 'converts /* to /**', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'star suffix'      => [ 'vendor/*', [ '*', '!*/', '!vendor/*' ] ],
			'path star suffix' => [ 'src/lib/*', [ '*', '!*/', '!src/lib/*' ] ],
		] );

		it( 'preserves existing /**', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'doublestar suffix' => [ 'vendor/**', [ '*', '!*/', '!vendor/**' ] ],
			'path doublestar'   => [ 'src/lib/**', [ '*', '!*/', '!src/lib/**' ] ],
		] );

		it( 'handles negated directory patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'negated bare'       => [ '!vendor', [ '*', '!*/', 'vendor', 'vendor/**' ] ],
			'negated slash'      => [ '!vendor/', [ '*', '!*/', 'vendor', 'vendor/**' ] ],
			'negated star'       => [ '!vendor/*', [ '*', '!*/', 'vendor/*' ] ],
			'negated doublestar' => [ '!vendor/**', [ '*', '!*/', 'vendor/**' ] ],
		] );

	} );

	describe( 'file pattern handling', function () {

		it( 'keeps file patterns without /** suffix', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'simple file'  => [ 'file.php', [ '*', '!*/', '!file.php' ] ],
			'path file'    => [ 'src/file.php', [ '*', '!*/', '!src/file.php' ] ],
			'glob pattern' => [ '*.php', [ '*', '!*/', '!*.php' ] ],
		] );

		it( 'handles @file: prefix for extensionless files', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'@file Makefile' => [ '@file:Makefile', [ '*', '!*/', '!Makefile' ] ],
			'@file path'     => [ '@file:bin/console', [ '*', '!*/', '!bin/console' ] ],
			'negated @file'  => [ '!@file:Makefile', [ '*', '!*/', 'Makefile' ] ],
		] );

	} );

	describe( 'whitespace handling', function () {

		it( 'trims whitespace from patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'leading space'  => [ '  file.php', [ '*', '!*/', '!file.php' ] ],
			'trailing space' => [ 'file.php  ', [ '*', '!*/', '!file.php' ] ],
			'both sides'     => [ '  file.php  ', [ '*', '!*/', '!file.php' ] ],
			'tabs'           => [ "\tfile.php\t", [ '*', '!*/', '!file.php' ] ],
		] );

	} );

	describe( 'default prefix patterns', function () {

		it( 'always prepends * and !*/ patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'empty array'       => [ [], [ '*', '!*/' ] ],
			'single pattern'    => [ 'file.php', [ '*', '!*/', '!file.php' ] ],
			'multiple patterns' => [ [ 'a.php', 'b.php' ], [ '*', '!*/', '!a.php', '!b.php' ] ],
		] );

	} );

	describe( 'complex patterns', function () {

		it( 'handles real-world gitignore-style patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'typical include file' => [
				"src\n*.php\n!vendor\n# keep tests\ntests",
				[ '*', '!*/', '!src', '!src/**', '!*.php', 'vendor', 'vendor/**', '# keep tests', '!tests', '!tests/**' ],
			],
			'mixed files and dirs' => [
				[ 'src/', 'file.php', 'lib', '!excluded.php' ],
				[ '*', '!*/', '!src', '!src/**', '!file.php', '!lib', '!lib/**', 'excluded.php' ],
			],
		] );

	} );

	describe( 'line ending variations', function () {

		it( 'handles different line endings', function ( string $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'CRLF'              => [ "a.php\r\nb.php", [ '*', '!*/', "!a.php", '!b.php' ] ],
			'CR only'           => [ "a.php\rb.php", [ '*', '!*/', "!a.php\rb.php" ] ],
			'mixed endings'     => [ "a.php\nb.php\r\nc.php", [ '*', '!*/', '!a.php', "!b.php", '!c.php' ] ],
			'trailing newline'  => [ "a.php\n", [ '*', '!*/', '!a.php', '' ] ],
			'multiple newlines' => [ "a.php\n\n\nb.php", [ '*', '!*/', '!a.php', '', '', '!b.php' ] ],
		] );

	} );

	describe( 'negation edge cases', function () {

		it( 'handles negation variations', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'just !'          => [ '!', [ '*', '!*/', '/**' ] ],
			'double negation' => [ '!!file.php', [ '*', '!*/', '!file.php' ] ],
			'triple negation' => [ '!!!file.php', [ '*', '!*/', '!!file.php' ] ],
			'negation dir'    => [ '!!vendor', [ '*', '!*/', '!vendor', '!vendor/**' ] ],
			'! in middle'     => [ 'file!name.php', [ '*', '!*/', '!file!name.php' ] ],
		] );

	} );

	describe( 'comment edge cases', function () {

		it( 'handles comment variations', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'just #'           => [ '#', [ '*', '!*/', '#' ] ],
			'## double'        => [ '##', [ '*', '!*/', '##' ] ],
			'# with spaces'    => [ '#   ', [ '*', '!*/', '#' ] ],
			'hash mid pattern' => [ 'file#1.php', [ '*', '!*/', '!file#1.php' ] ],
			'hash in path'     => [ 'src/#backup/file.php', [ '*', '!*/', '!src/#backup/file.php' ] ],
		] );

	} );

	describe( '@file: in invert', function () {

		it( 'handles @file: edge cases', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'@file empty'            => [ '@file:', [ '*', '!*/', '!' ] ],
			'@file negated empty'    => [ '!@file:', [ '*', '!*/', '' ] ],
			'@file with dir'         => [ '@file:Makefile/', [ '*', '!*/', '!Makefile/' ] ],
			'@file wildcard'         => [ '@file:foo*', [ '*', '!*/', '!foo*' ] ],
			'@file negated wildcard' => [ '!@file:foo*', [ '*', '!*/', 'foo*' ] ],
			'@file gpt-pro-*'        => [ '@file:gpt-pro-*', [ '*', '!*/', '!gpt-pro-*' ] ],
			'mixed @file'            => [ [ '@file:LICENSE', 'src', 'README.md' ], [ '*', '!*/', '!LICENSE', '!src', '!src/**', '!README.md' ] ],
		] );

	} );

	describe( 'glob patterns in invert', function () {

		it( 'handles glob patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'star only'       => [ '*', [ '*', '!*/', '!*' ] ],
			'doublestar only' => [ '**', [ '*', '!*/', '!**' ] ],
			'question mark'   => [ 'file?.php', [ '*', '!*/', '!file?.php' ] ],
			'brackets'        => [ 'file[0-9].php', [ '*', '!*/', '!file[0-9].php' ] ],
			'bracket in name' => [ 'gpt-pro-[23].php', [ '*', '!*/', '!gpt-pro-[23].php' ] ],
			'braces'          => [ '*.{js,ts}', [ '*', '!*/', '!*.{js,ts}' ] ],
			'negated glob'    => [ '!*.min.js', [ '*', '!*/', '*.min.js' ] ],
		] );

	} );

	describe( 'wildcard directory in invert', function () {

		it( 'handles wildcard directory patterns', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'wildcard dir name'        => [ 'foo*', [ '*', '!*/', '!foo*', '!foo*/**' ] ],
			'negated wildcard dir'     => [ '!foo*', [ '*', '!*/', 'foo*', 'foo*/**' ] ],
			'wildcard dir children'    => [ 'foo*/*', [ '*', '!*/', '!foo*/*' ] ],
			'wildcard dir descendants' => [ 'foo*/**', [ '*', '!*/', '!foo*/**' ] ],
		] );

	} );

	describe( 'hidden directory in invert', function () {

		it( 'handles hidden directories', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'hidden dir slash'        => [ '.config/', [ '*', '!*/', '!.config', '!.config/**' ] ],
			'negated hidden dir'      => [ '!.config/', [ '*', '!*/', '.config', '.config/**' ] ],
			'hidden dir name'         => [ '.config', [ '*', '!*/', '!.config', '!.config/**' ] ],
			'negated hidden dir name' => [ '!.config', [ '*', '!*/', '.config', '.config/**' ] ],
		] );

	} );

	describe( 'slash patterns in invert', function () {

		it( 'handles slash variations', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'just /'             => [ '/', [ '*', '!*/', '!/**' ] ],
			'anchored file'      => [ '/file.php', [ '*', '!*/', '!/file.php' ] ],
			'anchored dir'       => [ '/vendor', [ '*', '!*/', '!/vendor', '!/vendor/**' ] ],
			'anchored dir slash' => [ '/vendor/', [ '*', '!*/', '!/vendor', '!/vendor/**' ] ],
			'double slash'       => [ 'a//b.php', [ '*', '!*/', '!a//b.php' ] ],
			'relative dot'       => [ './src', [ '*', '!*/', '!./src', '!./src/**' ] ],
			'relative dotdot'    => [ '../src', [ '*', '!*/', '!../src', '!../src/**' ] ],
		] );

	} );

	describe( 'special characters in invert', function () {

		it( 'handles special characters', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'space in name'     => [ 'my file.php', [ '*', '!*/', '!my file.php' ] ],
			'unicode'           => [ 'файл.php', [ '*', '!*/', '!файл.php' ] ],
			'emoji'             => [ '🎉.txt', [ '*', '!*/', '!🎉.txt' ] ],
			'backslash path'    => [ 'src\\file.php', [ '*', '!*/', '!src\\file.php' ] ],
			'windows path file' => [ 'src\\index.php', [ '*', '!*/', '!src\\index.php' ] ],
			'windows path dir'  => [ 'src\\Legacy', [ '*', '!*/', '!src\\Legacy', '!src\\Legacy/**' ] ],
		] );

	} );

	describe( 'array edge cases', function () {

		it( 'handles array variations', function ( array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'all empty'      => [ [ '', '', '' ], [ '*', '!*/', '', '', '' ] ],
			'all comments'   => [ [ '# a', '# b' ], [ '*', '!*/', '# a', '# b' ] ],
			'all whitespace' => [ [ '  ', "\t", '   ' ], [ '*', '!*/', '', '', '' ] ],
			'mixed no-ops'   => [ [ '', '# comment', '  ' ], [ '*', '!*/', '', '# comment', '' ] ],
			'single element' => [ [ 'file.php' ], [ '*', '!*/', '!file.php' ] ],
			'many elements'  => [ [ 'a.php', 'b.php', 'c.php', 'd.php', 'e.php' ], [ '*', '!*/', '!a.php', '!b.php', '!c.php', '!d.php', '!e.php' ] ],
		] );

	} );

	describe( 'deeply nested paths', function () {

		it( 'handles deep nesting', function ( string|array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'deep file'    => [ 'a/b/c/d/e/f/g.php', [ '*', '!*/', '!a/b/c/d/e/f/g.php' ] ],
			'deep dir'     => [ 'a/b/c/d/e/f/g', [ '*', '!*/', '!a/b/c/d/e/f/g', '!a/b/c/d/e/f/g/**' ] ],
			'deep negated' => [ '!a/b/c/d/file.php', [ '*', '!*/', 'a/b/c/d/file.php' ] ],
		] );

	} );

	describe( 'order preservation', function () {

		it( 'preserves pattern order', function ( array $input, array $expected ) {
			expect( IncludeFile::invert_patterns( $input ) )->toBe( $expected );
		} )->with( [
			'include then exclude' => [
				[ 'vendor', '!vendor/package' ],
				[ '*', '!*/', '!vendor', '!vendor/**', 'vendor/package', 'vendor/package/**' ],
			],
			'exclude then include' => [
				[ '!vendor', 'vendor/package' ],
				[ '*', '!*/', 'vendor', 'vendor/**', '!vendor/package', '!vendor/package/**' ],
			],
			'file then dir'        => [
				[ 'file.php', 'dir' ],
				[ '*', '!*/', '!file.php', '!dir', '!dir/**' ],
			],
			'dir then file'        => [
				[ 'dir', 'file.php' ],
				[ '*', '!*/', '!dir', '!dir/**', '!file.php' ],
			],
		] );

	} );

} );

describe( 'to_directory_pattern', function () {

	describe( 'file patterns → directory extraction', function () {

		it( 'extracts * for root-level file patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'simple php'    => [ 'file.php', '*' ],
			'simple js'     => [ 'script.js', '*' ],
			'simple css'    => [ 'styles.css', '*' ],
			'multiple dots' => [ 'file.min.js', '*' ],
		] );

		it( 'extracts parent directory from pathed files', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single dir'  => [ 'src/file.php', 'src/*' ],
			'two dirs'    => [ 'src/lib/file.php', 'src/lib/*' ],
			'deep path'   => [ 'a/b/c/d/e/f/g.php', 'a/b/c/d/e/f/*' ],
			'dotted dir'  => [ 'src.old/file.php', 'src.old/*' ],
			'version dir' => [ 'v1.2.3/index.js', 'v1.2.3/*' ],
		] );

	} );

	describe( 'negated file patterns', function () {

		it( 'returns false for negated files', function ( string $input ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBeFalse();
		} )->with( [
			'negated file'      => [ '!file.php' ],
			'negated multi-dot' => [ '!file.min.js' ],
			'negated path file' => [ '!src/file.php' ],
			'negated deep'      => [ '!a/b/c/file.txt' ],
			'negated dotted'    => [ '!src.old/file.php' ],
		] );

	} );

	describe( 'directory patterns (pass-through)', function () {

		it( 'passes through bare directory names', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single segment'     => [ 'vendor', 'vendor' ],
			'path extensionless' => [ 'src/vendor', 'src/vendor' ],
			'deep extensionless' => [ 'a/b/c/lib', 'a/b/c/lib' ],
			'with underscore'    => [ 'node_modules', 'node_modules' ],
			'with hyphen'        => [ 'my-package', 'my-package' ],
		] );

		it( 'passes through trailing slash directories', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'trailing slash' => [ 'vendor/', 'vendor/' ],
			'path trailing'  => [ 'src/lib/', 'src/lib/' ],
			'deep trailing'  => [ 'a/b/c/d/', 'a/b/c/d/' ],
		] );

		it( 'passes through /* suffix directories', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'star suffix' => [ 'vendor/*', 'vendor/*' ],
			'path star'   => [ 'src/lib/*', 'src/lib/*' ],
		] );

		it( 'passes through /** suffix directories', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'doublestar suffix' => [ 'vendor/**', 'vendor/**' ],
			'path doublestar'   => [ 'src/lib/**', 'src/lib/**' ],
		] );

		it( 'passes through negated directory patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'negated bare'       => [ '!vendor', '!vendor' ],
			'negated slash'      => [ '!vendor/', '!vendor/' ],
			'negated star'       => [ '!vendor/*', '!vendor/*' ],
			'negated doublestar' => [ '!vendor/**', '!vendor/**' ],
			'negated path'       => [ '!src/lib', '!src/lib' ],
			'negated hidden'     => [ '!.gitignore', '!.gitignore' ],
		] );

	} );

	describe( '@file: prefix handling', function () {

		it( 'extracts * for @file: root-level patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'@file Makefile' => [ '@file:Makefile', '*' ],
			'@file LICENSE'  => [ '@file:LICENSE', '*' ],
			'@file README'   => [ '@file:README', '*' ],
			'@file with ext' => [ '@file:file.php', '*' ],
		] );

		it( 'extracts directory from @file: pathed patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'@file path' => [ '@file:bin/console', 'bin/*' ],
			'@file deep' => [ '@file:tools/scripts/build', 'tools/scripts/*' ],
		] );

		it( 'handles negated @file: patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'negated @file'      => [ '!@file:Makefile', FALSE ],
			'negated @file path' => [ '!@file:bin/console', FALSE ],
			'negated @file deep' => [ '!@file:a/b/c', FALSE ],
		] );

		it( 'handles @file: with wildcards', function ( string $input, string|false $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'@file wildcard'   => [ '@file:foo*', '*' ],
			'@file gpt-pro-*'  => [ '@file:gpt-pro-*', '*' ],
			'negated wildcard' => [ '!@file:foo*', FALSE ],
		] );

	} );

	describe( 'glob file patterns', function () {

		it( 'extracts * for root glob patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'star extension'     => [ '*.php', '*' ],
			'question mark'      => [ 'file?.php', '*' ],
			'bracket range'      => [ 'file[0-9].php', '*' ],
			'brace alternatives' => [ 'file.{js,ts}', '*' ],
		] );

		it( 'extracts directory from pathed glob patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'src/*.php'         => [ 'src/*.php', 'src/*' ],
			'deep glob'         => [ 'a/b/c/*.txt', 'a/b/c/*' ],
			'doublestar prefix' => [ '**/*.php', '**/*' ],
		] );

	} );

	describe( 'slash edge cases', function () {

		it( 'handles anchored paths', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'anchored file' => [ '/file.php', '/*' ],
			'anchored path' => [ '/src/file.php', '/src/*' ],
			'anchored dir'  => [ '/vendor', '/vendor' ],
		] );

		it( 'handles relative paths', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'dot file'      => [ './file.php', './*' ],
			'dot path'      => [ './src/file.php', './src/*' ],
			'dotdot file'   => [ '../file.php', '../*' ],
			'dotdot path'   => [ '../src/file.php', '../src/*' ],
			'deep relative' => [ '../../src/file.php', '../../src/*' ],
		] );

		it( 'handles double slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'double slash mid'  => [ 'a//b//file.php', 'a//b/*' ],
			'double slash file' => [ '//file.php', '/*' ],
		] );

		it( 'passes through slash-only patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single slash' => [ '/', '/' ],
			'double slash' => [ '//', '//' ],
		] );

	} );

	describe( 'negation edge cases', function () {

		it( 'handles multiple negation prefixes', function ( string $input, string|false $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'double negation' => [ '!!file.php', FALSE ],
			'triple negation' => [ '!!!file.php', FALSE ],
			'double neg path' => [ '!!src/file.php', FALSE ],
		] );

		it( 'handles bang in middle of pattern', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'bang in name' => [ 'file!name.php', '*' ],
			'bang in path' => [ 'src/!important/file.php', 'src/!important/*' ],
		] );

	} );

	describe( 'dot edge cases', function () {

		it( 'handles special dot patterns', function ( string $input, string|false $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'single dot'   => [ '.', '.' ],
			'double dot'   => [ '..', '*' ],
			'triple dot'   => [ '...', '*' ],
			'dot slash'    => [ './', './' ],
			'dotdot slash' => [ '../', '../' ],
		] );

		it( 'handles trailing dots', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'trailing dot'      => [ 'file.', '*' ],
			'path trailing dot' => [ 'src/file.', 'src/*' ],
		] );

	} );

	describe( 'empty and minimal patterns', function () {

		it( 'handles empty and minimal inputs', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'    => [ '', '' ],
			'single char'     => [ 'a', 'a' ],
			'single char dot' => [ 'a.b', '*' ],
			'just extension'  => [ '.a', '.a' ],
		] );

	} );

	describe( 'comment and no-op patterns', function () {

		it( 'passes through comments unchanged', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'comment'     => [ '# comment', '# comment' ],
			'double hash' => [ '##', '##' ],
			'just hash'   => [ '#', '#' ],
		] );

	} );

	describe( 'special characters', function () {

		it( 'handles special characters in patterns', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'space in name' => [ 'my file.php', '*' ],
			'space in path' => [ 'my dir/file.php', 'my dir/*' ],
			'unicode name'  => [ 'файл.php', '*' ],
			'unicode path'  => [ 'папка/файл.php', 'папка/*' ],
			'emoji name'    => [ '🎉.txt', '*' ],
			'emoji path'    => [ '🎉/file.txt', '🎉/*' ],
		] );

		it( 'handles backslash paths', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'backslash file' => [ 'src\\file.php', '*' ],
			'mixed slashes'  => [ 'src\\dir/file.php', 'src\\dir/*' ],
		] );

	} );

	describe( 'bracket patterns', function () {

		it( 'handles bracket glob files', function ( string $input, string|false $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'bracket in name' => [ 'gpt-pro-[23].php', '*' ],
			'bracket range'   => [ 'file[a-z].txt', '*' ],
			'negated bracket' => [ '!gpt-pro-[23].php', FALSE ],
			'bracket in path' => [ 'src[1]/file.php', 'src[1]/*' ],
		] );

	} );

	describe( 'hidden directories', function () {

		it( 'passes through hidden directories', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'hidden dir name'   => [ '.git', '.git' ],
			'hidden config dir' => [ '.codex', '.codex' ],
			'hidden dotfile'    => [ '.gitignore', '.gitignore' ],
			'hidden env'        => [ '.env', '.env' ],
			'hidden path'       => [ 'config/.env', 'config/.env' ],
			'hidden dir slash'  => [ '.config/', '.config/' ],
			'hidden dir star'   => [ '.git/*', '.git/*' ],
			'hidden dir dstar'  => [ '.cache/**', '.cache/**' ],
		] );

		it( 'extracts directory from hidden dir paths', function ( string $input, string $expected ) {
			expect( IncludeFile::to_directory_pattern( $input ) )->toBe( $expected );
		} )->with( [
			'file in hidden' => [ '.config/settings.json', '.config/*' ],
			'deep hidden'    => [ '.local/share/file.txt', '.local/share/*' ],
		] );

	} );

	describe( 'consistency with is_file_pattern', function () {

		it( 'agrees with is_file_pattern on file vs directory classification', function ( string $input, bool $is_file ) {
			$file_pattern = IncludeFile::is_file_pattern( $input );
			$dir_pattern  = IncludeFile::to_directory_pattern( $input );

			if ( $is_file ) {
				// File patterns should produce * or a directory path
				expect( $file_pattern )->not->toBeFalse();
				expect( $dir_pattern === '*' || $dir_pattern === '!*' || !str_contains( $dir_pattern, '.' ) || $dir_pattern === $input )->toBeTrue();
			}
			else {
				// Directory patterns should pass through unchanged
				expect( $file_pattern )->toBeFalse();
				expect( $dir_pattern )->toBe( $input );
			}
		} )->with( [
			'file'        => [ 'file.php', TRUE ],
			'dir'         => [ 'vendor', FALSE ],
			'dir slash'   => [ 'vendor/', FALSE ],
			'pathed file' => [ 'src/file.php', TRUE ],
			'pathed dir'  => [ 'src/lib', FALSE ],
			'dotfile'     => [ '.gitignore', FALSE ],
			'@file'       => [ '@file:Makefile', TRUE ],
		] );

	} );

} );

describe( 'is_terminal_negated_directory_pattern', function () {

	it( 'detects negated directory patterns that cannot re-introduce descendants', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		'bare negated dir'            => [ '!vendor', TRUE ],
		'negated dir trailing slash'  => [ '!vendor/', TRUE ],
		'deep negated dir'            => [ '!lib/vendor', TRUE ],
		'deep negated dir slash'      => [ '!lib/vendor/', TRUE ],
		'hidden negated dir'          => [ '!.git', TRUE ],
		'negated dir one-level open'  => [ '!lib/vendor/*', FALSE ],
		'negated dir recursive open'  => [ '!lib/vendor/**', FALSE ],
		'negated root one-level open' => [ '!/*', FALSE ],
		'negated root recursive open' => [ '!/**', FALSE ],
		'negated file'                => [ '!src/file.php', FALSE ],
		'negated file glob'           => [ '!*.php', FALSE ],
		'negated forced file'         => [ '!@file:Makefile', FALSE ],
		'non-negated dir'             => [ 'vendor', FALSE ],
		'comment'                     => [ '# !vendor', FALSE ],
		'empty'                       => [ '', FALSE ],
		'whitespace negated dir'      => [ '  !vendor  ', TRUE ],
	] );

	it( 'handles glob patterns with wildcards (all terminate matched dirs)', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		// Wildcards determine WHAT matches, not WHETHER matched dirs are skipped
		'star in middle segment'       => [ '!src/*/vendor', TRUE ],
		'double star in middle'        => [ '!src/**/vendor', TRUE ],
		'star in segment name'         => [ '!ven*dor', TRUE ],
		'star at segment end'          => [ '!vendor*', TRUE ],
		'star at segment start'        => [ '!*vendor', TRUE ],
		'question mark wildcard'       => [ '!vendor?', TRUE ],
		'question in middle'           => [ '!ven?or', TRUE ],
		'character class'              => [ '!vendor[0-9]', TRUE ],
		'character class range'        => [ '!src/lib[a-z]', TRUE ],
		'negated character class'      => [ '!vendor[!0-9]', TRUE ],
		'brace expansion'              => [ '!{vendor,lib}', TRUE ],
		'brace in path'                => [ '!src/{vendor,lib}', TRUE ],
		// Wildcards followed by more path segments still terminate matched dirs
		'star slash not at end'        => [ '!vendor/*/lib', TRUE ],
		'double star slash not at end' => [ '!vendor/**/lib', TRUE ],
	] );

	it( 'handles extension-like directory names', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		// Dotted names treated as files (ambiguous but conservative)
		'systemd style .d'      => [ '!apt.conf.d', FALSE ],
		'config .d'             => [ '!conf.d', FALSE ],
		'hidden with extension' => [ '!.config.d', FALSE ],
		'backup directory'      => [ '!src.bak', FALSE ],
		'versioned directory'   => [ '!vendor.old', FALSE ],
		// Definite files
		'actual file extension' => [ '!README.md', FALSE ],
		'php file'              => [ '!bootstrap.php', FALSE ],
		'double extension'      => [ '!archive.tar.gz', FALSE ],
		// Single-dot hidden names → directory (e.g., .git, .env)
		'hidden dir'            => [ '!.gitignore', TRUE ],
		'force file prefix'     => [ '!@file:.gitignore', FALSE ],
	] );

	it( 'handles double negation and edge syntax', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		// Double/triple negation - after first !, rest is literal filename
		'double negation'          => [ '!!vendor', TRUE ],  // Excludes dir literally named "!vendor"
		'triple negation'          => [ '!!!vendor', TRUE ], // Excludes dir literally named "!!vendor"
		// Just symbols
		'just negation'            => [ '!', FALSE ],
		'negated empty'            => [ '! ', FALSE ],
		// Path edge cases
		'negated root slash'       => [ '!/', TRUE ],
		'negated dot'              => [ '!.', TRUE ],
		// Trailing slashes and spaces
		'negated dir double slash' => [ '!vendor//', TRUE ],
		'negated with tab'         => [ "!\tvendor", TRUE ],
		'tab before negation'      => [ "\t!vendor", TRUE ],
	] );

	it( 'handles paths with special segments', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		// Multiple slashes (malformed but still terminates)
		'multiple slashes'     => [ '!src//vendor', TRUE ],
		'leading double slash' => [ '!//vendor', TRUE ],
		// Trailing/rooted paths
		'trailing slash only'  => [ '!vendor/', TRUE ],
		'rooted'               => [ '!/vendor', TRUE ],
		'rooted deep'          => [ '!/src/vendor', TRUE ],
	] );

	it( 'handles unicode and special characters', function ( string $input, bool $expected ) {
		expect( IncludeFile::is_dir_termination_pattern( $input ) )->toBe( $expected );
	} )->with( [
		'unicode directory'  => [ '!日本語', TRUE ],
		'emoji directory'    => [ '!📁vendor', TRUE ],
		'unicode with slash' => [ '!src/données', TRUE ],
		'accented'           => [ '!café', TRUE ],
		// Special chars that might break regex/glob
		'parentheses'        => [ '!vendor(old)', TRUE ],
		'plus sign'          => [ '!c++', TRUE ],
		'at sign'            => [ '!@types', TRUE ],
		'hash in name'       => [ '!vendor#1', TRUE ],
		'dollar sign'        => [ '!vendor$', TRUE ],
		'caret'              => [ '!vendor^', TRUE ],
	] );

} );

describe( 'match by directory', function () {

	it( 'matches the expected directory probes', function ( array $patterns, array $match, array $skip ) {
		$dataset  = preg_replace( '/^dataset "(.+)"$/', '$1', $this->dataName() );
		$messages = [];

		$includeFile = new IncludeFile( $patterns );
		$dirPatterns = $includeFile->get_directory_patterns();
		$includeDirs = new IncludeFile( $dirPatterns );

		foreach ( [ 'SKIPPED' => $match, 'MATCHED' => $skip ] as $problem => $paths ) {
			foreach ( $paths as $path ) {
				if ( $includeDirs->includes( $test = ( $path = rtrim( $path, '/' ) ) . ( $path ? '/' : '' ) . 'test.php' ) === ( $problem === 'MATCHED' ) ) {
					$messages[] = "[ {$test} ] ✘ WAS {$problem}\n[ " . implode( '  ', $patterns ) . ' ]';
				}
			}
		}

		expect( $messages )->toBeEmpty( "--- {$dataset} ---\n\n" . implode( "\n\n", $messages ) . "\n" );
	} )->with( [

		'one star'             => [
			'patterns' => [ '*' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'two stars'            => [
			'patterns' => [ '**' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'slash one star'       => [
			'patterns' => [ '/*' ],
			'match'    => [ '/', ],
			'skip'     => [ '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
		],
		'slash two stars'      => [
			'patterns' => [ '/**' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'star slash star'      => [
			'patterns' => [ '*/*' ],
			'match'    => [ '.git', 'lib', 'node_modules', 'src', ],
			'skip'     => [ '/', 'src/Deep', 'src/Deep/Nested', ],
		],
		'star slash stars'     => [
			'patterns' => [ '*/**' ],
			'match'    => [ '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ '/', ],
		],
		'stars slash star'     => [
			'patterns' => [ '**/*' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'stars slash stars'    => [
			'patterns' => [ '**/**' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'case-insensitive'     => [
			'patterns' => [ '*.[pP][hH][pP]' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'case-sensitive upper' => [
			'patterns' => [ '*.PHP' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'case-sensitive lower' => [
			'patterns' => [ '*.php' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'slash file'           => [
			'patterns' => [ '/*.php' ],
			'match'    => [ '/', ],
			'skip'     => [ '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
		],
		'star slash file'      => [
			'patterns' => [ '*/*.php' ],
			'match'    => [ '.git', 'lib', 'node_modules', 'src', ],
			'skip'     => [ '/', 'src/Deep', 'src/Deep/Nested', ],
		],
		'stars slash file'     => [
			'patterns' => [ '**/*.php' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],
		'star slash x2 file'   => [
			'patterns' => [ '/*/*.php' ],
			'match'    => [ '.git', 'lib', 'node_modules', 'src', ],
			'skip'     => [ '/', 'src/Deep', 'src/Deep/Nested', ],
		],
		'specific file'        => [
			'patterns' => [ '@file:test.php' ],
			'match'    => [ '/', '.git', 'lib', 'node_modules', 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [],
		],

		'{dir}'               => [
			'patterns' => [ 'src' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'{dir}/*'             => [
			'patterns' => [ 'src/*' ],
			'match'    => [ 'src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', ],
		],
		'{dir}/**'            => [
			'patterns' => [ 'src/**' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'/{dir}'              => [
			'patterns' => [ '/src' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'/{dir}/*'            => [
			'patterns' => [ '/src/*' ],
			'match'    => [ 'src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', ],
		],
		'/{dir}/**'           => [
			'patterns' => [ '/src/**' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'*/{dir}'             => [
			'patterns' => [ '*/src' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'*/{dir}/*'           => [
			'patterns' => [ '*/src/*' ],
			'match'    => [ 'a/src', ],
			'skip'     => [ 'src', 'a/src/src/src', 'a/src/b/c', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'*/{dir}/**'          => [
			'patterns' => [ '*/src/**' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'/*/{dir}'            => [
			'patterns' => [ '/*/src' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'/*/{dir}/*'          => [
			'patterns' => [ '/*/src/*' ],
			'match'    => [ 'a/src', ],
			'skip'     => [ 'src', 'a/src/src/src', 'a/src/b/c', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'/*/{dir}/**'         => [
			'patterns' => [ '/*/src/**' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', '/', '.git', 'lib', 'node_modules', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
		],
		'**/{dir}'            => [
			'patterns' => [ '**/src' ],
			'match'    => [ 'src', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'**/{dir}/*'          => [
			'patterns' => [ '**/src/*' ],
			'match'    => [ 'src', 'a/src', 'a/src/src/src', 'a/b/src', ],
			'skip'     => [ 'src/Deep', 'src/Deep/Nested', 'a/src/b/c', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'**/{dir}/**'         => [
			'patterns' => [ '**/src/**' ],
			'match'    => [ 'src', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}'           => [
			'patterns' => [ '/**/src' ],
			'match'    => [ 'src', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}/*'         => [
			'patterns' => [ '/**/src/*' ],
			'match'    => [ 'src', 'a/src', 'a/src/src/src', 'a/b/src', ],
			'skip'     => [ 'src/Deep', 'src/Deep/Nested', 'a/src/b/c', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}/**'        => [
			'patterns' => [ '/**/src/**' ],
			'match'    => [ 'src', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'{dir}/{file}'        => [
			'patterns' => [ 'src/*.php' ],
			'match'    => [ 'src', ],
			'skip'     => [ 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'{dir}/*/{file}'      => [
			'patterns' => [ 'src/*/*.php' ],
			'match'    => [ 'src/Deep', ],
			'skip'     => [ 'src', 'src/Deep/Nested', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'{dir}/**/{file}'     => [
			'patterns' => [ 'src/**/*.php' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/{dir}/{file}'       => [
			'patterns' => [ '/src/*.php' ],
			'match'    => [ 'src', ],
			'skip'     => [ 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/{dir}/*/{file}'     => [
			'patterns' => [ '/src/*/*.php' ],
			'match'    => [ 'src/Deep', ],
			'skip'     => [ 'src', 'src/Deep/Nested', 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/{dir}/**/{file}'    => [
			'patterns' => [ '/src/**/*.php' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ 'a/src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'*/{dir}/{file}'      => [
			'patterns' => [ '*/src/*.php' ],
			'match'    => [ 'a/src', ],
			'skip'     => [ 'src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'*/{dir}/*/{file}'    => [
			'patterns' => [ '*/src/*/*.php' ],
			'match'    => [ 'a/src/b' ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b/c', 'a/b/src/c/d', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'*/{dir}/**/{file}'   => [
			'patterns' => [ '*/src/**/*.php' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/b/src/c/d', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/*/{dir}/{file}'     => [
			'patterns' => [ '/*/src/*.php' ],
			'match'    => [ 'a/src', ],
			'skip'     => [ 'src', 'a/b/src/c/d', 'a/src/src/src', 'a/src/b/c', 'src/Deep', 'src/Deep/Nested', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/*/{dir}/*/{file}'   => [
			'patterns' => [ '/*/src/*/*.php' ],
			'match'    => [ 'a/src/b' ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b/c', 'a/b/src/c/d', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/*/{dir}/**/{file}'  => [
			'patterns' => [ '/*/src/**/*.php' ],
			'match'    => [ 'a/src', 'a/src/src/src', 'a/src/b/c', ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/b/src/c/d', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'**/{dir}/{file}'     => [
			'patterns' => [ '**/src/*.php' ],
			'match'    => [ 'src', 'a/src', 'a/src/src/src', 'a/b/src', ],
			'skip'     => [ 'src/Deep', 'src/Deep/Nested', 'a/src/b', 'a/src/b/c', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'**/{dir}/*/{file}'   => [
			'patterns' => [ '**/src/*/*.php' ],
			'match'    => [ 'src/Deep', 'a/src/b', 'a/src/src/src', ],
			'skip'     => [ 'src', 'src/Deep/Nested', 'a/src', 'a/b/src/c/d', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'**/{dir}/**/{file}'  => [
			'patterns' => [ '**/src/**/*.php' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src/c/d', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}/{file}'    => [
			'patterns' => [ '/**/src/*.php' ],
			'match'    => [ 'src', 'a/src', 'a/src/src/src', 'a/b/src', ],
			'skip'     => [ 'src/Deep', 'src/Deep/Nested', 'a/src/b', 'a/src/b/c', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}/*/{file}'  => [
			'patterns' => [ '/**/src/*/*.php' ],
			'match'    => [ 'src/Deep', 'a/src/b', 'a/src/src/src', ],
			'skip'     => [ 'src', 'src/Deep/Nested', 'a/src', 'a/b/src/c/d', 'a/src/b/c', 'a/b/src', '/', '.git', 'lib', 'node_modules', ],
		],
		'/**/{dir}/**/{file}' => [
			'patterns' => [ '/**/src/**/*.php' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src/c/d', 'a/b/src', ],
			'skip'     => [ '/', '.git', 'lib', 'node_modules', ],
		],


		'no-hidden'      => [
			'patterns' => [ 'src', '!**/.*' ],
			'match'    => [ 'src', 'src/Deep', 'src/Deep/Nested', ],
			'skip'     => [ 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'negation'       => [
			'patterns' => [ 'src', '!src/Deep' ],
			'match'    => [ 'src', 'src/a/b/c' ],
			'skip'     => [ 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'deep'           => [
			'patterns' => [ 'src/Deep/**' ],
			'match'    => [ 'src/Deep', 'src/Deep/Nested', 'src/Deep/Nested/Nested', ],
			'skip'     => [ 'src', 'src/Nested/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'nested-any'     => [
			'patterns' => [ '**/Deep/**/Nested/*' ],
			'match'    => [ 'src/Deep/Nested', 'src/Deep/Nested/Nested', 'src/Nested/Deep/Nested', 'a/Deep/Nested/Nested', ],
			'skip'     => [ 'src', 'src/Deep', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'nested-single'  => [
			'patterns' => [ '**/Deep/*/Nested/*' ],
			'match'    => [ 'src/Deep/Nested/Nested', 'a/Deep/Nested/Nested', ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'src/Nested/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],
		'deep-one-level' => [
			'patterns' => [ 'src/Deep/Nested/Nested/*' ],
			'match'    => [ 'src/Deep/Nested/Nested', ],
			'skip'     => [ 'src', 'src/Deep', 'src/Deep/Nested', 'src/Nested/Deep/Nested', 'a/Deep/Nested/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', '/', '.git', 'lib', 'node_modules', ],
		],

		'exclude-vendor' => [
			'patterns' => [ '**/*.php', '!vendor', '!node_modules', '!.git', '!cache', '!build', '!tests', '!expected' ],
			'match'    => [ '/', 'src', 'src/Deep', 'src/Deep/Nested', 'a/src', 'a/src/src/src', 'a/src/b', 'a/src/b/c', 'a/b/src', 'a/b/src/c/d', 'lib', ],
			'skip'     => [ '.git', 'node_modules', ],
		],

		'ignore dir include sub-dirs'      => [
			'patterns' => [ 'src', '!src/vendor/*', 'src/vendor/user', ],
			'match'    => [ 'src', 'src/vendor/user', 'src/vendor/user/project', 'src/vendor/user/project/lib', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', ],
			'skip'     => [ 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c', 'a/b/vendor/c/d' ],
		],
		'ignore dir include sub-dir'       => [
			'patterns' => [ 'src', '!src/vendor/*', 'src/vendor/user', '!src/vendor/user/*', 'src/vendor/user/project', ],
			'match'    => [ 'src', 'src/vendor/user/project', 'src/vendor/user/project/lib', ],
			'skip'     => [ 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],
		'stars ignore dir include sub-dir' => [
			'patterns' => [ 'src', '!**/vendor/*', '**/vendor/user', '!**/vendor/user/*', '**/vendor/user/project', ],
			'match'    => [ 'src', 'src/vendor/user/project', 'src/vendor/user/project/lib', 'a/b/vendor/user/project', 'a/b/vendor/user/project/lib', 'vendor/user/project', 'vendor/user/project/lib', ],
			'skip'     => [ 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],
		'terminating directory'            => [ // NOTE: `!vendor` is a hard termination, the following patterns CANNOT undo it!
			'patterns' => [ '!vendor', 'vendor/user', 'vendor/user/*', 'vendor/user/**', 'vendor/user/project', ],
			'match'    => [],
			'skip'     => [ 'src', 'src/vendor/user/project', 'src/vendor/user/project/lib', 'a/b/vendor/user/project', 'a/b/vendor/user/project/lib', 'vendor/user/project', 'vendor/user/project/lib', 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],
		'nested terminating directory'     => [
			'patterns' => [ '!**/vendor/', 'vendor/user', 'vendor/user/*', 'vendor/user/**', 'vendor/user/project', ],
			'match'    => [],
			'skip'     => [ 'src', 'src/vendor/user/project', 'src/vendor/user/project/lib', 'a/b/vendor/user/project', 'a/b/vendor/user/project/lib', 'vendor/user/project', 'vendor/user/project/lib', 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],
		'dotdot terminating directory'     => [
			'patterns' => [ '!vendo[a-z]', 'vendor/user', 'vendor/user/*', 'vendor/user/**', 'vendor/user/project', ],
			'match'    => [],
			'skip'     => [ 'src', 'src/vendos/user', 'src/vendor/user/project/lib', 'a/b/vendor/user/project', 'a/b/vendor/user/project/lib', 'vendor/user/project', 'vendor/user/project/lib', 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],
		'glob terminating directory'       => [
			'patterns' => [ '!**/v*', 'vendor/user', 'vendor/user/*', 'vendor/user/**', 'vendor/user/project', ],
			'match'    => [],
			'skip'     => [ 'src', 'src/vendor/user/project', 'src/vendor/user/project/lib', 'a/b/vendor/user/project', 'a/b/vendor/user/project/lib', 'vendor/user/project', 'vendor/user/project/lib', 'src/vendor/user', 'src/vendor/user/project2', 'src/vendor/user/project2/lib', 'vendor', 'a/vendor', 'a/b/vendor', 'src/vendor', 'a/b/vendor/c/d' ],
		],

	] );

} );
