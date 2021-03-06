<?php
namespace Poirot\Filesystem\Adapter\Local;

use Poirot\Filesystem\Adapter\Directory;
use Poirot\Filesystem\Adapter\File;
use Poirot\Filesystem\Adapter\Link;
use Poirot\Filesystem\Exception\FileNotFoundException;
use Poirot\Filesystem\Interfaces\Filesystem\File\iFileContentDelivery;
use Poirot\Filesystem\Interfaces\Filesystem\iCommon;
use Poirot\Filesystem\Interfaces\Filesystem\iCommonInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectoryInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iFile;
use Poirot\Filesystem\Interfaces\Filesystem\iFileInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iLinkInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iFilePermissions;
use Poirot\Filesystem\FilePermissions;
use Poirot\Filesystem\Interfaces\iFsLocal;
use Poirot\PathUri\FilePathUri;
use Poirot\PathUri\Interfaces\iFilePathUri;
use Poirot\PathUri\SeqPathJoinUri;
use Poirot\Stream\Interfaces\Context\iSContext;
use Poirot\Stream\Interfaces\iSResource;
use Poirot\Stream\Interfaces\Resource\iSRAccessMode;
use Poirot\Stream\Resource\SROpenMode;
use Poirot\Stream\WrapperClient;

/**
 * ! Note: In PHP Most Of Filesystem actions need
 *         file/directory permission be as same as
 *         apache/php user
 */
class LocalFS implements iFsLocal
{
    /**
     * Gets the current working directory
     *
     * - current working directory must exist
     *   from within root directory
     *
     * - if not chDir to root dir
     *
     * @throws \Exception On iFailure
     * @return iDirectory
     */
    function getCwd()
    {
        $cwd = getcwd();
        if ($cwd === false)
            throw new \Exception(
                'Failed To Get Current Working Directory.'
                , null
                , new \Exception(error_get_last()['message'])
            );

        $return = new Directory($cwd);
        $return->setFilesystem($this);

        return $return;
    }

    /**
     * Changes Filesystem current directory
     *
     * @param iDirectoryInfo $dir
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chDir(iDirectoryInfo $dir)
    {
        $dirRealpath = $this->pathUri()
            ->fromPathUri($dir->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($dirRealpath);

        if (@chdir($dirRealpath) === false)
            throw new \Exception(sprintf(
                'Failed Changing Directory To "%s".'
                , $dirRealpath
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Make an Object From Existence Path Filesystem
     *
     * @param string $path Filesystem Path To File or Directory
     *
     * @throws \Exception On Failure
     * @return iCommonInfo
     */
    function mkFromPath($path)
    {
        // create filesystem node object
        $return = false;
        if ($this->isDir($path))
            $return = new Directory;
        elseif ($this->isFile($path))
            $return = new File;
        elseif ($this->isLink($path))
            $return = new Link;

        if (!$return)
            throw new \Exception(sprintf(
                'Cant Make From Path "%s", not recognized.'
                , $path
            ), null, new \Exception(error_get_last()['message']));

        $return
            ->setFilesystem($this)
            ->pathUri()
                ->setSeparator( $this->pathUri()->getSeparator() )
                ->fromArray(
                    $this->pathUri()->parse($path)
                )
        ;

        if ($this->isLink($return))
            $return->setTarget( $this->linkRead($return) );

        return $return;
    }

    /**
     * Make Stream Resource From File Location
     *
     * - create stream resource from file location
     *
     * @param iFileInfo                     $file
     * @param iSRAccessMode|string          $openMode Open mode
     * @param iSContext|array|resource|null $context  Context Options
     *
     * @return iSResource
     */
    function mkStreamFrom(iFileInfo $file, $openMode = iSRAccessMode::MODE_RB, $context = null)
    {
        $file = clone $file;

        $pathUri = $file->pathUri();

        if (!$pathUri->isAbsolute())
            // !! We need absolute path for file:// stream wrappers
            // Append current working directory
            $pathUri->prepend(new SeqPathJoinUri([
                'path'      => $this->getCwd()->pathUri()->toString(),
                'separator' => $this->pathUri()->getSeparator()
            ]));

        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        if (is_string($openMode))
            $openMode = new SROpenMode($openMode);

        if (! $openMode instanceof iSRAccessMode)
            throw new \InvalidArgumentException(sprintf(
                'OpenMode must instance of iSRAccessMode or string, "%s" given.'
                , is_object($openMode) ? get_class($openMode) : gettype($openMode)
            ));

        $openMode->asBinary();

        $wc = new WrapperClient('file://'.$filename, $openMode);

        return $wc->getConnect();
    }

