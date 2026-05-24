<?php

declare( strict_types=1 );

namespace Render;

use Iterator;
use Automattic\IgnoreFile;
use Automattic\IgnoreFile\InvalidPatternException;

class IncludeFile
{
	public array $patterns = [];

	private IgnoreFile $ignoreFile;

	/**
	 * Constructor.
	 *
	 * @param string|string[] $patterns Patterns to add.
	 * @param string          $prefix   All (string) patterns must match relative to this prefix.
	 */
	public function __construct ()
	{
		$this->ignoreFile = new IgnoreFile();
		// Strict mode by default.
		$this->ignoreFile->strictMode = TRUE;
		// Add patterns if any.
		if ( func_num_args() > 0 ) {
			$this->add( ...func_get_args() );
		}
	}

	/**
	 * Set to throw on invalid patterns.
	 *
	 * @param bool $strictMode whether to throw on invalid patterns
	 */
	public function setStrictMode ( bool $strictMode ): void
	{
		$this->ignoreFile->strictMode = $strictMode;
	}

	/**
	 * Add one or more pattern lines.
	 *
	 * The `$prefix` is intended for use when you're loading multiple include pattern sets from
	 * multiple places in the filesystem. As you load each pattern set, pass its path
	 * as `$prefix` (either relative to a common base or absolute).
	 *
	 * @throws \InvalidArgumentException If arguments are invalid.
	 * @throws InvalidPatternException If patterns are invalid and `$this->strictMode` is set.
	 *
	 * @param string|string[] $patterns Patterns to add.
	 * @param string          $prefix   All (string) patterns must match relative to this prefix.
	 */
	public function add ( $patterns, $prefix = '' )
	{
		$this->patterns = array_merge( $this->patterns, is_string( $patterns ) ? explode( "\n", $patterns ) : $patterns );
		$this->ignoreFile->add( static::invert_patterns( $patterns ), $prefix );
	}

