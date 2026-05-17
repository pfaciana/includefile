<?php

declare( strict_types=1 );

use Render\IncludeFile;

describe( 'normalize', function () {

	describe( 'slash normalization', function () {

		it( 'converts backslashes to forward slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'single backslash'     => [ 'path\\file', 'path/file' ],
			'multiple backslashes' => [ 'path\\to\\file', 'path/to/file' ],
			'windows absolute'     => [ 'C:\\Users\\Phil\\file.php', 'C:/Users/Phil/file.php' ],
			'deep nesting'         => [ 'a\\b\\c\\d\\e\\f', 'a/b/c/d/e/f' ],
			'trailing backslash'   => [ 'path\\to\\dir\\', 'path/to/dir/' ],
			'leading backslash'    => [ '\\path\\to\\file', '/path/to/file' ],
		] );

		it( 'preserves forward slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'unix absolute'  => [ '/var/www/html', '/var/www/html' ],
			'relative path'  => [ 'src/lib/file.php', 'src/lib/file.php' ],
			'trailing slash' => [ '/var/www/', '/var/www/' ],
			'single slash'   => [ '/', '/' ],
		] );

		it( 'normalizes mixed slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'mixed forward/back'   => [ 'path/to\\file', 'path/to/file' ],
			'alternating'          => [ 'a/b\\c/d\\e', 'a/b/c/d/e' ],
			'windows with forward' => [ 'C:/Users\\Phil', 'C:/Users/Phil' ],
		] );

	} );

	describe( 'multiple slash collapse', function () {

		it( 'collapses multiple slashes to single', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'double slash mid'  => [ 'path//file', 'path/file' ],
			'triple slash mid'  => [ 'path///file', 'path/file' ],
			'many slashes mid'  => [ 'path/////file', 'path/file' ],
			'multiple segments' => [ 'a//b//c', 'a/b/c' ],
			'trailing double'   => [ 'path//', 'path/' ],
			'mixed multiple'    => [ 'path\\\\to//file', 'path/to/file' ],
		] );

		it( 'preserves leading double slash for network shares', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'UNC forward'      => [ '//server/share', '//server/share' ],
			'UNC backslash'    => [ '\\\\server\\share', '//server/share' ],
			'UNC with path'    => [ '\\\\server\\share\\path', '//server/share/path' ],
			'UNC triple start' => [ '///server/share', '//server/share' ],
		] );

		it( 'handles edge cases with leading slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'single leading' => [ '/path', '/path' ],
			'double leading' => [ '//path', '//path' ],
			'triple leading' => [ '///path', '//path' ],
			'quad leading'   => [ '////path', '//path' ],
		] );

	} );

	describe( 'Windows drive letter', function () {

		it( 'uppercases lowercase drive letters', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'lowercase c' => [ 'c:\\Users', 'C:/Users' ],
			'lowercase d' => [ 'd:/Projects', 'D:/Projects' ],
			'lowercase e' => [ 'e:\\data\\file.txt', 'E:/data/file.txt' ],
			'lowercase z' => [ 'z:\\', 'Z:/' ],
		] );

		it( 'preserves uppercase drive letters', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'uppercase C' => [ 'C:\\Users', 'C:/Users' ],
			'uppercase D' => [ 'D:/Projects', 'D:/Projects' ],
			'uppercase E' => [ 'E:\\data\\file.txt', 'E:/data/file.txt' ],
		] );

		it( 'handles drive letter edge cases', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'drive only'       => [ 'c:', 'C:' ],
			'drive with slash' => [ 'c:/', 'C:/' ],
			'drive backslash'  => [ 'c:\\', 'C:/' ],
		] );

	} );

	describe( 'special paths', function () {

		it( 'handles relative paths', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'current dir'      => [ '.', '.' ],
			'parent dir'       => [ '..', '..' ],
			'dot prefix'       => [ './path/file', './path/file' ],
			'dotdot prefix'    => [ '../path/file', '../path/file' ],
			'multiple dotdot'  => [ '../../path', '../../path' ],
			'dot backslash'    => [ '.\\path\\file', './path/file' ],
			'dotdot backslash' => [ '..\\path\\file', '../path/file' ],
		] );

		it( 'handles empty and minimal input', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'     => [ '', '' ],
			'single char'      => [ 'a', 'a' ],
			'single slash'     => [ '/', '/' ],
			'single backslash' => [ '\\', '/' ],
		] );

		it( 'handles paths with special characters', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'spaces'            => [ 'path/to/my file.txt', 'path/to/my file.txt' ],
			'dots in name'      => [ 'file.name.ext', 'file.name.ext' ],
			'hyphen underscore' => [ 'my-file_name', 'my-file_name' ],
			'parentheses'       => [ 'path/(copy)', 'path/(copy)' ],
		] );

	} );

	describe( 'colon edge cases', function () {

		it( 'only uppercases when colon is at position 1', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'colon at pos 1'    => [ 'c:', 'C:' ],
			'colon at pos 2'    => [ 'cd:', 'cd:' ],
			'colon later'       => [ 'path:name', 'path:name' ],
			'multiple colons'   => [ 'a:b:c', 'A:b:c' ],
			'colon in filename' => [ '/path/file:stream', '/path/file:stream' ],
		] );

	} );

	describe( 'full path examples', function () {

		it( 'normalizes real-world paths', function ( string $input, string $expected ) {
			expect( IncludeFile::normalize( $input ) )->toBe( $expected );
		} )->with( [
			'windows project' => [
				'd:\\Webs\\newalbanypresbyterian\\public_html',
				'D:/Webs/newalbanypresbyterian/public_html',
			],
			'linux web root'  => [
				'/var/www/html/wp-content/themes',
				'/var/www/html/wp-content/themes',
			],
			'mac home dir'    => [
				'/Users/developer/Sites/project',
				'/Users/developer/Sites/project',
			],
			'network share'   => [
				'\\\\fileserver\\shared\\documents',
				'//fileserver/shared/documents',
			],
			'messy windows'   => [
				'c:\\\\Users\\\\Phil\\\\\\Documents',
				'C:/Users/Phil/Documents',
			],
			'messy unix'      => [
				'/var//www///html',
				'/var/www/html',
			],
		] );

	} );

} );

