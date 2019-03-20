<?php
/**
 *
 * Filesystem chroot and misc. file management functions
 *
 * @copyright 2018 Aleksandar Z. Bosakov
 * @license MIT
 */

namespace AZBosakov\Filesystem;

class Chroot
{
    /** @var string $defaultRoot The default root for all instances */
    private static $defaultRoot = '/';
    
    /** @var string $root The root dir for this instance */
    private $root = '/';
    /** @var string $cwd The current working dir for this instance, used for resolving rel. paths */
    private $cwd = '/';
    
    /** The default umask for mkdir */
    private $umask = 022;
    
    public function __construct(string $rootDir = '/')
    {
        $r = rtrim($rootDir, '/');
        $this->root = self::normalizePath(static::$defaultRoot . "/$r", '/');
        if (! is_dir($this->root)) {
            throw new \DomainException("The path '{$this->root}' is not a directory");
        }
    }
    
    /**
     * Sets the default root directory for all subsequent instances.
     * By default, it's '/' and can be changed to another dir ONLY ONCE.
     * Subsequent changes are forbidden
     * 
     * @param string $rootDir
     * @return bool The success or failure of setting the default root
     */
    public static function setDefaultRoot(string $rootDir): bool
    {
        if (static::$defaultRoot !== '/') {
            trigger_error("The default root already set", E_USER_WARNING);
            return false;
        }
        $root = self::normalizePath($rootDir, getcwd());
        if (! is_dir($root)) return false;
        static::$defaultRoot = $root;
        return true;
    }
    
    /**
     * Get the default root dir
     * 
     * @return string The default root
     */
    public static function getDefaultRoot(): string
    {
        return static::$defaultRoot;
    }
    
    /**
     * @return string The root dir of this instance
     */
    public function getRootDir(): string
    {
        return $this->root;
    }
    
    /**
     * @return int the umask for mkdir mode
     */
    public function getUmask(): int
    {
        return $this->umask;
    }
    
    /**
     * Set the mkdir mode umask
     * 
     * @param int $umask
     * @return self
     */
    public function setUmask(int $umask): self
    {
        $this->umask = $umask;
        return $this;
    }
    
    /**
     * Resolve the '//', '.' and '..'
     * 
     * @param string $path
     * @param string $relTo The path, used for resolving relative ones.
     * @return ?string Normalized path or null if the path can't be normalized
     */
    public static function normalizePath(string $path, string $relTo = '/'): ?string
    {
        if (strpos($path, '/') !== 0) {
            $path = "$relTo/$path";
        }
        $leadSlash = strpos($path, '/') === 0 ? '/' : '';
        $path = trim($path, '/');
        $srcSegments = explode('/', $path);
        $dstSegments = [];
        foreach ($srcSegments as $segment) {
            switch ($segment) {
                case '':
                case '.':
                    continue 2;
                case '..':
                    if (! $dstSegments) {
                        trigger_error("Trying to climb out of the root dir!", E_USER_WARNING);
                        return null;
                    }
                    array_pop($dstSegments);
                    continue 2;
                default:
                    $dstSegments[] = $segment;
            }
        }
        return $leadSlash . implode('/', $dstSegments);
    }
    
    /**
     * Resolve paths relative to the root dir
     * Path doesn't need to exist
     * 
     * @param string $sitePath
     * @return ?string Normalized path or null if the path can't be normalized
     */
    public function realpath(string $sitePath): ?string
    {
        return self::normalizePath($sitePath, $this->cwd);
    }
    
    /** Calls syspath */
    public function __invoke(string $sitePath): ?string
    {
        return $this->syspath($sitePath);
    }
    
    /**
     * Map paths relative to the instance to the underlying filesystem
     * 
     * @param string $sitePath
     * @return ?string The filesystem path or null if the site path can't be normalized
     */
    public function syspath(string $sitePath): ?string
    {
        $sp = $this->realpath($sitePath);
        if (! isset($sp)) return null;
        return rtrim("{$this->root}{$sp}", '/');
    }
    
    /**
     * @return string The system path corresponding to the instance CWD
     */
    public function __toString()
    {
        return rtrim("{$this->root}{$this->cwd}", '/');
    }
    