	/**
	 * Filter an array of paths to keep only included paths.
	 *
	 * See `includes()` for notes on how to pass paths.
	 *
	 * @param (string|\SplFileInfo)[] $paths Paths.
	 * @return (string|\SplFileInfo)[]
	 */
	public function filter ( array $paths )
	{
		return array_filter( $paths, [ $this, 'includes' ], ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * Indicate whether the path is included.
	 *
	 * If `$prefix` was used with `add()`, the paths passed should be equivalent (relative to the
	 * same common base, or absolute if `add()` was passed absolute paths).
	 *
	 * Directories must include a trailing `/` to be recognized as such. Without that, they'll be
	 * treated as files.
	 *
	 * @param string|\SplFileInfo $path Path to test.
	 * @return bool If the path is included.
	 */
	public function includes ( $path )
	{
		return !$this->ignoreFile->ignores( ...func_get_args() );
	}

	/**
	 * Indicate whether the path is not included.
	 *
	 * This is, basically, `! $this->includes( $path )`. It's convenient for
	 * passing to `array_filter()` or the like.
	 *
	 * @param string|\SplFileInfo $path Path to test.
	 * @return bool If the path is not included.
	 */
	public function notIncludes ( $path )
	{
		return !$this->ignoreFile->notIgnores( ...func_get_args() );
	}

	/**
	 * Indicate whether the path is included or not included.
	 *
	 * There are three possible return values:
	 *
	 * - `array( 'included' => true, 'notIncluded' => false, 'pattern' => string )` => Path is included.
	 * - `array( 'included' => false, 'notIncluded' => true, 'pattern' => string )` => Path is not included.
	 * - `array( 'included' => false, 'notIncluded' => false, 'pattern' => null )` => Path matched no include or exclude pattern.
	 *
	 * @param string|\SplFileInfo $path Path to test.
	 * @return array{included: bool, notIncluded: bool, pattern: ?string} As above.
	 */
	public function test ( $path )
	{
		$result = $this->ignoreFile->test( ...func_get_args() );

		return [
			'included'    => $result['unignored'],
			'notIncluded' => $result['ignored'],
			'pattern'     => $result['pattern'],
		];
	}

	/**
	 * Filter an iterator to keep only included paths.
	 *
	 * When using FileSystemIterator or its subclasses, do not use CURRENT_AS_PATHNAME
	 * as that does not include the necessary trailing `/` on directory names.
	 *
	 * @param Iterator $iter Iterator to filter.
	 * @return \FilterIterator|\FilterIterator&\RecursiveIterator Filtered iterator. If the input implements `RecursiveIterator`, the returned iterator does too.
	 */
	public function filterIterator ( Iterator $iter )
	{
		if ( $iter instanceof \RecursiveIterator ) {
			return new \RecursiveCallbackFilterIterator( $iter, [ $this, 'includes' ] );
		}

		return new \CallbackFilterIterator( $iter, [ $this, 'includes' ] );
	}

	/**
	 * Get directory patterns needed to traverse paths for the current include patterns.
	 *
	 * File patterns are converted to their containing directory pattern.
	 * Directory patterns are preserved.
	 * Negated file patterns and blank lines are omitted because they do not identify a directory that needs traversal.
	 *
	 * @param bool $invert      Whether to invert the directory patterns for IgnoreFile consumption.
	 * @param bool $includeBase Whether inverted patterns should include the default base rules.
	 *
	 * @return string[] Directory patterns.
	 */
	public function get_directory_patterns ( bool $invert = FALSE, bool $includeBase = TRUE )
	{
		$directory_patterns = [];
		foreach ( $this->patterns as $pattern ) {
			$directory_pattern = static::to_directory_pattern( $pattern );
			if ( $directory_pattern !== FALSE && $directory_pattern !== '' ) {
				$directory_patterns[] = $directory_pattern;
			}
		}

		if ( $invert ) {
			$directory_patterns = static::invert_patterns( $directory_patterns, $includeBase );
		}

		return $directory_patterns;
	}

	/**
	 * Convert a file include pattern to the directory pattern needed to reach it.
	 *
	 * This method only converts file patterns. Directory patterns, comments, and blank lines are
	 * returned unchanged. Negated file patterns return false because an excluded file does not
	 * require directory traversal.
	 *
	 * Examples:
	 * - `file.php` => `*`
	 * - `src/file.php` => `src/*`
	 * - `@file:bin/console` => `bin/*`
	 * - `!file.php` => false
	 *
	 * @param string $pattern Include pattern to convert.
	 *
	 * @return string|false Directory pattern, original non-file pattern, or false for negated files.
	 */
	public static function to_directory_pattern ( string $pattern ): string|false
	{
		if ( FALSE === ( $file_pattern = static::is_file_pattern( $pattern ) ) ) {
			return $pattern;
		}

		if ( str_starts_with( $file_pattern, '!' ) ) {
			return FALSE;
		}

		if ( str_starts_with( $file_pattern, '@file:' ) ) {
			$file_pattern = substr( $file_pattern, 6 );
		}

		if ( !str_contains( $file_pattern, '/' ) ) {
			return '*';
		}

		$directory = rtrim( substr( $file_pattern, 0, strrpos( $file_pattern, '/' ) ), '/' ) . '/*';

		return $directory !== '' ? $directory : FALSE;
	}

	public static function get_terminating_directory_instance ( array $patterns ): static|false
	{
		$terminatingPatterns = array_filter( $patterns, fn( $pattern ) => static::is_dir_termination_pattern( $pattern ) );

		if ( empty( $terminatingPatterns ) ) {
			return FALSE;
		}

		return new static( [ '*', ...$terminatingPatterns ] );
	}

	/**
	 * Get a traversal filter that prunes directories excluded by terminating patterns.
	 *
	 * @deprecated Use getFilter() instead.
	 *
	 * @param string $baseDir Base directory used to make traversed paths relative.
	 * @param array{
	 *     allowLinks?: bool
	 * }             $config  Optional traversal config.
	 *
	 * @return callable|false RecursiveCallbackFilterIterator callback, or false when no filter is needed.
	 */
	public function getDefaultCallbackFilter ( string $baseDir, array $config = [] ): callable|false
	{
		return $this->getFilter( ...func_get_args() );
	}

	/**
	 * Get a traversal filter that prunes directories excluded by terminating patterns.
	 *
	 * @param string $baseDir Base directory used to make traversed paths relative.
	 * @param array{
	 *      allowLinks?: bool
	 *  }            $config  Optional traversal config.
	 *
	 * @return callable|false RecursiveCallbackFilterIterator callback, or false when no filter is needed.
	 */
	public function getFilter ( string $baseDir, array $config = [] ): callable|false
	{
		$config = array_merge( [
			'allowLinks' => FALSE,
		], $config );

		$includeDirs = static::get_terminating_directory_instance( $this->patterns );

		$baseDir = static::add_trailing_slash( static::normalize( $baseDir ) );

		return function ( $fileInfo, $absPath, $iterator ) use ( $baseDir, $includeDirs, $config ): bool {
			$relPath = static::strip_base( $absPath, $baseDir );

			if ( $iterator->hasChildren( $config['allowLinks'] ) ) {
				return $includeDirs ? $includeDirs->includes( $relPath ) : TRUE;
			}

			return $this->includes( $relPath );
		};
	}

	/**
	 * Make a traversal filter with optional short-circuit callbacks.
	 *
	 * The `dir` callback receives the iterator item, absolute path, relative path, base directory,
	 * terminating-directory matcher, and current IncludeFile instance. The `file` callback
	 * receives the same values with current IncludeFile instance before terminating-directory matcher.
	 * Return a bool to short-circuit the default filter, or null to continue to the default behavior.
	 *
	 * @param string $baseDir Base directory used to make traversed paths relative.
	 * @param string|array{
	 *     dir?:  callable|null,
	 *     file?: callable|string|null,
	 *     allowLinks?: bool
	 * }             $config  Optional short-circuit callbacks.
	 *
	 * @return callable|false RecursiveCallbackFilterIterator callback, or false when no filter is needed.
	 */
	public function makeFilter ( string $baseDir, string|array $config = [] ): callable|false
	{
		if ( is_string( $config ) ) {
			$config = [ 'file' => $config ];
		}

		$config = array_merge( [
			'dir'        => NULL,
			'file'       => NULL,
			'allowLinks' => FALSE,
		], $config );

		if ( is_string( $config['file'] ) ) {
			$config['file'] = ltrim( strtolower( $config['file'] ), '*.' );
			if ( str_contains( $config['file'], '.' ) ) {
				$config['file'] = '.' . $config['file'];
				$config['file'] = fn( $fileInfo ) => substr_compare( $fileInfo->getFilename(), $config['file'], -strlen( $config['file'] ), NULL, TRUE ) === 0 ? NULL : FALSE;
			}
			else {
				$config['file'] = fn( $fileInfo ) => strcasecmp( $fileInfo->getExtension(), $config['file'] ) === 0 ? NULL : FALSE;
			}
		}

		foreach ( [ 'file', 'dir' ] as $key ) {
			if ( !is_callable( $config[$key] ) ) {
				$config[$key] = NULL;
			}
		}

		$includeDirs = static::get_terminating_directory_instance( $this->patterns );

		$baseDir = static::add_trailing_slash( static::normalize( $baseDir ) );

		return function ( $fileInfo, $absPath, $iterator ) use ( $baseDir, $includeDirs, $config ): bool {
			$relPath = static::strip_base( $absPath, $baseDir );

			if ( $iterator->hasChildren( $config['allowLinks'] ) ) {
				if ( $config['dir'] && is_bool( $result = $config['dir']( $iterator, $fileInfo, $absPath, $relPath, $baseDir, $includeDirs, $this ) ) ) {
					return $result;
				}

				return $includeDirs ? $includeDirs->includes( $relPath ) : TRUE;
			}

			if ( $config['file'] && is_bool( $result = $config['file']( $fileInfo, $iterator, $absPath, $relPath, $baseDir, $this, $includeDirs ) ) ) {
				return $result;
			}

			return $this->includes( $relPath );
		};
	}

	/**
	 * Determine whether a negated directory pattern should terminate traversal.
	 *
	 * A terminating directory pattern excludes a directory in a way that descendant include
	 * patterns cannot undo during filesystem traversal. Open-ended exclusions like `!vendor/*`
	 * and `!vendor/**` are not terminal because later descendants may still be reachable.
	 *
	 * @param string $pattern Include pattern to inspect.
	 *
	 * @return bool True when the pattern is a terminal negated directory pattern.
	 */
	public static function is_dir_termination_pattern ( string $pattern ): bool
	{
		if ( ( $pattern = trim( $pattern ) ) === '' || str_starts_with( $pattern, '#' ) || !str_starts_with( $pattern, '!' ) ) {
			return FALSE;
		}

		$pattern = substr( $pattern, 1 );

		if ( $pattern === '' || str_ends_with( $pattern, '/*' ) || str_ends_with( $pattern, '/**' ) ) {
			return FALSE;
		}

		return static::is_file_pattern( $pattern ) === FALSE;
	}

	/**
	 * Determine whether a pattern targets a file rather than a directory/no-op.
	 *
	 * File patterns are returned as normalized pattern strings. Otherwise return false.
	 * A dotted final path segment is treated as a file, except single-dot hidden names like
	 * `.git`, which are treated as directories. Prefix `@file:` forces ambiguous extensionless
	 * patterns to be treated as files and is stripped from the returned pattern.
	 *
	 * Negation is preserved on returned file patterns.
	 *
	 * @param string $pattern Include pattern to classify.
	 *
	 * @return string|false File pattern, or false when the pattern is not a file pattern.
	 */
	public static function is_file_pattern ( string $pattern ): string|false
	{
		// Preserve input negation while checking the real pattern body.
		if ( $is_exclude_pattern = str_starts_with( $pattern, '!' ) ) {
			$pattern = substr( $pattern, 1 );
		}

		// `@file:` forces ambiguous extensionless patterns to stay file patterns.
		if ( str_starts_with( $pattern, '@file:' ) ) {
			$pattern = substr( $pattern, 6 );

			return $is_exclude_pattern ? '!' . $pattern : $pattern;
		}

		if ( str_ends_with( $pattern, '/' ) || str_ends_with( $pattern, '/*' ) || str_ends_with( $pattern, '/**' ) ) {
			return FALSE;
		}

		// Ambiguous final segments are directories. Dotted final segment means file,
		// except single-dot hidden names like `.git`, which default to directories.
		$segments     = explode( '/', $pattern );
		$last_segment = end( $segments );

		$is_single_dot_hidden_name = str_starts_with( $last_segment, '.' ) && substr_count( $last_segment, '.' ) === 1;
		if ( !str_contains( $last_segment, '.' ) || $is_single_dot_hidden_name ) {
			return FALSE;
		}

		return $is_exclude_pattern ? '!' . $pattern : $pattern;
	}

	/**
	 * Convert include patterns into IgnoreFile-compatible ignore patterns.
	 *
	 * IgnoreFile works in ignore/unignore terms, while this class exposes include/exclude terms.
	 * This method flips that syntax so included paths become unignored paths internally and
	 * excluded paths become ignored paths internally. Directory patterns are expanded to include
	 * both the directory itself and its descendants when needed.
	 *
	 * When `$includeBase` is true, the result starts by excluding files by default while keeping
	 * directories traversable, allowing later include patterns to match descendants.
	 *
	 * @param string|string[] $patterns    Include patterns to invert. Multiline strings are split on `\n`.
	 * @param bool            $includeBase Whether to prepend default base ignore rules.
	 *
	 * @return string[] IgnoreFile-compatible patterns.
	 */
	public static function invert_patterns ( string|array $patterns, bool $includeBase = TRUE ): array
	{
		if ( is_string( $patterns ) ) {
			$patterns = explode( "\n", $patterns );
		}

		$inverted = [];

		foreach ( $patterns as $pattern ) {
			// Preserve no-op lines before flipping include/exclude semantics.
			if ( ( $pattern = trim( $pattern ) ) === '' || str_starts_with( $pattern, '#' ) ) {
				$inverted[] = $pattern;
				continue;
			}

			// Invert include syntax to ignore syntax.
			$pattern = str_starts_with( $pattern, '!' ) ? substr( $pattern, 1 ) : '!' . $pattern;

			// BUG FIX for automattic/ignorefile/src/IgnoreFile.php:267
			if ( $pattern === '/**' ) {
				$pattern = '**';
			}
			elseif ( $pattern === '!/**' ) {
				$pattern = '!**';
			}

			if ( in_array( $pattern, [ '*', '!*', '**', '!**' ], TRUE ) ) {
				$inverted[] = $pattern;
				continue;
			}

			if ( FALSE !== ( $file_pattern = static::is_file_pattern( $pattern ) ) ) {
				$inverted[] = $file_pattern;
			}
			else {
				$pattern = rtrim( $pattern, '/' );

				if ( str_ends_with( $pattern, '/*' ) || str_ends_with( $pattern, '/**' ) ) {
					$inverted[] = $pattern;
				}
				elseif ( $pattern === '' || $pattern === '!' ) {
					$inverted[] = $pattern . '/**';
				}
				else {
					$inverted[] = $pattern;
					$inverted[] = $pattern . '/**';
				}
			}
		}

		if ( $includeBase ) {
			// Ignore all files by default, but keep dirs walkable so later includes can match.
			array_unshift( $inverted, '*', '!*/' );
		}

		return $inverted;
	}

	/**
	 * Recursively yield files under $base.
	 *
	 * Second arg accepts a string extension, callable (legacy), or a config array:
	 * - filter:      callable — RecursiveCallbackFilterIterator callback (prunes descent)
	 * - filterByExt: string — filters yielded files by extension
	 * - maxDepth:    int — RecursiveIteratorIterator::setMaxDepth (default 50)
	 * - flags:       int — FilesystemIterator/RecursiveDirectoryIterator flags
	 * - mode:        int — RecursiveIteratorIterator mode
	 *
	 * A bare callable is normalized to [ 'filter' => $callable ] for BC.
	 * A bare string is normalized to [ 'filterByExt' => $string ].
	 *
	 * @param string|string[]            $directories Base directory or an array of base directories
	 * @param array|callable|string|null $config      Config array, extension string, or legacy filter callback
	 *
	 * @return \Generator
	 */
	public static function get_files ( string|array $directories, array|callable|string|null $config = NULL ): \Generator
	{
		if ( !is_array( $directories ) ) {
			$directories = (array) $directories;
		}

		if ( is_string( $config ) ) {
			$config = [ 'filterByExt' => $config ];
		}
		elseif ( !is_array( $config ) ) {
			$config = is_callable( $config ) ? [ 'filter' => $config ] : [];
		}

		$config = array_merge( [
			'filter'      => FALSE,
			'filterByExt' => FALSE,
			'maxDepth'    => 50,
			'flags'       => \FilesystemIterator::KEY_AS_PATHNAME | \FilesystemIterator::CURRENT_AS_FILEINFO | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS,
			'mode'        => \RecursiveIteratorIterator::LEAVES_ONLY,
		], $config );

		if ( is_string( $config['filterByExt'] ) ) {
			if ( ( $config['filterByExt'] = ltrim( strtolower( $config['filterByExt'] ), '*.' ) ) === '' ) {
				$config['filterByExt'] = FALSE;
			}
			elseif ( str_contains( $config['filterByExt'], '.' ) ) {
				$config['filterByExt'] = '.' . $config['filterByExt'];
			}
		}
		else {
			$config['filterByExt'] = FALSE;
		}

		foreach ( $directories as $directory ) {
			if ( $config['flags'] & \FilesystemIterator::UNIX_PATHS ) {
				$directory = static::normalize( $directory );
			}

			if ( !is_dir( $directory ) ) {
				throw new \InvalidArgumentException( "Not a directory: {$directory}" );
			}

			$directoryIterator = new \RecursiveDirectoryIterator( $directory, $config['flags'] );
			if ( is_callable( $config['filter'] ) ) {
				$directoryIterator = new \RecursiveCallbackFilterIterator( $directoryIterator, $config['filter'] );
			}
			$iterator = new \RecursiveIteratorIterator( $directoryIterator, $config['mode'] );
			$iterator->setMaxDepth( $config['maxDepth'] );

			if ( is_string( $config['filterByExt'] )
				&& ( ( $config['mode'] & ( \RecursiveIteratorIterator::SELF_FIRST | \RecursiveIteratorIterator::CHILD_FIRST ) ) === \RecursiveIteratorIterator::LEAVES_ONLY ) //
				&& ( ( $config['flags'] & \FilesystemIterator::CURRENT_MODE_MASK ) === \FilesystemIterator::CURRENT_AS_FILEINFO ) ) {
				if ( $config['filterByExt'][0] === '.' ) {
					foreach ( $iterator as $path => $file ) {
						if ( substr_compare( $file->getFilename(), $config['filterByExt'], -strlen( $config['filterByExt'] ), NULL, TRUE ) === 0 ) {
							yield $path => $file;
						}
					}
				}
				else {
					foreach ( $iterator as $path => $file ) {
						if ( strcasecmp( $file->getExtension(), $config['filterByExt'] ) === 0 ) {
							yield $path => $file;
						}
					}
				}
			}
			else {
				yield from $iterator;
			}
		}
	}

	/**
	 * Normalizes a filesystem path.
	 *
	 * On windows systems, replaces backslashes with forward slashes
	 * and forces upper-case drive letters.
	 * Allows for two leading slashes for Windows network shares, but
	 * ensures that all other duplicate slashes are reduced to a single.
	 *
	 * @param string $path Path to normalize.
	 *
	 * @return string Normalized path.
	 */
	public static function normalize ( $path )
	{
		// Standardize all paths to use '/'.
		$path = str_replace( '\\', '/', $path );

		// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
		$path = preg_replace( '|(?<=.)/+|', '/', $path );

		// Windows paths should uppercase the drive letter.
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		return $path;
	}

	/**
	 * Tests if a given filesystem path is absolute.
	 *
	 * For example, '/foo/bar', or 'c:\windows'.
	 *
	 * @param string $path File path.
	 *
	 * @return bool True if path is absolute, false is not absolute.
	 */
	public static function is_absolute_path ( string $path, bool $checkRealpath = FALSE ): bool
	{
		// This is definitive if true but fails if $path does not exist or contains a symbolic link.
		if ( $checkRealpath && realpath( $path ) === $path ) {
			return TRUE;
		}

		if ( strlen( $path ) === 0 || '.' === $path[0] ) {
			return FALSE;
		}

		// Windows allows absolute paths like this.
		if ( preg_match( '#^[a-zA-Z]:[/\\\\]#', $path ) ) {
			return TRUE;
		}

		// A path starting with / or \ is absolute; anything else is relative.
		return ( '/' === $path[0] || '\\' === $path[0] );
	}

	/**
	 * Adds base path to a non-absolute path
	 *
	 * @param string $value Value from which base path will be prepended.
	 * @param string $base  Base path to prepend.
	 *
	 * @return string String with the base path.
	 */
	public static function add_base ( string $path, string $base ): string
	{
		if ( self::is_absolute_path( $path ) ) {
			return $path;
		}

		return rtrim( $base, '/' ) . '/' . $path;
	}

	/**
	 * Removes base path if it exists
	 *
	 * @param string $value Value from which base path will be removed.
	 * @param string $base  Base path to remove.
	 *
	 * @return string String without the base path.
	 */
	public static function strip_base ( string $path, string $base )
	{
		if ( str_starts_with( $path, $base ) ) {
			return substr( $path, strlen( $base ) );
		}

		return $path;
	}

	/**
	 * Appends a trailing slash.
	 *
	 * Will remove trailing forward and backslashes if it exists already before adding
	 * a trailing forward slash. This prevents double slashing a string or path.
	 *
	 * The primary use of this is for paths and thus should be used for paths. It is
	 * not restricted to paths and offers no specific path support.
	 *
	 * @param string $value Value to which trailing slash will be added.
	 *
	 * @return string String with trailing slash added.
	 */
	public static function add_trailing_slash ( $value )
	{
		return rtrim( $value, '/\\' ) . '/';
	}

	/**
	 * Removes trailing forward slashes and backslashes if they exist.
	 *
	 * The primary use of this is for paths and thus should be used for paths. It is
	 * not restricted to paths and offers no specific path support.
	 *
	 * @param string $value Value from which trailing slashes will be removed.
	 *
	 * @return string String without the trailing slashes.
	 */
	public static function strip_trailing_slash ( $value )
	{
		return rtrim( $value, '/\\' );
	}

	/**
	 * Appends a preceding slash.
	 *
	 * Will remove preceding forward and backslashes if it exists already before adding
	 * a preceding forward slash. This prevents double slashing a string or path.
	 *
	 * @param string $value Value to which preceding slash will be added.
	 *
	 * @return string String with preceding slash added.
	 */
	public static function add_preceding_slash ( $value )
	{
		return '/' . ltrim( $value, '/\\' );
	}

	/**
	 * Removes preceding forward slashes and backslashes if they exist.
	 *
	 * @param string $value Value from which preceding slashes will be removed.
	 *
	 * @return string String without the preceding slashes.
	 */
	public static function strip_preceding_slash ( $value )
	{
		return ltrim( $value, '/\\' );
	}
}