describe( 'is_absolute_path', function () {

	describe( 'Unix absolute paths', function () {

		it( 'detects Unix absolute paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'root'             => [ '/' ],
			'simple path'      => [ '/var' ],
			'deep path'        => [ '/var/www/html' ],
			'trailing slash'   => [ '/var/www/' ],
			'home dir'         => [ '/home/user' ],
			'tmp'              => [ '/tmp/file.txt' ],
		] );

	} );

	describe( 'Windows absolute paths', function () {

		it( 'detects Windows backslash paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'C drive'          => [ 'C:\\' ],
			'C with path'      => [ 'C:\\Users' ],
			'D drive'          => [ 'D:\\Projects' ],
			'lowercase drive'  => [ 'c:\\Users' ],
			'deep path'        => [ 'C:\\Users\\Phil\\Documents' ],
		] );

		it( 'detects backslash-only paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'single backslash' => [ '\\' ],
			'backslash path'   => [ '\\Windows\\System32' ],
		] );

	} );

	describe( 'UNC paths', function () {

		it( 'detects UNC network paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'forward slash UNC'  => [ '//server/share' ],
			'backslash UNC'      => [ '\\\\server\\share' ],
			'UNC with path'      => [ '//server/share/folder' ],
		] );

	} );

	describe( 'relative paths (returns false)', function () {

		it( 'detects relative paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeFalse();
		} )->with( [
			'simple name'      => [ 'file.txt' ],
			'path segments'    => [ 'path/to/file' ],
			'current dir'      => [ '.' ],
			'parent dir'       => [ '..' ],
			'dot prefix'       => [ './path' ],
			'dotdot prefix'    => [ '../path' ],
			'deep relative'    => [ '../../path/to/file' ],
			'dotfile'          => [ '.gitignore' ],
			'dot dir'          => [ '.config/settings' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles empty and minimal input', function ( string $input, bool $expected ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'     => [ '', FALSE ],
			'single char'      => [ 'a', FALSE ],
			'single dot'       => [ '.', FALSE ],
			'single slash'     => [ '/', TRUE ],
			'single backslash' => [ '\\', TRUE ],
		] );

		it( 'handles Windows forward slash paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'C:/'              => [ 'C:/' ],
			'C:/Users'         => [ 'C:/Users' ],
			'D:/Projects'      => [ 'D:/Projects' ],
		] );

		it( 'handles drive-like patterns', function ( string $input, bool $expected ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBe( $expected );
		} )->with( [
			'drive only'       => [ 'C:', FALSE ],
			'drive colon'      => [ 'C:file', FALSE ],
			'not a drive'      => [ '1:\\path', FALSE ],
			'long prefix'      => [ 'CD:\\path', FALSE ],
		] );

	} );

	describe( 'special characters', function () {

		it( 'handles paths with special characters', function ( string $input, bool $expected ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBe( $expected );
		} )->with( [
			'space in path'    => [ '/path/my file', TRUE ],
			'unicode'          => [ '/путь/файл', TRUE ],
			'relative unicode' => [ 'путь/файл', FALSE ],
			'relative space'   => [ 'my file.txt', FALSE ],
		] );

	} );

	describe( 'normalized path support', function () {

		it( 'handles both forward and backslash Windows paths', function ( string $input ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBeTrue();
		} )->with( [
			'normalized C:/'      => [ 'C:/Users/Phil' ],
			'raw C:\\'            => [ 'C:\\Users\\Phil' ],
			'mixed C:/\\'         => [ 'C:/Users\\Phil' ],
			'normalized D:/'      => [ 'D:/Projects' ],
			'lowercase c:/'       => [ 'c:/Users' ],
			'lowercase c:\\'      => [ 'c:\\Users' ],
		] );

	} );

	describe( 'ambiguous patterns', function () {

		it( 'handles ambiguous path patterns', function ( string $input, bool $expected ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBe( $expected );
		} )->with( [
			'colon only'          => [ ':', FALSE ],
			'colon path'          => [ ':path', FALSE ],
			'slash colon'         => [ '/:', TRUE ],
			'backslash colon'     => [ '\\:', TRUE ],
			'double colon'        => [ 'C::', FALSE ],
			'url-like'            => [ 'http://example.com', FALSE ],
			'file url'            => [ 'file:///path', FALSE ],
		] );

	} );

	describe( 'whitespace edge cases', function () {

		it( 'handles whitespace in paths', function ( string $input, bool $expected ) {
			expect( IncludeFile::is_absolute_path( $input ) )->toBe( $expected );
		} )->with( [
			'leading space'       => [ ' /path', FALSE ],
			'trailing space'      => [ '/path ', TRUE ],
			'space only'          => [ ' ', FALSE ],
			'tab prefix'          => [ "\t/path", FALSE ],
			'newline prefix'      => [ "\n/path", FALSE ],
		] );

	} );

} );