    /**
     * Get Path Uri Object
     *
     * - it used to build/parse uri address to file
     *   by filesystem
     *
     * - every time return clean/reset or new instance of
     *   pathUri
     *
     * @throws \Exception
     * @return iFilePathUri
     */
    function pathUri()
    {
        $FilePathUri = new FilePathUri;
        $FilePathUri->setSeparator(DIRECTORY_SEPARATOR);

        return $FilePathUri;
    }

    // Directory Implementation:

    /**
     * List an array of files/directories path from the directory
     *
     * - get rid of ".", ".." from list
     *
     * @param iDirectoryInfo|null $dir          If Null Scan Current Working Directory
     * @param int                 $sortingOrder SCANDIR_SORT_NONE|SCANDIR_SORT_ASCENDING
     *                                         |SCANDIR_SORT_DESCENDING
     *
     * @throws \Exception On Failure
     * @return array
     */
    function scanDir(iDirectoryInfo $dir = null, $sortingOrder = self::SCANDIR_SORT_NONE)
    {
        if ($dir === null)
            $dir = $this->getCwd();

        $dirRealPath = $this->pathUri()
            ->fromPathUri($dir->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($dirRealPath);

        $result  = @scandir($dirRealPath, $sortingOrder);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed Scan Directory To "%s".'
                , $dirRealPath
            ), null, new \Exception(error_get_last()['message']));

        // get rid of the dots
        $result = array_diff($result, array('..', '.'));

        // (cwd = "/modules/innClinic") joint (dir = "/config") ===> "/"
        // (cwd = "/modules/innClinic") mask  (dir = "/config") ===> "config"
        // ===> /config  :D

        /*$joint =
            (new SeqPathJoinUri($dir->pathUri()->toString()))
            ->joint(new SeqPathJoinUri($this->getCwd()->pathUri()->toString()), false)
            ->toString();
        ;

        if ($joint == $this->getCwd()->pathUri()->toString())
            // The Path is within current working directory
            $prependPath =
                (new SeqPathJoinUri($dir->pathUri()->toString()))
                ->mask(new SeqPathJoinUri($this->getCwd()->pathUri()->toString()), false)
            ;
        else
            $prependPath =
                (new SeqPathJoinUri($dir->pathUri()->toString()))
                    ->joint(new SeqPathJoinUri($this->getCwd()->pathUri()->toString()), false)
                    ->append((new SeqPathJoinUri($dir->pathUri()->toString()))
                        ->mask(new SeqPathJoinUri($this->getCwd()->pathUri()->toString()), false)
                    );
            ;

        // append dir path to files
        array_walk($result, function(&$value, $key) use ($prependPath) {
            $value = (new SeqPathJoinUri($value))
                ->prepend($prependPath)
                ->toString()
            ;
        });*/