    /**
     * Map a filesystem path to one inside the instance root - oposite of syspath()
     * 
     * @param string $sysPath The filesystem path
     * @return ?string The path relative to the root or null, if the sys path is not inside the root
     */
    public function sitepath(string $sysPath): ?string
    {
        $fsPath = self::normalizePath($sysPath, getcwd());
        if (! isset($fsPath)) {
            return null;
        }
        if (strpos($fsPath, $this->root) !== 0) {
            return null;
        }
        if ($fsPath == $this->root) {
            return '/';
        }
        $diff = substr($fsPath, strlen($this->root));
        if ($diff === '') return '/';
        if ($diff === false or strpos($diff, '/') !== 0) return null;
        return $diff;
    }
    
    /**
     * Check if the path relative to the root is a file
     * 
     * @param string $sitePath
     * @return bool
     */
    public function isFile(string $sitePath): bool
    {
        $fsPath = $this->syspath($sitePath);
        return is_file($fsPath);
    }
    
    /**
     * Check if the path relative to the root is a directory
     * 
     * @param string $sitePath
     * @return bool
     */
    public function isDir(string $sitePath): bool
    {
        $fsPath = $this->syspath($sitePath);
        return is_dir($fsPath);
    }
    
    /**
     * Change the current working directory relative to the instance root.
     * 
     * @param string $sitePath
     * @return bool
     */
    public function cd(string $sitePath): bool
    {
        $rp = $this->realpath($sitePath);
        if (! $this->isDir($rp)) {
            trigger_error("Can't cd to '$rp' - not a directory");
            return false;
        }
        $this->cwd = $rp;
        return true;
    }
    
    /**
     * Get the current working directory relative to the instance root.
     * 
     * @return string The CWD
     */
    public function pwd(): string
    {
        return $this->cwd;
    }
    
    /**
     * List the files matching a glob pattern
     * 
     * @param string $glob The glob pattern
     * @return array The list of matched files/dirs
     */
    public function ls(string $glob = '*'): array
    {
        if (substr($glob, -1) == '/') {
            $glob .= '*';
        }
        $dir = $this->syspath(dirname($glob));
        return array_map([$this, 'sitepath'], glob($this->syspath($glob)));
    }
    
    
    /**
     * Recursively list the files matching a glob pattern
     * 
     * @param string $glob The glob pattern
     * @param string $dir  Relative to directory
     * @return array The list of matched files/dirs
     */
    public function find(string $glob = '*', string $dir = '.'): array
    {
        $list = self::rGlob($glob, $this->syspath($dir));
        return array_map([$this, 'sitepath'], $list);
    }
    
    /**
     * Copy a file/dir. If source is a directory - copy it recursively
     * 
     * @param string $siteSrc The source file/dir
     * @param string $siteDst The destination path
     * @param bool $overwrite Overwrite existing destination
     * @return bool The success of the operation
     */
    public function cp(string $siteSrc, string $siteDst, bool $overwrite = false): bool
    {
        $fsSrc = $this->syspath($siteSrc);
        $fsDst = $this->syspath($siteDst);
        if (substr($siteDst, -1) == '/') {
            $fsDst .= '/' . basename($fsSrc);
        }
        if (file_exists($fsDst) and (! $overwrite)) {
            trigger_error("Destination path: '$fsDst' already exists, use (src, dst, true) to force overwrite!");
            return false;
        }
        return self::rCopy($fsSrc, $fsDst, $overwrite);
    }
    
    /**
     * Move a file/dir
     * 
     * @param string $siteSrc The source file/dir
     * @param string $siteDst The destination path
     * @param bool $overwrite Overwrite existing destination
     * @return bool The success of the operation
     */
    public function mv(string $siteSrc, string $siteDst, bool $overwrite = false): bool
    {
        $fsSrc = $this->syspath($siteSrc);
        $fsDst = $this->syspath($siteDst);
        if (substr($siteDst, -1) == '/') {
            $fsDst .= '/' . basename($fsSrc);
        }
        if (file_exists($fsDst) and (! $overwrite)) {
            trigger_error("Destination path: '$fsDst' already exists, use (src, dst, true) to force overwrite!");
            return false;
        }
        return rename($fsSrc, $fsDst);
    }
    