describe( 'add_base', function () {

	describe( 'relative paths get base prepended', function () {

		it( 'prepends base to relative paths', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'simple'               => [ 'file.txt', '/var/www', '/var/www/file.txt' ],
			'nested path'          => [ 'src/file.php', '/var/www', '/var/www/src/file.php' ],
			'base trailing slash'  => [ 'file.txt', '/var/www/', '/var/www/file.txt' ],
			'base multi slash'     => [ 'file.txt', '/var/www///', '/var/www/file.txt' ],
			'dot prefix'           => [ './file.txt', '/var/www', '/var/www/./file.txt' ],
			'dotdot prefix'        => [ '../file.txt', '/var/www', '/var/www/../file.txt' ],
			'windows base'         => [ 'file.txt', 'C:\\Users', 'C:\\Users/file.txt' ],
		] );

	} );

	describe( 'absolute paths unchanged', function () {

		it( 'returns absolute paths unchanged', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'unix absolute'        => [ '/etc/config', '/var/www', '/etc/config' ],
			'windows absolute'     => [ 'C:\\Users\\file', '/var/www', 'C:\\Users\\file' ],
			'backslash absolute'   => [ '\\Windows\\System32', '/base', '\\Windows\\System32' ],
			'UNC path'             => [ '//server/share', '/base', '//server/share' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'empty path'           => [ '', '/var/www', '/var/www/' ],
			'empty base'           => [ 'file.txt', '', '/file.txt' ],
			'both empty'           => [ '', '', '/' ],
			'just dot'             => [ '.', '/var/www', '/var/www/.' ],
			'just dotdot'          => [ '..', '/var/www', '/var/www/..' ],
			'root as base'         => [ 'file.txt', '/', '/file.txt' ],
		] );

	} );

	describe( 'real-world examples', function () {

		it( 'handles real-world paths', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'plugin file'          => [ 'src/Plugin.php', '/var/www/wp-content/plugins/myplugin', '/var/www/wp-content/plugins/myplugin/src/Plugin.php' ],
			'theme asset'          => [ 'assets/css/style.css', '/var/www/wp-content/themes/theme', '/var/www/wp-content/themes/theme/assets/css/style.css' ],
			'deep nesting'         => [ 'a/b/c/d.php', '/x/y/z', '/x/y/z/a/b/c/d.php' ],
		] );

	} );

	describe( 'normalized Windows paths', function () {

		it( 'treats normalized Windows paths as absolute', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'C:/ is absolute'      => [ 'C:/Users', '/base', 'C:/Users' ],
			'D:/ is absolute'      => [ 'D:/Projects', '/base', 'D:/Projects' ],
			'raw C:\\ is absolute' => [ 'C:\\Users', '/base', 'C:\\Users' ],
			'lowercase c:/'        => [ 'c:/Users', '/base', 'c:/Users' ],
		] );

	} );

	describe( 'slash edge cases', function () {

		it( 'handles slash edge cases', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'path with leading slash (abs)'  => [ '/file.txt', '/base', '/file.txt' ],
			'base only slash'                => [ 'file', '/', '/file' ],
			'path backslash (abs)'           => [ '\\file.txt', '/base', '\\file.txt' ],
			'double slash base'              => [ 'file', '/base//', '/base/file' ],
		] );

	} );

	describe( 'special characters in add_base', function () {

		it( 'handles special characters', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::add_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'space in path'        => [ 'my file.txt', '/var/www', '/var/www/my file.txt' ],
			'space in base'        => [ 'file.txt', '/var/my www', '/var/my www/file.txt' ],
			'unicode path'         => [ 'файл.txt', '/var/www', '/var/www/файл.txt' ],
			'unicode base'         => [ 'file.txt', '/путь', '/путь/file.txt' ],
		] );

	} );

} );