        return $result;
    }

    // File Implementation:

    /**
     * Changes file group
     *
     * @param iCommonInfo $file Path to the file
     * @param mixed $group A group name or number
     *
     * @throws \Exception
     * @return $this
     */
    function chgrp(iCommonInfo $file, $group)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        if (!@chgrp($filename, $group))
            throw new \Exception(sprintf(
                'Failed Changing Group Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache(); // don't cache result

        return $this;
    }

    /**
     * Gets file group
     *
     * - Returns the group of the file
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception On Failure
     * @return int|string
     */
    function getFileGroup(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $group = filegroup($filename);
        (!function_exists('posix_getgrgid')) ?:
            $group = posix_getgrgid($group);

        if (!$group)
            throw new \Exception(sprintf(
                'Failed To Know Group Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache(); // don't cache result

        return $group;
    }

    /**
     * Changes file mode
     *
     * @param iCommonInfo $file Path to the file
     * @param iFilePermissions $mode
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chmod(iCommonInfo $file, iFilePermissions $mode)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        if (!@chmod($filename, $mode->getTotalPerms()))
            throw new \Exception(sprintf(
                'Failed To Change File Mode For "%s".'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache(); // don't cache result

        return $this;
    }

    /**
     * Gets file permissions
     *
     * @param iCommonInfo $file
     *
     * @return iFilePermissions
     */
    function getFilePerms(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $fperm = @fileperms($filename);

        $perms = new FilePermissions();
        $perms->grantPermission($fperm);

        return $perms;
    }

    /**
     * Changes file owner
     *
     * @param iCommonInfo $file Path to the file
     * @param string $user A user name or number
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chown(iCommonInfo $file, $user)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        if (!@chown($filename, $user))
            throw new \Exception(sprintf(
                'Failed To Change Owner Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Gets file owner
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception On Failure
     * @return int|string The user Name/ID of the owner of the file
     */
    function getFileOwner(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $owner = @fileowner($filename); // fileowner() "root" is 0
        if (function_exists('posix_getgrgid'))
            $owner = posix_getgrgid($owner);

        if (!$owner)
            throw new \Exception(sprintf(
                'Failed To Know Group Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache(); // don't cache result

        return $owner;
    }

    /**
     * Copies file
     *
     * - Source is Directory:
     *      the destination must be a directory
     *      goto a
     * - Source is File:
     *      the destination can be a directory or file
     *          directory:
     *             (a) if exists it will be merged
     *                 not exists it will be created
     *          file:
     *              if file exists it will be overwrite
     *              copy source to destination with new name
     *
     * @param iCommonInfo $source
     * @param iCommon $dest
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function copy(iCommonInfo $source, iCommon $dest)
    {
        $srcPathStr = $this->pathUri()
            ->fromPathUri($source->pathUri())
            ->toString()
        ;

        $destPathStr   = $this->pathUri()
            ->fromPathUri($dest->pathUri())
            ->toString()
        ;

        // source must be valid
        $this->__validateFilepath($srcPathStr);

        if ($this->isDir($source) && !$this->isDir($dest))
            throw new \Exception(sprintf(
                'Invalid Destination Provided, We Cant Copy A Directory "%s" To File "%s".'
                , $srcPathStr, $destPathStr
            ));

        if (!$this->isDir($dest) && !$this->isFile($dest))
            throw new \Exception(sprintf(
                'Destination at "%s" Must be a File Or Directory For Copy.'
                , $destPathStr
            ));

        $copied = false;
        if ($this->isDir($dest)) {
            // Copy File to directory
            if (!$this->isExists($dest))
                $this->mkDir($dest, new FilePermissions(0755));

            if ($this->isFile($source))
                /** @var iFile $source */
                $copied = @copy(
                    $srcPathStr
                    , $destPathStr .$this->pathUri()->getSeparator(). $this->pathUri()
                        ->fromPathUri($source->pathUri())
                        ->getFilename()
                );
            else {
                // Copy Directory To Directory
                foreach($this->scanDir($source) as $fd)
                    ($copied = true) // we don't want rise error from here
                    and
                    $this->copy(
                        $this->mkFromPath($fd)
                        , $dest
                    );
            }
        } else {
            // Copy File To Destination(file)

            // make directories to destination to avoid error >>> {
            $destDir = $this->dirUp($dest);
            if (!$this->isExists($destDir))
                $this->mkDir($destDir, new FilePermissions(0777));
            // } <<<

            $copied = copy(
                $srcPathStr
                , $destPathStr
            );
        }

        if (!$copied)
            throw new \Exception(sprintf(
                'Error While Coping "%s" To "%s".'
                , $srcPathStr, $destPathStr
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Is File?
     *
     * ! It's not necessary to check file existence on storage
     *   Just Perform Object Check
     *   It can be used with isExists() combination
     *
     * @param iCommon|string $source
     *
     * @return bool
     */
    function isFile($source)
    {
        $return = false;

        if (is_string($source))
            $return = @is_file($source);

        if(is_object($source))
            $return = $source instanceof iFileInfo;

        return $return;
    }

    /**
     * Is Dir?
     *
     * ! It's not necessary to check file existence on storage
     *   Just Perform Object Check
     *   It can be used with isExists() combination
     *
     * @param iCommon|string $source
     *
     * @return bool
     */
    function isDir($source)
    {
        $return = false;

        if (is_string($source))
            $return = @is_dir($source);

        if(is_object($source))
            $return = $source instanceof iDirectoryInfo;

        return $return;
    }

    /**
     * Is Link?
     *
     * ! It's not necessary to check file existence on storage
     *   Just Perform Object Check
     *   It can be used with isExists() combination
     *
     * @param iCommon|string $source
     *
     * @return bool
     */
    function isLink($source)
    {
        $return = false;

        if (is_string($source))
            $return = @is_link($source);

        if(is_object($source))
            $return = $source instanceof iLinkInfo;

        return $return;
    }

    /**
     * Returns available space on filesystem or disk partition
     *
     * - Returns the number of available bytes as a float
     * - Using Current Working Directory
     *
     * @return float|LocalFS::DISKSPACE_*
     */
    function getFreeSpace()
    {
        $result = @disk_free_space(
            $this->getCwd()->pathUri()->toString()
        );

        if ($result === false)
            $result = self::DISKSPACE_UNKNOWN;

        return $result;
    }

    /**
     * Returns the total size of a filesystem or disk partition
     *
     * - Returns the number of available bytes as a float
     * - Using Current Working Directory
     *
     * @return float|LocalFS::DISKSPACE_*
     */
    function getTotalSpace()
    {
        $result = @disk_total_space(
            $this->getCwd()->pathUri()->toString()
        );

        if ($result === false)
            $result = self::DISKSPACE_UNKNOWN;

        return $result;
    }

    /**
     * Checks whether a file or directory exists
     *
     * - we got an object as arguments, then i can check for
     *   ::isDir, isFile::, based on object and return the
     *   result on last
     *
     * @param iCommonInfo $cnode
     *
     * @return boolean
     */
    function isExists(iCommonInfo $cnode)
    {
        $filename = $this->pathUri()
            ->fromPathUri($cnode->pathUri())
            ->toString()
        ;

        // Upon failure, an E_WARNING is emitted.
        $result = @file_exists($filename);
        clearstatcache();

        switch ($cnode) {
            case $cnode instanceof iDirectoryInfo:
                $result = $this->isDir($filename);
                break;
            case $cnode instanceof iFileInfo:
                $result = $this->isFile($filename);
                break;
            case $cnode instanceof iLinkInfo:
                $result = $this->isLink($filename);
                break;
        }

        return $result;
    }

    /**
     * Write a string to a file
     *
     * - If filename does not exist, the file is created
     *
     * ! fails if you try to put a file in a directory that doesn't exist.
     *
     * @param iFile  $file
     * @param string|iFileContentDelivery $contents
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function putFileContents(iFile $file, $contents)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $append  = /*($append) ? FILE_APPEND :*/ 0;
        $append |= LOCK_EX; // to prevent anyone else writing to the file at the same time

        if(file_put_contents($filename, $contents, $append) === false) // file will be created if not exists
            throw new \Exception(sprintf(
                'Failed To Put "%s" File Contents.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        $file->setContents($contents);

        return $this;
    }

    /**
     * Reads entire file into a string
     *
     * @param iFile $file
     * @param int   $maxlen Maximum length of data read
     *
     * @throws \Exception On Failure
     * @return string
     */
    function getFileContents(iFile $file, $maxlen = 0)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $content = @file_get_contents($filename);
        if ($content === false)
            throw new \Exception(sprintf(
                'Failed To Read Contents Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $content;
    }

    /**
     * Gets last access time of file
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getATime(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $result = @fileatime($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Get Access Time For "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache();

        return $result;
    }

    /**
     * Gets inode change time of file
     *
     * ! when the permissions, owner, group, or other
     *   metadata from the inode is updated
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getCTime(iCommonInfo $file)
    {
        // Note that on Windows systems, filectime will show the file creation time

        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $result = @filectime($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Get Change Time For "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache();

        return $result;
    }

    /**
     * Gets file modification time
     *
     * ! the time when the content of the file was changed
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getMTime(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;
        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $result = @filemtime($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Get Modified Time For "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache();

        return $result;
    }

    /**
     * Gets file size
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return int In bytes
     */
    function getFileSize(iFileInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $result = @filesize($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Get Size Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        clearstatcache();

        return $result;
    }

    /**
     * TODO Not Working
     *
     * Portable advisory file locking
     *
     * ! shared lock    (reader)
     *   exclusive lock (writer)
     *   release lock   (shared|exclusive)
     *
     * @param iFileInfo $file
     * @param int       $lock  LOCK_SH|LOCK_EX|LOCK_UN
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function flock(iFileInfo $file, $lock = LOCK_EX)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        $fp = fopen($filename, "r+");
        // Upon failure, an E_WARNING is emitted.
        $result = @flock($fp, $lock);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Lock "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $result;
    }

    /**
     * Tells whether a file/directory exists and is readable
     *
     * ! checks whether you can do getFileContents() or similar calls
     *   for directories to fetch contents list
     *
     * @param iCommonInfo $file
     *
     * @return bool
     */
    function isReadable(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        // Upon failure, an E_WARNING is emitted.
        $result = @is_readable($filename);

        clearstatcache();

        return $result;
    }

    /**
     * Tells whether the file/directory is writable
     *
     * @param iCommonInfo $file
     *
     * @return bool TRUE if the filename exists and is writable
     */
    function isWritable(iCommonInfo $file)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        // Upon failure, an E_WARNING is emitted.
        $result = @is_writable($filename);

        clearstatcache();

        return $result;
    }

    /**
     * Create a hard link
     *
     * @param iLinkInfo $link
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function mkLink(iLinkInfo $link)
    {
        $target = $link->getTarget();

        $targetname = $this->pathUri()
            ->fromPathUri($target->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($targetname);

        $filename = $this->pathUri()
            ->fromPathUri($link->pathUri())
            ->toString()
        ;

        // Upon failure, an E_WARNING is emitted.
        $result = @symlink(
            $targetname
            , $filename
        );
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Create "%s" Link.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Makes directory Recursively
     *
     * @param iDirectoryInfo $dir
     * @param iFilePermissions $mode
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function mkDir(iDirectoryInfo $dir, iFilePermissions $mode = null)
    {
        if ($mode === null)
            $mode = new FilePermissions(0755); // todo use static default permission

        $dirname = $this->pathUri()
            ->fromPathUri($dir->pathUri())
            ->toString()
        ;

        if (!@mkdir($dirname, $mode->getTotalPerms(), true))
            throw new \Exception(sprintf(
                'Failed To Make Directory "%s".'
                , $dirname
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Get Parent Directory Of Given File/Dir
     *
     * ! If there are no slashes in path, a current dir returned
     *
     * @param iCommonInfo $file
     *
     * @return iDirectory
     */
    function dirUp(iCommonInfo $file)
    {
        /*
         * TODO
         * dirname() is locale aware, so for it to see the correct
         * directory name with multibyte character paths, the matching
         * locale must be set using the setlocale() function.
         */
        $path = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->setBasename(null)
            ->setExtension(null)
            ->normalize()
            ->toString();

        $directory = $this->mkFromPath($path);

        return $directory;
    }

    /**
     * Returns the base filename of the given path.
     *
     * @param iCommonInfo $file
     *
     * @return string
     */
    function getFilename(iCommonInfo $file)
    {
        /*
         * TODO
         * basename() is locale aware, so for it to see the correct
         * directory name with multibyte character paths, the matching
         * locale must be set using the setlocale() function.
         */

        $pathStr = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        return basename($pathStr);
    }

    /**
     * Get Extension Of File
     *
     * ! empty screen if dose`nt have ext
     *
     * @param iFileInfo $file
     *
     * @return string
     */
    function getFileExtension(iFileInfo $file)
    {
        /*
         * TODO
         * basename() is locale aware, so for it to see the correct
         * directory name with multibyte character paths, the matching
         * locale must be set using the setlocale() function.
         */

        $pathStr = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        return pathinfo($pathStr, PATHINFO_EXTENSION);
    }

    /**
     * Get File/Folder Name Without Extension
     *
     * @param iCommonInfo $file
     *
     * @return string
     */
    function getBasename(iCommonInfo $file)
    {
        /*
         * TODO
         * basename() is locale aware, so for it to see the correct
         * directory name with multibyte character paths, the matching
         * locale must be set using the setlocale() function.
         */

        $pathStr = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        return pathinfo($pathStr, PATHINFO_FILENAME);
    }

    /**
     * Rename File Or Directory
     *
     * - new name can contains absolute path
     *   /new/path/to/renamed.file
     * - if new name is just name
     *   append file directory path to new name
     * - moving it between directories if necessary
     * - If newname exists, it will be overwritten
     *
     * @param iCommonInfo $file
     * @param string      $newName
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function rename(iCommonInfo $file, $newName)
    {
        $pathInfo = (new FilePathUri($newName))->toArray();
        if (!isset($pathInfo['path']))
            $newName = ($this->dirUp($file)->pathUri()->toString())
                .$this->pathUri()->getSeparator(). $newName;

        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        if (!@rename($filename, $newName))
            throw new \Exception(sprintf(
                'Failed To Rename "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Attempts to remove the directory
     *
     * - If Directory was not empty, attempt recursive
     *   remove for files and nested directories
     *
     * @param iDirectoryInfo $dir
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function rmDir(iDirectoryInfo $dir)
    {
        $dirName = $this->pathUri()
            ->fromPathUri($dir->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($dirName);

        $lsDir = $this->scanDir($dir);
        if (!empty($lsDir))
            foreach($lsDir as $ls) {
                // First: Delete Directories Recursively
                $node = $this->mkFromPath($ls);
                if ($this->isDir($node))
                    $this->rmDir($node);
                else
                    $this->unlink($node);
            }

        // Ensure That Folder Is Empty: Delete It
        if (!@rmdir($dirName))
            throw new \Exception(sprintf(
                'Error While Deleting "%s" File.'
                , $dirName
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Sets access time of file
     *
     * ! Prior to PHP 5.3.0 it was not possible to change
     *   the modification time of a directory with this
     *   function under Windows.
     *
     * @param iCommonInfo $file
     * @param null        $time
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chATime(iCommonInfo $file, $time = null)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($file);

        // Upon failure, an E_WARNING is emitted.
        $result = @touch($filename, null, $time);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Touch "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Sets modification time of file
     *
     * ! Prior to PHP 5.3.0 it was not possible to change
     *   the modification time of a directory with this
     *   function under Windows.
     *
     * @param iCommonInfo $file
     * @param null        $time
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chMTime(iCommonInfo $file, $time = null)
    {
        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($file);

        // Upon failure, an E_WARNING is emitted.
        $result = @touch($filename, $time);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Touch "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Returns the target of a symbolic link
     *
     * @param iLinkInfo $link
     *
     * @throws \Exception On Failure
     * @return iCommonInfo File or Directory
     */
    function linkRead(iLinkInfo $link)
    {
        $filename = $this->pathUri()
            ->fromPathUri($link->pathUri())
            ->toString()
        ;

        $result = readlink($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Read Link From "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this->mkFromPath($result);
    }

    /**
     * Deletes a file
     *
     * @param iFileInfo|iLinkInfo $file
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function unlink($file)
    {
        if (!$file instanceof iFileInfo
            && !$file instanceof iLinkInfo
        )
            throw new \Exception(sprintf(
                'iFileInfo or iLinkInfo instance must given, but "%s" passed.'
                , is_object($file) ? get_class($file) : gettype($file)
            ));

        $filename = $this->pathUri()
            ->fromPathUri($file->pathUri())
            ->toString()
        ;

        // Upon failure, an E_WARNING is emitted.
        $result = @unlink($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Delete "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Gives information about a file
     *
     * @link http://php.net/manual/en/function.stat.php
     *
     * @param iCommonInfo $node
     *
     * @throws FileNotFoundException
     * @throws \Exception
     * @return array
     */
    function getStat(iCommonInfo $node)
    {
        $filename = $this->pathUri()
            ->fromPathUri($node->pathUri())
            ->toString()
        ;

        $this->__validateFilepath($filename);

        // Upon failure, an E_WARNING is emitted.
        $result = stat($filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Get Stat Of "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $result;
    }

    /**
     * Validate the Real File Path is exists?
     *
     * @param string $realpath
     *
     * @throws FileNotFoundException
     */
    protected function __validateFilepath($realpath)
    {
        // Upon failure, an E_WARNING is emitted.
        $result = @file_exists($realpath);
        clearstatcache();

        if (!$result)
            throw new FileNotFoundException(sprintf(
                'File "%s" Not Found.'
                , $realpath
            ));
    }
}
