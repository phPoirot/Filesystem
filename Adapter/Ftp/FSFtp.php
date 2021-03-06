<?php
namespace Poirot\Filesystem\Adapter\Ftp;

use Poirot\Std\Interfaces\ipOptionsProvider;
use Poirot\Filesystem\Adapter\AbstractCommonNode;
use Poirot\Filesystem\Adapter\Directory;
use Poirot\Filesystem\Adapter\File;
use Poirot\Filesystem\Interfaces\Filesystem\iCommon;
use Poirot\Filesystem\Interfaces\Filesystem\iCommonInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectoryInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iFile;
use Poirot\Filesystem\Interfaces\Filesystem\iFileInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iLinkInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iFilePermissions;
use Poirot\Filesystem\Interfaces\iFsBase;
use Poirot\Filesystem\FilePermissions;
use Poirot\PathUri\Interfaces\iFilePathUri;
use Poirot\PathUri\FilePathUri;

class FSFtp implements
    iFsBase,
    ipOptionsProvider
{
    /**
     * @var FSFtpOptions
     */
    protected $options;

    /**
     * @var resource Ftp Connection Resource
     */
    protected $resource;

    /**
     * ! public for accessible from Options
     *
     * @var bool
     */
    public $refreshResource = false;

    /**
     * @var FilePathUri
     */
    protected $pathUri;

    /**
     * Construct
     *
     * - pass ftp options on construct
     *
     * @param Array|FSFtpOptions $options Ftp Connection Options
     *
     * @throws \Exception
     */
    function __construct($options)
    {
        if (!is_array($options) && !$options instanceof FSFtpOptions)
            throw new \Exception(sprintf(
                'Constructor Except "Array" or Instanceof "AbstractOptions", but "%s" given.'
                , is_object($options) ? get_class($options) : gettype($options)
            ));

        $this->optsData()->from($options);

        // inject filesystem, refresh connection on option changes
        $this->optsData()->setFtpFilesystem($this);
    }

    /**
     * Get Ftp Connection Handler
     * to perform filesystem actions
     *
     * - Used Options to get connect
     *
     * @throws \Exception On Failed Connecting
     * @return resource
     */
    protected function getConnect()
    {
        if ($this->resource !== null
            && !$this->refreshResource // not has a request for refresh connection
        )
            return $this->resource;

        $serverUri  = $this->optsData()->getServerUri();
        $serverPort = $this->optsData()->getPort();
        $timeout    = $this->optsData()->getTimeout();

        $username   = $this->optsData()->getUsername();

        if ($this->optsData()->getUseSsl())
            $conn   = ftp_ssl_connect($serverUri, $serverPort, $timeout);
        else
            $conn   = ftp_connect($serverUri, $serverPort, $timeout);

        $loginR = @ftp_login(
            $conn,
            $username,
            $this->optsData()->getPassword()
        );

        if (!$conn || !$loginR)
            throw new \Exception(sprintf(
                'Ftp Connection Failed to "%s" for user "%s"'
                , $serverUri, $username
            ));

        /*
         * Some complain that ftp_nlist, always return FALSE.
         * I did experience this behavior myself, until I used ftp_pasv,
         * which is useful if your client is behind a firewall
         * (which most clients are now)
         */
        ftp_pasv($conn, true);

        $this->resource = $conn;
        $this->refreshResource = false; // the resource is refreshed

        return $this->getConnect();
    }

    /**
     * Destruct
     */
    function __destruct()
    {
        if (!$this->resource)
            return false;

        ftp_close($this->resource);
        $this->resource = null;
        $this->refreshResource = false;
    }

    /**
     * Inject File System To Filesystem Node
     *
     * @param AbstractCommonNode $fsNode
     *
     * @return \Poirot\Filesystem\Adapter\AbstractCommonNode
     */
    protected function injectFilesystem(AbstractCommonNode $fsNode)
    {
        $fsNode->setFilesystem($this);

        return $fsNode;
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
        $return = false;

        if ($this->isDir($path))
            $return = new Directory($path);
        elseif ($this->isFile($path))
            $return = new File($path);

        if (!$return)
            throw new \Exception(sprintf(
                'Path "%s" not recognized.'
                , $path
            ));

        $return->setFilesystem($this);

        return $return;
    }

    /**
     * Get Path Uri Object
     *
     * - it used to build/parse uri address to file
     *   by filesystem
     *
     * @return iFilePathUri
     */
    function getPathUri()
    {
        if (!$this->pathUri)
            $this->pathUri = new FilePathUri;

        return $this->pathUri;
    }

    /**
     * Gets the current working directory
     *
     * @throws \Exception On Failure
     * @return iDirectory
     */
    function getCwd()
    {
        $cwd = ftp_pwd($this->getConnect());
        if ($cwd === false)
            throw new \Exception('Failed To Get Current Working Directory.');

        return $this->injectFilesystem(new Directory($cwd));
    }

    /**
     * List an array of files/directories path from the directory
     *
     * - get rid of ".", ".." from list
     *
     * @param iDirectoryInfo|null $dir If Null Scan Current Working Directory
     * @param int $sortingOrder SCANDIR_SORT_NONE|SCANDIR_SORT_ASCENDING|SCANDIR_SORT_DESCENDING
     *
     * @throws \Exception On Failure
     * @return array
     */
    function scanDir(iDirectoryInfo $dir = null, $sortingOrder = self::SCANDIR_SORT_NONE)
    {
        if ($dir === null)
            $dir = $this->getCwd();

        $dirname = $dir->pathUri()->toString();

        // it's included the full path
        $result  = ftp_nlist($this->getConnect(), $dirname);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed Scan Directory To "%s".'
                , $dirname
            ), null, new \Exception(error_get_last()['message']));

        // append dir path to files
        array_walk($result, function(&$value, $key) use ($dirname)  {
            $value = @end(explode('/', $value)); // FilePathUri
            $value = $dirname.'/'.$value;        // FilePathUri
        });

        // get rid of the dots
        $result = array_diff($result, array(
            $dirname.'/..', // FilePathUri
            $dirname.'/.'   // FilePathUri
            )
        );

        return $result;
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
        $dirname = $dir->pathUri()->toString();
        if (@ftp_chdir($this->getConnect(), $dirname) === false)
            throw new \Exception(sprintf(
                'Failed Changing Directory To "%s", your cwd is "%s".'
                , $dirname , $this->getCwd()->pathUri()->toString()
            ));

        return $this;
    }

    /**
     * Changes file group
     *
     * @param iCommonInfo $file Path to the file
     * @param mixed $group A group name or number
     *
     * @return $this
     */
    function chgrp(iCommonInfo $file, $group)
    {
        // TODO Implement Feature.

        return $this;
    }

    /**
     * Gets file group
     *
     * - Returns the group of the file
     *
     * @param iCommonInfo $node File Or Directory
     *
     * @throws \Exception On Failure
     * @return int|string
     */
    function getFileGroup(iCommonInfo $node)
    {
        $info = $this->getFSRawData($node);
        if (!isset($info['group']))
            throw new \Exception(sprintf(
                'Failed To Know Group Of "%s" File.'
                , $node->pathUri()->toString()
            ));

        return $info['group'];
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
        $filename = $file->pathUri()->toString();
        if (ftp_chmod($this->getConnect(), $mode->getTotalPerms(), $filename) === false)
            throw new \Exception(sprintf(
                'Failed To Change File Mode For "%s".'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
    }

    /**
     * Gets file permissions
     *
     * @param iCommonInfo $file
     *
     * @throws \Exception
     * @return iFilePermissions
     */
    function getFilePerms(iCommonInfo $file)
    {
        $info = $this->getFSRawData($file);
        if (!isset($info['rights']))
            throw new \Exception(sprintf(
                'Failed To Get Permissions Of "%s" File.'
                , $file->pathUri()->toString()
            ));

        $perms = new FilePermissions();
        $perms->fromString($info['rights']);

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
        $info = $this->getFSRawData($file);
        if (!isset($info['user']))
            throw new \Exception(sprintf(
                'Failed To Get Owner Of "%s" File.'
                , $file->pathUri()->toString()
            ));

        return $info['user'];
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
        $fname = $file->pathUri()->toString();

        /* TODO May not working for files more that 2gb */
        $fsize = ftp_size($this->getConnect(), $fname);
        if ($fsize == -1)
            throw new \Exception(sprintf(
                'Failed To Get Size Of "%s" File.'
                , $fname
            ));

        return $fsize;
    }

        /**
         * Get Raw Data Information For A File Or Directory
         *
         * ! return empty array if not found
         *
         * @param iDirectoryInfo|iFileInfo $node File Or Directory
         *
         * @return array
         */
        protected function getFSRawData($node)
        {
            $nFilename = $node->pathUri()->withoutLeadingDot()->toString();

            if ($this->isDir($node))
                // we can get rawlist of parent node dir
                // raw data of node may present as array key
                $node = $this->dirUp($node);

            $rwlist = $this->getRawList($node);

            $items = [];
            if (array_key_exists($nFilename, $rwlist))
                $items  = $rwlist[$nFilename];

            return $items;
        }

        /**
         * Get Raw List
         *
         * @param iCommonInfo $node File Or Directory
         *
         * @throws \Exception
         * @return array
         */
        protected function getRawList($node)
        {
            $items = [];

            $rawlist = @ftp_rawlist($this->getConnect()
                , $node->pathUri()->withoutLeadingDot()->toString()
            );

            if (is_array($rawlist))
                foreach ($rawlist as $child) {
                    $chunks = preg_split("/\s+/", $child);
                    list($item['rights'], $item['number'], $item['user'], $item['group'], $item['size'], $item['month'], $item['day'], $item['time']) = $chunks;
                    $item['type'] = $chunks[0]{0} === 'd' ? 'directory' : 'file';
                    array_splice($chunks, 0, 8);
                    $items[implode(" ", $chunks)] = $item;
                }

            return $items;
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
        // source must be valid
        if (!$this->isExists($source))
            throw new \Exception(sprintf(
                'Source File Not Found On "%s"'
                , $source->pathUri()->toString()
            ));

        if ($this->isDir($source) && !$this->isDir($dest))
            throw new \Exception(sprintf(
                'Invalid Destination Provided, We Cant Copy A Directory "%s" To File "%s".'
                , $source->pathUri()->toString(), $dest->pathUri()->toString()
            ));

        if (!$this->isDir($dest) && !$this->isFile($dest))
            throw new \Exception(sprintf(
                'Destination at "%s" Must be a File Or Directory For Copy.'
                , $dest->pathUri()->toString()
            ));

        $copied = false;
        if ($this->isDir($dest)) {
            // Copy to directory
            if (!$this->isExists($dest))
                $this->mkDir($dest, new FilePermissions(0755));

            if ($this->isFile($source)) {
                /** @var iFile $source */
                // download and upload file again
                $content = $this->getFileContents($source);
                $dfile = new File($dest->pathUri()->toString().'/'.$source->pathUri()->getFilename());
                $this->putFileContents($dfile, $content);
                $copied = true;
            } else {
                // Merge Folder
                $destDirName = $dest->pathUri()->toString().'/'.$source->pathUri()->getFilename();
                $copied = true; // we don't want rise error from here
                foreach($this->scanDir($source) as $fd)
                    $this->copy(
                        $this->mkFromPath($fd)
                        , new Directory($destDirName)
                    );
            }
        } else {
            // Copy File To Destination(file)

            // make directories to destination to avoid error >>> {
            $destDir = $this->dirUp($dest);
            if (!$this->isExists($destDir))
                $this->mkDir($destDir, new FilePermissions(0777));
            // } <<<

            // download and upload file again
            $content = $this->getFileContents($source);
            $dfile = new File($dest->pathUri()->toString());
            $this->putFileContents($dfile, $content);

            $copied = true;
        }

        if (!$copied)
            throw new \Exception(sprintf(
                'Error While Coping "%s" To "%s".'
                , $source->pathUri()->toString(), $dest->pathUri()->toString()
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
            $return = (ftp_size($this->getConnect(), $source) != -1);

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

        if (is_string($source)) {
            $cwd = $this->getCwd();
            try {
                $this->chDir(new Directory($source));
                $return = true;
            } catch(\Exception $e) {
                // leave it be, false returned
            }

            // get back to current directory
            $this->chDir($cwd);
        }

        if (is_object($source))
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
     * @return float|FSFtp::DISKSPACE_*
     */
    function getFreeSpace()
    {
        return self::DISKSPACE_UNKNOWN;
    }

    /**
     * Returns the total size of a filesystem or disk partition
     *
     * - Returns the number of available bytes as a float
     * - Using Current Working Directory
     *
     * @return float|FSFtp::DISKSPACE_*
     */
    function getTotalSpace()
    {
        return self::DISKSPACE_UNKNOWN;
    }

    /**
     * Checks whether a file or directory exists
     *
     * return FALSE for symlinks pointing to non-existing files
     *
     * @param iCommonInfo $file
     *
     * @return boolean
     */
    function isExists(iCommonInfo $file)
    {
        $rawlist = $this->getFSRawData($file);

        return !empty($rawlist);
    }

    /**
     * Reads entire file into a string
     *
     * @param iFile $file
     * @param int $maxlen Maximum length of data read
     *
     * @throws \Exception On Failure
     * @return string
     */
    function getFileContents(iFile $file, $maxlen = 0)
    {
        $tmpFile = @fopen('php://temp', 'Wb+');
        if ($tmpFile === false)
            throw new \Exception('Failed To Initialize Temp File.');

        $fname   = $file->pathUri()->toString();

        if (ftp_fget($this->getConnect(), $tmpFile, $fname, FTP_BINARY) === false)
            throw new \Exception(sprintf(
                'Failed To Read Contents Of "%s" File.'
                , $fname
            ));

        $size = fstat($tmpFile)['size'];
        rewind($tmpFile);

        return fread($tmpFile, $size);
    }

    /**
     * Write a string to a file
     *
     * - If filename does not exist, the file is created
     *
     * @param iFile $file
     * @param string $contents
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function putFileContents(iFile $file, $contents)
    {
        $tmpFile = @fopen('php://temp', 'Wb+');
        if ($tmpFile === false)
            throw new \Exception('Failed To Initialize Temp File.');

        if(fwrite($tmpFile, $contents) === false)
            throw new \Exception('Failed To Write To Temp File.');

        $size = fstat($tmpFile)['size'];
        rewind($tmpFile);

        $fname   = $file->pathUri()->toString();
        if (!ftp_alloc($this->getConnect(), $size, $serverResult))
            throw new \Exception(sprintf(
                'Unable to allocate space on server.  Server said: %s'
                , $serverResult
            ));

        if (ftp_fput($this->getConnect(), $fname, $tmpFile, FTP_BINARY) === false)
            throw new \Exception(sprintf(
                'Failed To Write Contents To "%s" File.'
                , $fname
            ));

        return $this;
    }

    /**
     * Gets last access time of file
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getFileATime(iFileInfo $file)
    {
        return false;
    }

    /**
     * Gets inode change time of file
     *
     * ! when the permissions, owner, group, or other
     *   metadata from the inode is updated
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getFileCTime(iFileInfo $file)
    {
        return false;
    }

    /**
     * Gets file modification time
     *
     * ! the time when the content of the file was changed
     *
     * !! Not all servers support this feature!
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getMTime(iFileInfo $file)
    {
        $filename = $file->pathUri()->toString();
        // Upon failure, an E_WARNING is emitted.
        $result = ftp_mdtm($this->getConnect(), $filename);
        if ($result === -1)
            throw new \Exception(sprintf(
                'Failed To Get Modified Time For "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $result;
    }

    /**
     * Portable advisory file locking
     *
     * ! shared lock    (reader)
     *   exclusive lock (writer)
     *   release lock   (shared|exclusive)
     *
     * @param iFileInfo $file
     * @param int $lock LOCK_SH|LOCK_EX|LOCK_UN
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function flock(iFileInfo $file, $lock = LOCK_EX)
    {
        return $this;
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
        return $this->isExists($file);
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
        return $this->isExists($file);
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
        // TODO: Implement mkLink() method.
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
    function mkDir(iDirectoryInfo $dir, iFilePermissions $mode)
    {
        $dirpath = $dir->pathUri()->toString();
        if (in_array($dirpath, ['.', '/']))
            return $this;

        $cwdTmp = $this->getCwd(); // store current working dir

        // Its may included '.' or '' for leading slashes /dir
        $pathSections = (explode('/', $dirpath));

        do {
            $mkdir = array_shift($pathSections);
        } while(in_array($mkdir, ['.']));

        if (!$this->isDir($mkdir)) {
            // create directory if not exists
            if (ftp_mkdir($this->getConnect(), $mkdir) === false)
                throw new \Exception(sprintf(
                    'Failed To Make Directory "%s".'
                    , $mkdir
                ), null, new \Exception(error_get_last()['message']));

            $this->chmod(new Directory($mkdir), $mode);
        }

        $this->chDir(new Directory($mkdir));

        $this->mkDir(
            new Directory(implode('/', $pathSections))
            , $mode
        );

        $this->chDir($cwdTmp); // get back to working directory

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
        $pathname  = $file->pathUri()->getPath();

        return $this->injectFilesystem(new Directory($pathname));
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
     * @param string $newName
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function rename(iCommonInfo $file, $newName)
    {
        $pathInfo = (new FilePathUri($newName))->toArray();
        if (!isset($pathInfo['path']))
            $newName = $this->dirUp($file)->pathUri()->toString()
                .'/'. $newName;

        if (ftp_rename($this->getConnect(), $file->pathUri()->toString(), $newName) === false)
            throw new \Exception(sprintf(
                'Failed To Rename "%s" File.'
                , $file->pathUri()->toString()
            ));

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
        if (!ftp_rmdir($this->getConnect(), $dir->pathUri()->toString()))
            throw new \Exception(sprintf(
                'Error While Deleting "%s" File.'
                , $dir->pathUri()->toString()
            ));

        return $this;
    }

    /**
     * Sets access time of file
     *
     * @param iFile $file
     * @param null $time
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chFileATime(iFile $file, $time = null)
    {
        // TODO: Implement chFileATime() method.
    }

    /**
     * Sets modification time of file
     *
     * @param iFile $file
     * @param null $time
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chFileMTime(iFile $file, $time = null)
    {
        // TODO: Implement chFileMTime() method.
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
        // TODO: Implement linkRead() method.
    }

    /**
     * Deletes a file
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function unlink(iFileInfo $file)
    {
        $filename = $file->pathUri()->toString();
        // Upon failure, an E_WARNING is emitted.
        $result = ftp_delete($this->getConnect(), $filename);
        if ($result === false)
            throw new \Exception(sprintf(
                'Failed To Delete "%s" File.'
                , $filename
            ), null, new \Exception(error_get_last()['message']));

        return $this;
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
        return basename($file->pathUri()->toString());
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
        return pathinfo($file->pathUri()->toString(), PATHINFO_EXTENSION);
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
        return pathinfo($file->pathUri()->toString(), PATHINFO_FILENAME);
    }

    /**
     * @return FSFtpOptions
     */
    function optsData()
    {
        if (!$this->options)
            $this->options = new FSFtpOptions();

        return $this->options;
    }

    /**
     * Get An Bare Options Instance
     *
     * - return always new instance of same object that used
     *   by ::options()
     *
     * ! it used on easy access to options instance
     *   before constructing class
     *   [php]
     *      $opt = Filesystem::optionsIns();
     *      $opt->setSomeOption('value');
     *
     *      $class = new Filesystem($opt);
     *   [/php]
     *
     * @return FSFtpOptions
     */
    static function newOptsData()
    {
        return new FSFtpOptions();
    }
}
 