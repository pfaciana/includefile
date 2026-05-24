# includefile

`includefile` is an include-pattern matcher designed for fast filesystem discovery.

It wraps [`automattic/ignorefile`](https://github.com/Automattic/ignorefile), but exposes include-first behavior: describe the files you want, exclude the directories or files you do not want, and stream the matching entries into whatever processing step comes next.

## Installation

```bash
composer require pfaciana/includefile
```

## Pattern Model

Patterns are include-first.

```text
src         include the src directory and descendants
**/*.php    include PHP files anywhere
!vendor     exclude the vendor directory and descendants
```

The pattern syntax comes from Git-style ignore files, through `automattic/ignorefile`. Use Git ignore-pattern behavior as the baseline model; this package does NOT define a new glob language.

Internally, `includefile` converts include patterns into ignore-style rules for `automattic/ignorefile`. That matters because `!` means "exclude from the include set" in this package, even though `!` means "unignore" in a `.gitignore` file.

`automattic/ignorefile` compiles patterns to regex. It does not check the filesystem to find out whether an ambiguous path is a file or a directory. A pattern like `.examples` could refer to either one, so `includefile` has to classify ambiguous patterns before it can build traversal filters.

### Ambiguous Files vs Directories

`includefile` classifies patterns as file patterns or directory patterns. That classification is a best guess based on the pattern text, not a filesystem check. It is used when building traversal filters.

Default rules:

- A final path segment with no dots is treated as a directory: `src`, `vendor`, `bin/console`, `v1.2/config`.
- A final path segment with a dot is treated as a file: `index.php`, `src/config.json`.
- EXCEPT: Single-dot hidden names are treated as directories: `.git`, `.agents`, `.claude`, `.env`, `.gitignore`.

So in short, `no dot = directory`, `dot = file`, unless hidden `hidden dot = directory`.

Force rules:

If you dont want to leave anything to change, you force how a pattern is treated.

- A trailing `/`, `/*`, or `/**` makes the pattern a directory pattern.
- `@file:` forces a pattern to be treated as a file: `@file:README`, `@file:bin/console`, `@file:.env`, `@file:.gitignore`.
  - Negation works with `@file:` by putting `!` first: `!@file:README`, `!@file:.env`, `!@file:.gitignore` you're saying `DO NOT INCLUDE` (or `EXCLUDE`) this file.

Hidden names are biased toward directories because directory pruning is usually more important for names like `.git`, `.agents`, and `.claude`. Files like `.gitattributes`, `.gitignore`, and `.env` do exist; use `@file:` when the hidden pattern is meant to be a file.

So in short, `end in slash = directory`, `starts with @file: (or !@file:) = file`.

### Directory Pruning

Some excluded directory patterns are terminal during traversal.

```text
!vendor
!.git
!node_modules
```

These exclude the directory and descendants, so discovery can skip descending into them.

Open-ended exclusions are different:

```text
!vendor/*
!vendor/**
```

Those exclude contents but are not terminal, because a later include pattern may still make a descendant reachable.

```text
src
!src/vendor/*
src/vendor/acme
```

Use terminal exclusions when nothing under that directory should be discovered. Use open-ended exclusions when later include patterns may re-include descendants.

This follows the same reachability model as Git ignore files, with the include/exclude meaning inverted. If traversal excludes a directory itself, later patterns for descendants under that directory cannot be reached during filesystem discovery.

## File Discovery

A common use is to stream matching files from a base directory.

```php
use Render\IncludeFile;

$include = new IncludeFile( [
	'**/*.php',
	'!.git',
	'!node_modules',
	'!tests',
	'!vendor',
] );

$baseDir = IncludeFile::add_trailing_slash( IncludeFile::normalize( __DIR__ ) );

$files = IncludeFile::get_files( $baseDir, [
	'filter' => $include->getFilter( $baseDir ),
] );

foreach ( $files as $file ) {
	// Process each matching SplFileInfo entry.
}
```

`get_files()` returns a generator. The default callback filter uses the include patterns for files and prunes terminal excluded directories before descending into them.

### `get_files()`

`IncludeFile::get_files()` is the common helper for filesystem discovery. It creates the recursive iterators, applies an optional traversal filter, and yields matching entries.

```php
IncludeFile::get_files( string|array $directories, array|callable|string|null $config = NULL ): \Generator
```

The first argument is a directory path or a list of directory paths.

The second argument may be:

- `NULL` to walk without a filter.
- A callable filter, passed directly to `RecursiveCallbackFilterIterator`.
- A string extension or dotted filename suffix, normalized to `filterByExt`.
- A list of string extensions or dotted filename suffixes, normalized to `filterByExt`.
- A config array.

Config keys:

- `filter`: callable filter or `FALSE`.
- `filterByExt`: string or list of extensions/dotted filename suffixes to filter yielded files.
- `maxDepth`: maximum recursive depth, default `50`.
- `flags`: `RecursiveDirectoryIterator` flags.
- `mode`: `RecursiveIteratorIterator` mode, default `RecursiveIteratorIterator::LEAVES_ONLY`.

Extension filters are case-insensitive. Values like `'php'`, `'.php'`, and `'*.php'` are equivalent. Dotted suffixes like `'inc.php'` match the full filename suffix. An empty string matches extensionless files, and an empty list means no extension filter. Unsupported `filterByExt` values are treated as no extension filter.

Use it with `getFilter()` for normal include-pattern discovery. Use `filterByExt` beside `filter` when you want include-pattern filtering and extension filtering together. The built-in `filterByExt` check is for the default leaf/fileinfo traversal; use a callable filter when custom iterator modes or current modes need extension filtering.

```php
$phpFiles = IncludeFile::get_files( $baseDir, 'php' );
$assetFiles = IncludeFile::get_files( $baseDir, [ 'php', 'json', 'md' ] );

$includedPhpFiles = IncludeFile::get_files( $baseDir, [
	'filter'      => $include->getFilter( $baseDir ),
	'filterByExt' => 'php',
] );
```

## Custom Discovery Filters

Use `makeFilter()` to extend the default callback filter without replacing its include-pattern behavior. Pass `'allowLinks' => TRUE` to `getFilter()` or `makeFilter()` to let the traversal filter treat linked directories as children.

The optional callbacks receive:

```php
'dir'  => function ( $iterator, $fileInfo, string $absPath, string $relPath, string $baseDir, IncludeFile|false $includeDirs, IncludeFile $include ): ?bool
'file' => function ( $fileInfo, $iterator, string $absPath, string $relPath, string $baseDir, IncludeFile $include, IncludeFile|false $includeDirs ): ?bool
```

Return `TRUE` or `FALSE` to short-circuit the default filter. Return `NULL` to continue to the default behavior.

Callbacks may omit trailing parameters, but declared parameters must keep the documented order.

```php
$filter = $include->makeFilter( $baseDir, [
	'file' => function ( $fileInfo, $iterator, string $absPath, string $relPath ): ?bool {
		if ( str_contains( $relPath, '/generated/' ) ) {
			return FALSE;
		}

		return NULL;
	},
] );
```

For extension filtering, use `get_files()` with `filterByExt`. For default traversal behavior, `getFilter()` is shorthand for `makeFilter( $baseDir )`.

`getDefaultCallbackFilter()` is still available as a deprecated compatibility alias.

`filterByExt` is useful when the caller only wants specific file types. It runs after the traversal filter and before yielding files, so `filter` can prune directories while `filterByExt` keeps extension checks out of custom callback code.

See `examples/file-discovery-basic.php` for a direct file discovery example.

See `examples/file-discovery-advanced.php` for the full wrapper-style example. It normalizes a base directory, builds a reusable discovery function, adapts the native `RecursiveCallbackFilterIterator` callback, and passes extra context into the caller's custom filter.

See `examples/get-files.php` for using `get_files()` without include patterns.

## Direct Pattern Matching

If you dont need to discover files, meaning you already know the exact files you want to test, then you can use the `includes()` and `filter()` methods.

Use `includes()` when you already have a path and only need to test it.

```php
$include = new IncludeFile( [
	'@file:README',
	'src',
	'!src/private',
] );

if ( $include->includes( 'src/IncludeFile.php' ) ) {
	// Included.
}
```

Use `filter()` when you already have an array of paths.

```php
$filesToProcess = $include->filter( $allFiles );
```

Use `test()` when you need the include state and the matching pattern.

```php
$result = $include->test( 'src/private/Secret.php' );

// [
//     'included' => false,
//     'notIncluded' => true,
//     'pattern' => '!src/private',
// ]
```

## Gotchas

When testing a raw directory path string with `includes()`, include the trailing `/`. Without it, `automattic/ignorefile` treats the path like a file path.

```php
$include->includes( 'src/' ); // directory path string
$include->includes( 'src' );  // file-like path string
```

This is separate from pattern classification. As a pattern, `src` is treated as a directory pattern and includes `src` and descendants.

Strict mode is enabled by default. Invalid patterns throw `Automattic\IgnoreFile\InvalidPatternException`.

```php
$include->setStrictMode( false );
```

Pattern parsing comes from `automattic/ignorefile`; known incompatibilities with Git are inherited from that package.
