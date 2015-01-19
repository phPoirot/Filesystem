<?php
namespace Poirot\Filesystem\Ftp;

use Poirot\Core\AbstractOptions;
use Poirot\Core\Interfaces\OptionsProviderInterface;
use Poirot\Filesystem\Abstracts\Common;
use Poirot\Filesystem\Abstracts\Directory;
use Poirot\Filesystem\Interfaces\Filesystem\iCommon;
use Poirot\Filesystem\Interfaces\Filesystem\iCommonInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectoryInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iFile;
use Poirot\Filesystem\Interfaces\Filesystem\iFileInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iLinkInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iPermissions;
use Poirot\Filesystem\Interfaces\iFilesystem;

class Filesystem implements
    iFilesystem,
    OptionsProviderInterface
{
    /**
     * @var FtpOptions
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
     * Construct
     *
     * - pass ftp options on construct
     *
     * @param Array|FtpOptions $options Ftp Connection Options
     *
     * @throws \Exception
     */
    function __construct($options)
    {
        if ($options !== null)
            if ($options instanceof FtpOptions)
                foreach($options->props()->writable as $opt)
                    $this->options()->{$opt} = $options->{$opt};
            elseif (is_array($options))
                $this->options()->fromArray($options);
            else
                throw new \Exception(sprintf(
                    'Constructor Except "Array" or Instanceof "AbstractOptions", but "%s" given.'
                    , is_object($options) ? get_class($options) : gettype($options)
                ));

        // inject filesystem, refresh connection on option changes
        $this->options()->setFtpFilesystem($this);
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
        if ($this->resource !== null && !$this->refreshResource)
            return $this->resource;

        $serverUri = $this->options()->getServerUri();
        $username  = $this->options()->getUsername();

        $conn   = ftp_connect($serverUri);
        $loginR = ftp_login(
            $conn,
            $username,
            $this->options()->getPassword()
        );

        if (!$conn || !$loginR)
            throw new \Exception(sprintf(
                'Ftp Connection Failed to "%s" for user "%s"'
            , $serverUri, $username));

        $this->resource = $conn;
        $this->refreshResource = false; // the resource refreshed

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
     * @param Common $fsNode
     *
     * @return \Poirot\Filesystem\Abstracts\Common
     */
    protected function injectFilesystem(Common $fsNode)
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
        // TODO: Implement mkFromPath() method.
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

        return $this->mkFromPath($cwd);
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
        // TODO: Implement scanDir() method.
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
        $dirname = $dir->getRealPathName();
        if (@ftp_chdir($this->getConnect(), $dirname) === false)
            throw new \Exception(sprintf(
                'Failed Changing Directory To "%s".'
                , $dirname
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
        // TODO: Implement chgrp() method.
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
        // TODO: Implement getFileGroup() method.
    }

    /**
     * Changes file mode
     *
     * @param iCommonInfo $file Path to the file
     * @param iPermissions $mode
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function chmod(iCommonInfo $file, iPermissions $mode)
    {
        // TODO: Implement chmod() method.
    }

    /**
     * Gets file permissions
     *
     * @param iCommonInfo $file
     *
     * @return iPermissions
     */
    function getFilePerms(iCommonInfo $file)
    {
        // TODO: Implement getFilePerms() method.
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
        // TODO: Implement chown() method.
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
        // TODO: Implement getFileOwner() method.
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
        // ftp_alloc — Allocates space for a file to be uploaded

        // TODO: Implement copy() method.
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
        // TODO: Implement isFile() method.
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
        // TODO: Implement isLink() method.
    }

    /**
     * Returns available space on filesystem or disk partition
     *
     * - Returns the number of available bytes as a float
     * - Using Current Working Directory
     *
     * @return float|self::DISKSPACE_*
     */
    function getFreeSpace()
    {
        // TODO: Implement getFreeSpace() method.
    }

    /**
     * Returns the total size of a filesystem or disk partition
     *
     * - Returns the number of available bytes as a float
     * - Using Current Working Directory
     *
     * @return float|self::DISKSPACE_*
     */
    function getTotalSpace()
    {
        // TODO: Implement getTotalSpace() method.
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
        // TODO: Implement isExists() method.
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
        // TODO: Implement getFileContents() method.
    }

    /**
     * Write a string to a file
     *
     * - If filename does not exist, the file is created
     *
     * @param iFile $file
     * @param string $contents
     * @param bool $append Append Content To File
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function putFileContents(iFile $file, $contents, $append = false)
    {
        // ftp_alloc — Allocates space for a file to be uploaded

        // TODO: Implement putFileContents() method.
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
        // TODO: Implement getFileATime() method.
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
        // TODO: Implement getFileCTime() method.
    }

    /**
     * Gets file modification time
     *
     * ! the time when the content of the file was changed
     *
     * @param iFileInfo $file
     *
     * @throws \Exception On Failure
     * @return int timestamp Unix timestamp
     */
    function getFileMTime(iFileInfo $file)
    {
        // TODO: Implement getFileMTime() method.
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
        // TODO: Implement getFileSize() method.
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
        // TODO: Implement flock() method.
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
        // TODO: Implement isReadable() method.
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
        // TODO: Implement isWritable() method.
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
     * @param iPermissions $mode
     *
     * @throws \Exception On Failure
     * @return $this
     */
    function mkDir(iDirectoryInfo $dir, iPermissions $mode)
    {
        // TODO: Implement mkDir() method.
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
    function getDirname(iCommonInfo $file)
    {
        // TODO: Implement getDirname() method.
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
        // TODO: Implement getFilename() method.
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
        // TODO: Implement getFileExtension() method.
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
        // TODO: Implement getBasename() method.
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
        // TODO: Implement rename() method.
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
        // TODO: Implement rmDir() method.
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
        // TODO: Implement unlink() method.
    }

    /**
     * @return FtpOptions
     */
    function options()
    {
        if (!$this->options)
            $this->options = new FtpOptions();

        return $this->options;
    }
}
 