describe( 'strip_base', function () {

	describe( 'matching base stripped', function () {

		it( 'strips matching base prefix', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'exact prefix'         => [ '/var/www/file.txt', '/var/www/', 'file.txt' ],
			'no trailing slash'    => [ '/var/www/file.txt', '/var/www', '/file.txt' ],
			'deep strip'           => [ '/var/www/html/wp/file.php', '/var/www/html/', 'wp/file.php' ],
			'windows path'         => [ 'C:\\Users\\Phil\\file.txt', 'C:\\Users\\Phil\\', 'file.txt' ],
			'strip to root'        => [ '/var/www', '/var/www', '' ],
		] );

	} );

	describe( 'non-matching base unchanged', function () {

		it( 'returns path unchanged when base does not match', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'different path'       => [ '/etc/config', '/var/www/', '/etc/config' ],
			'partial match'        => [ '/var/wwwroot/file', '/var/www/', '/var/wwwroot/file' ],
			'case mismatch'        => [ '/VAR/WWW/file', '/var/www/', '/VAR/WWW/file' ],
			'shorter path'         => [ '/var', '/var/www/', '/var' ],
			'no common prefix'     => [ 'relative/path', '/var/www/', 'relative/path' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'empty path'           => [ '', '/var/www/', '' ],
			'empty base'           => [ '/var/www/file.txt', '', '/var/www/file.txt' ],
			'both empty'           => [ '', '', '' ],
			'base equals path'     => [ '/var/www/', '/var/www/', '' ],
			'path is base prefix'  => [ '/var', '/var/www/', '/var' ],
		] );

	} );

	describe( 'boundary cases (no directory awareness)', function () {

		it( 'strips purely by string prefix (no boundary check)', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'mid-segment strip'    => [ '/var/www/html', '/var/ww', 'w/html' ],
			'mid-word strip'       => [ '/variable/path', '/var', 'iable/path' ],
			'filename prefix'      => [ '/path/file.txt', '/path/file', '.txt' ],
		] );

	} );

	describe( 'real-world examples', function () {

		it( 'handles real-world paths', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'plugin relative'      => [ '/var/www/wp-content/plugins/myplugin/src/Plugin.php', '/var/www/wp-content/plugins/myplugin/', 'src/Plugin.php' ],
			'theme relative'       => [ '/var/www/wp-content/themes/theme/style.css', '/var/www/wp-content/themes/theme/', 'style.css' ],
			'windows project'      => [ 'D:\\Webs\\project\\src\\file.php', 'D:\\Webs\\project\\', 'src\\file.php' ],
		] );

	} );

	describe( 'slash variation edge cases', function () {

		it( 'handles slash variations', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'base no slash, path has'  => [ '/var/www/file', '/var/www', '/file' ],
			'base slash, path no'      => [ '/var/www', '/var/www/', '/var/www' ],
			'double slash in base'     => [ '/var//www/file', '/var//www/', 'file' ],
			'mixed slashes no match'   => [ '/var/www/file', '/var\\www/', '/var/www/file' ],
		] );

	} );

	describe( 'special characters in strip_base', function () {

		it( 'handles special characters', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'space in path'        => [ '/var/my www/file.txt', '/var/my www/', 'file.txt' ],
			'unicode'              => [ '/путь/файл.txt', '/путь/', 'файл.txt' ],
			'unicode no match'     => [ '/путь/файл.txt', '/путь2/', '/путь/файл.txt' ],
		] );

	} );

	describe( 'base longer than path', function () {

		it( 'handles base longer than path', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'base much longer'     => [ '/var', '/var/www/html/deep/path/', '/var' ],
			'base one char longer' => [ '/var/www', '/var/www/', '/var/www' ],
		] );

	} );

	describe( 'overlapping prefixes', function () {

		it( 'handles tricky overlapping prefixes', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'www vs wwwroot'       => [ '/var/wwwroot/file', '/var/www', 'root/file' ],
			'app vs application'   => [ '/var/application/file', '/var/app', 'lication/file' ],
			'test vs testing'      => [ '/var/testing/file', '/var/test', 'ing/file' ],
			'exact match only'     => [ '/var/www-backup/file', '/var/www', '-backup/file' ],
		] );

	} );

	describe( 'empty result edge cases', function () {

		it( 'handles empty results', function ( string $path, string $base, string $expected ) {
			expect( IncludeFile::strip_base( $path, $base ) )->toBe( $expected );
		} )->with( [
			'exact match'          => [ '/var/www/', '/var/www/', '' ],
			'exact no slash'       => [ '/var/www', '/var/www', '' ],
			'strips everything'    => [ 'abc', 'abc', '' ],
		] );

	} );

} );

