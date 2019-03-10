# Class description

A filesystem chroot. Initialized with an existing directory path as a root dir. Most of the methods operate on paths, relative to the root dir, passed to the constructor.

The class contains functions for path normalization, copy, move, delete, list, etc. The method names and behavior are inspired by the corresponding UNIX commands - `cp`, `mv`, `rm`, `ls`, `rmdir`, `find`.

# Method reference

### public function __construct(string $rootDir = '/')
The `$rootDir` parameter must be an existing directory, relative to the default root directory for the class.

For example:

* class default: "`/a/b/c/d`", `$rootDir`: "`/x/y`" or "`x/y`", the root dir for the object will be "`/a/b/c/d/x/y`".

* class default: "`/a/b/c/d`", `$rootDir`: "`../../x/y`", the root dir for the object will be "`/a/b/x/y`".

### public static function setDefaultRoot(string $rootDir): bool
Sets the default root directory for the entire class. Initially it is "/" - the filesystem root. Can be changed **only once** - preferably before the first use of the class. Returns **true** on successful change (the first invocation with existing, non-'/' path), **false** otherwise.

Intended use - set the default at the document root dir, then instantiate with something like

```$imgRoot = new ...\Chroot('/images')```

or

```$tplRoot = new ...\Chroot('/templates')```,

etc.

### public static function getDefaultRoot(): string
Returns the default root directory for the class.

### public function getRootDir(): string
Returns the root directory for the particular instance.

### public function getUmask(): int
Returns the **umask**, used for `mkdir()` mode parameter. The mode is calculated as `0777 & ~umask`.

### public function setUmask(int $umask): self
Sets the umask. Returns the instance.

### public static function normalizePath(string $path, string $relTo = '/'): ?string
Normalizes the `$path` to absolute path. If `$path` does not start with '/', it's taken as a relative to the `$relTo` path.

The path does not need to be existing.

If the path can't be normalized (too many '..'), returns **null**.

### public function realpath(string $sitePath): ?string
Normalizes the `$sitePath` to absolute path, starting from the instance root. If `$sitePath` does not start with '/', it's taken as a relative to the instance's Current Working Directory (CWD).

If the path can't be normalized (too many '..'), returns **null**.

### public function __invoke(string $sitePath): ?string
A shorthand for `->syspath()`, to save typing.

### public function syspath(string $sitePath): ?string
Maps a path, relative to the instance, to a filesystem path. Eg.:

instance root: "`/a/b/c`", instance path: "`/x/y`" -> "`/a/b/c/x/y`"

If the `$sitePath` can't be normalized as local one, returns **null**.

### public function __toString()
Returns the instance root concatenated with the instance CWD, so the object can be used in a string interpolation, like:

```
...::setDefaultRoot('/srv/doc/root');
...
$imgRoot = new ...\Chroot('/images');   // root = '/srv/doc/root/images'
$imgRoot->cd('big');                    // CWD = '/big'; (string)$imgRoot == '/srv/doc/root/images/big'
$imgFile = 'xxx.jpg';
doSomething("$imgRoot/$imgFile");       // "$imgRoot/$imgFile" == '/srv/doc/root/images/big/xxx.jpg'
```

### public function sitepath(string $sysPath): ?string
The opposite of `->syspath(...)`. Maps a filesystem path to a local one, as long as the system one is inside the instance root. Returns **null** otherwise.

Eg.: root: "`/a/b/c`", `$sysPath`: "`/a/b/c/d/e`" -> "`/d/e`"

### public function isFile(string $sitePath): bool
Checks if `$sitePath` is a file.

### public function isDir(string $sitePath): bool
Checks if `$sitePath` is a directory.

### public function cd(string $sitePath): bool
Changes the instance's CWD. Returns **true** on success, **false** if the `$sitePath` can't be normalized.

### public function pwd(): string
Returns the instance's CWD.

### public function ls(string $glob = '*'): array
Returns a list of the paths, matching the `$glob`, relative to CWD.

### public function find(string $glob = '*', string $dir = '.'): array
A recursive `->ls(...)`, relative to the CWD or `$dir`.

### public function cp(string $siteSrc, string $siteDst, bool $overwrite = false): bool
Copy a file/directory. If `$siteDst` ends with '/', it is taken as the `dirname(...)` of the destination, and the `basename(...)` is the `basename(...)` of the source. Eg.:

* SRC: "`/dir111/file111`", DST: "`/dir222/file222`" -> "`/dir111/file111`" is copied as "`/dir222/file222`"

* SRC: "`/dir111/file111`", DST: "`/dir222/`" -> "`/dir111/file111`" is copied as "`/dir222/file111`"

If the destination exists, the copy fails, unless `$overwrite` is true.

### public function mv(string $siteSrc, string $siteDst, bool $overwrite = false): bool
Move a file/directory. If `$siteDst` ends with '/', it is taken as the `dirname(...)` of the destination, and the `basename(...)` is the `basename(...)` of the source. Eg.:

* SRC: "`/dir111/file111`", DST: "`/dir222/file222`" -> "`/dir111/file111`" is moved as "`/dir222/file222`"

* SRC: "`/dir111/file111`", DST: "`/dir222/`" -> "`/dir111/file111`" is moved as "`/dir222/file111`"

If the destination exists, the move fails, unless `$overwrite` is true.

### public function rm(string $sitePath, bool $rf = false): bool
Delete a path. If `$rf` is **true**, delete directories recursively, like the UNIX's `rm -rf ...`. Returns **true** on success, **false** otherwise.

### public function mkdir(string $sitePath, bool $mkpath = false): bool
Creates a subdirectory. If `$mkpath` is true, can create multiple levels, like the UNIX's `mkdir -p ...`.

### public function rmdir(string $sitePath, bool $recursive = false): bool
Removes a directory. Fails if directory is non-empty, unless `$recursive` is **true**, for recursive removal. With `$recursive`: **true**, acts like `->rm(...)` with `$rf`: **true**.

### public static function rCopy(string $fsSrc, string $fsDst, bool $overwrite = false): bool
Recursive copy. The arguments **filesystem** paths, **not** local ones.

### public static function rRemove(string $fsPath): bool
Recursive delete. The arguments **filesystem** paths, **not** local ones.

### public static function rGlob(string $pattern = '*', string $dir = '.'): array
Recursive `glob()`. The arguments **filesystem** paths, **not** local ones.