    /**
     * Delete a file/dir. If a directory and $rf = true, delete it recursively
     * 
     * @param string $sitePath The source file/dir
     * @param bool $rf Delete recursive (rm -rf)
     * @return bool The success of the operation
     */
    public function rm(string $sitePath, bool $rf = false): bool
    {
        $fsPath = $this->syspath($sitePath);
        if ($rf) {
            return self::rRemove($fsPath);
        }
        return unlink($fsPath);
    }
    
    /**
     * Create a directory. If $mkpath = true, can create subdirs too
     * 
     * @param string $sitePath The directory path, relative to the root
     * @param bool $mkpath Create subdirectories, like a/b/c
     * @return bool The success of the operation
     */
    public function mkdir(string $sitePath, bool $mkpath = false): bool
    {
        $fsPath = $this->syspath($sitePath);
        $perm = 0777 & ~$this->umask;
        return @mkdir($fsPath, $perm, $mkpath);
    }
    
    /**
     * Remove a directory. If $recursive = true, remove it even when non-empty.
     * 
     * @param string $sitePath The directory path, relative to the root
     * @param bool $recursive Remove non-empty dirs recursively
     * @return bool The success of the operation
     */
    public function rmdir(string $sitePath, bool $recursive = false): bool
    {
        $fsPath = $this->syspath($sitePath);
        if (! is_dir($fsPath)) {
            trigger_error("Not a directory : '$fsPath'");
            return false;
        }
        if ($recursive) {
            return self::rRemove($fsPath);
        }
        return rmdir($fsPath);
    }
    
    // rFuncs bellow are NOT relative to CWD !
    // They work directly on the host filesystem
    
    
    /**
     * Copy a file/dir. If source is a directory - copy it recursively
     * 
     * @param string $fsSrc The source file/dir
     * @param string $fsDst The destination path
     * @param bool $overwrite Overwrite existing destination
     * @return bool The success of the operation
     */
    public static function rCopy(string $fsSrc, string $fsDst, bool $overwrite = false): bool
    {
        $fsSrc = realpath($fsSrc);
        if (substr($fsDst, -1) == '/') {
            $fsDst .= basename($fsSrc);
        }
        if ((! $overwrite) and file_exists($fsDst)) {
            trigger_error("Destination path: '$fsDst' already exists, use (src, dst, true) to force overwrite!");
            return false;
        }
        if (is_dir($fsSrc)) {
            if (! mkdir($fsDst)) {
                trigger_error("Can't mkdir '$fsDst'");
                return false;
            }
            if ($dirHandle = opendir($fsSrc)) {
                $result = true;
                while (($dirEntry = readdir($dirHandle)) !== false) {
                    if ($dirEntry == '.' or $dirEntry == '..') {
                        continue;
                    }
                    $result = self::rCopy("$fsSrc/$dirEntry", "$fsDst/$dirEntry", $overwrite) && $result;
                }
                closedir($dirHandle);
                return $result;
            }
            return false;
        } else {
            return copy($fsSrc, $fsDst);
        }
        return false;
    }
    
    
    /**
     * Delete a file/dir. If a directory - delete it recursively
     * 
     * @param string $fsPath The source file/dir
     * @return bool The success of the operation
     */
    public static function rRemove(string $fsPath): bool
    {
        if (is_dir($fsPath)) {
            if ($dirHandle = opendir($fsPath)) {
                $result = true;
                while (($dirEntry = readdir($dirHandle)) !== false) {
                    if ($dirEntry == '.' or $dirEntry == '..') {
                        continue;
                    }
                    $result = self::rRemove("$fsPath/$dirEntry") && $result;
                }
                closedir($dirHandle);
                return rmdir($fsPath);
            }
            return false;
        } else {
            return unlink($fsPath);
        }
        return false;
    }
    
    /**
     * Recursively list the files matching a glob pattern
     * 
     * @param string $glob The glob pattern
     * @param string $dir  Relative to directory
     * @return array The list of matched files/dirs
     */
    public static function rGlob(string $pattern = '*', string $dir = '.'): array
    {
        $entries = glob("$dir/$pattern");
        foreach (glob("$dir/*", GLOB_ONLYDIR) as $subDir) {
            $entryCount = count($entries);
            $levelDown = "$dir/" . basename($subDir);
            $newEntries = self::rGlob($pattern, $levelDown);
            array_splice($entries, $entryCount, 0, $newEntries);
        }
        return $entries;
    }
}