describe( 'add_trailing_slash', function () {

	describe( 'adds trailing slash', function () {

		it( 'adds slash to paths without trailing slash', function ( string $input, string $expected ) {
			expect( IncludeFile::add_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'simple path'          => [ '/var/www', '/var/www/' ],
			'filename'             => [ 'file.txt', 'file.txt/' ],
			'windows path'         => [ 'C:\\Users\\Phil', 'C:\\Users\\Phil/' ],
			'single segment'       => [ 'path', 'path/' ],
			'root'                 => [ '', '/' ],
		] );

	} );

	describe( 'normalizes existing trailing slashes', function () {

		it( 'replaces trailing backslash with forward slash', function ( string $input, string $expected ) {
			expect( IncludeFile::add_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'trailing backslash'   => [ 'path\\', 'path/' ],
			'windows trailing'     => [ 'C:\\Users\\', 'C:\\Users/' ],
			'unix trailing'        => [ '/var/www/', '/var/www/' ],
		] );

		it( 'collapses multiple trailing slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::add_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'double slash'         => [ 'path//', 'path/' ],
			'triple slash'         => [ 'path///', 'path/' ],
			'mixed slashes'        => [ 'path/\\', 'path/' ],
			'all backslashes'      => [ 'path\\\\', 'path/' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $input, string $expected ) {
			expect( IncludeFile::add_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'         => [ '', '/' ],
			'single slash'         => [ '/', '/' ],
			'single backslash'     => [ '\\', '/' ],
			'only slashes'         => [ '///', '/' ],
			'dot'                  => [ '.', './' ],
			'dotdot'               => [ '..', '../' ],
		] );

	} );

} );

describe( 'strip_trailing_slash', function () {

	describe( 'removes trailing slashes', function () {

		it( 'removes trailing forward slash', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'simple path'          => [ '/var/www/', '/var/www' ],
			'deep path'            => [ '/var/www/html/', '/var/www/html' ],
			'root slash'           => [ '/', '' ],
			'windows path'         => [ 'C:/Users/', 'C:/Users' ],
		] );

		it( 'removes trailing backslash', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'single backslash'     => [ 'path\\', 'path' ],
			'windows trailing'     => [ 'C:\\Users\\', 'C:\\Users' ],
			'root backslash'       => [ '\\', '' ],
		] );

		it( 'removes multiple trailing slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'double forward'       => [ 'path//', 'path' ],
			'triple forward'       => [ 'path///', 'path' ],
			'double backslash'     => [ 'path\\\\', 'path' ],
			'mixed'                => [ 'path/\\/', 'path' ],
		] );

	} );

	describe( 'paths without trailing slash unchanged', function () {

		it( 'returns path unchanged', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'no trailing'          => [ '/var/www', '/var/www' ],
			'filename'             => [ 'file.txt', 'file.txt' ],
			'dot extension'        => [ '/path/file.php', '/path/file.php' ],
			'windows no trailing'  => [ 'C:\\Users', 'C:\\Users' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_trailing_slash( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'         => [ '', '' ],
			'single slash'         => [ '/', '' ],
			'single backslash'     => [ '\\', '' ],
			'only slashes'         => [ '///', '' ],
			'dot'                  => [ '.', '.' ],
			'dotdot'               => [ '..', '..' ],
			'dot slash'            => [ './', '.' ],
		] );

	} );

} );

describe( 'add_preceding_slash', function () {

	describe( 'adds preceding slash', function () {

		it( 'adds slash to paths without preceding slash', function ( string $input, string $expected ) {
			expect( IncludeFile::add_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'simple path'          => [ 'var/www', '/var/www' ],
			'filename'             => [ 'file.txt', '/file.txt' ],
			'single segment'       => [ 'path', '/path' ],
			'deep path'            => [ 'a/b/c/d', '/a/b/c/d' ],
		] );

	} );

	describe( 'normalizes existing preceding slashes', function () {

		it( 'replaces preceding backslash with forward slash', function ( string $input, string $expected ) {
			expect( IncludeFile::add_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'preceding backslash'  => [ '\\path', '/path' ],
			'backslash path'       => [ '\\Windows\\System32', '/Windows\\System32' ],
		] );

		it( 'collapses multiple preceding slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::add_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'double slash'         => [ '//path', '/path' ],
			'triple slash'         => [ '///path', '/path' ],
			'mixed slashes'        => [ '/\\path', '/path' ],
			'all backslashes'      => [ '\\\\path', '/path' ],
		] );

		it( 'preserves single forward slash', function ( string $input, string $expected ) {
			expect( IncludeFile::add_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'already slashed'      => [ '/var/www', '/var/www' ],
			'root path'            => [ '/etc', '/etc' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $input, string $expected ) {
			expect( IncludeFile::add_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'         => [ '', '/' ],
			'single slash'         => [ '/', '/' ],
			'single backslash'     => [ '\\', '/' ],
			'only slashes'         => [ '///', '/' ],
			'dot'                  => [ '.', '/.' ],
			'dotdot'               => [ '..', '/..' ],
			'dot prefix'           => [ './path', '/./path' ],
		] );

	} );

} );

describe( 'strip_preceding_slash', function () {

	describe( 'removes preceding slashes', function () {

		it( 'removes preceding forward slash', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'unix path'            => [ '/var/www', 'var/www' ],
			'deep path'            => [ '/var/www/html', 'var/www/html' ],
			'root slash'           => [ '/', '' ],
			'single segment'       => [ '/path', 'path' ],
		] );

		it( 'removes preceding backslash', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'single backslash'     => [ '\\path', 'path' ],
			'windows style'        => [ '\\Windows\\System32', 'Windows\\System32' ],
			'root backslash'       => [ '\\', '' ],
		] );

		it( 'removes multiple preceding slashes', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'double forward'       => [ '//path', 'path' ],
			'triple forward'       => [ '///path', 'path' ],
			'double backslash'     => [ '\\\\server', 'server' ],
			'mixed'                => [ '/\\/path', 'path' ],
		] );

	} );

	describe( 'paths without preceding slash unchanged', function () {

		it( 'returns path unchanged', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'relative path'        => [ 'var/www', 'var/www' ],
			'filename'             => [ 'file.txt', 'file.txt' ],
			'dot prefix'           => [ './path', './path' ],
			'dotdot prefix'        => [ '../path', '../path' ],
			'windows drive'        => [ 'C:\\Users', 'C:\\Users' ],
		] );

	} );

	describe( 'edge cases', function () {

		it( 'handles edge cases', function ( string $input, string $expected ) {
			expect( IncludeFile::strip_preceding_slash( $input ) )->toBe( $expected );
		} )->with( [
			'empty string'         => [ '', '' ],
			'single slash'         => [ '/', '' ],
			'single backslash'     => [ '\\', '' ],
			'only slashes'         => [ '///', '' ],
			'dot'                  => [ '.', '.' ],
			'dotdot'               => [ '..', '..' ],
		] );

	} );

} );