<?php
namespace Poirot\Filesystem\Adapter;

use Poirot\Filesystem\Adapter\Local\LocalFS;
use Poirot\Filesystem\Interfaces\Filesystem\iCommonInfo;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iFilePermissions;
use Poirot\Filesystem\Interfaces\iFilesystem;
use Poirot\Filesystem\Interfaces\iFsBase;
use Poirot\Filesystem\Interfaces\iFilesystemAware;
use Poirot\Filesystem\Interfaces\iFilesystemProvider;
use Poirot\PathUri\Interfaces\iFilePathUri;
use Poirot\PathUri\FilePathUri;

abstract class AbstractCommonNode
    implements
    iCommonInfo,
    iFilesystemAware,
    iFilesystemProvider
{
    /**
     * @var iFsBase
     */
    protected $filesystem;

    /**
     * @var iFilePathUri
     */
    protected $pathUri;

    /**
     * Construct
     *
     * @param array|string|iFilePathUri $pathUri
     * @throws \Exception
     */
    function __construct($pathUri = null)
    {
        if ($pathUri instanceof iFilePathUri)
            $pathUri = $pathUri->toArray();
        elseif (is_string($pathUri))
            $pathUri = $this->pathUri()->parse($pathUri);

        if ($pathUri !== null) {
            if (is_array($pathUri))
                $this->pathUri()->fromArray($pathUri);
            else
                throw new \Exception(sprintf(
                    'PathUri must be instanceof iFilePathUri, Array or String, given: %s'
                    , is_object($pathUri) ? get_class($pathUri) : gettype($pathUri)
                ));
        }
    }

    /**
     * Get Path Uri Filename
     *
     * - it used to build uri address to file
     *
     * @return iFilePathUri
     */
    function pathUri()
    {
        if (!$this->pathUri)
            $this->pathUri = (new FilePathUri)
                // by default create relative paths
                ->setPathStrMode(FilePathUri::PATH_AS_RELATIVE)
                ->setSeparator(
                    $this->filesystem()->pathUri()
                        ->getSeparator()
                )
            ;

        return $this->pathUri;
    }

    /**
     * Returns parent directory's path
     *
     * /etc/passwd => /etc
     *
     * @return iDirectory
     */
    function dirUp()
    {
        return $this->filesystem()->dirUp($this);
    }

    /**
     * Gets the owner of the file
     *
     * @return mixed
     */
    function getOwner()
    {
        return $this->filesystem()->getFileOwner($this);
    }

    /**
     * Gets file permissions
     * Should return an or combination of the PERMISSIONS
     *
     * exp. from storage WRITABLE|EXECUTABLE
     *
     * @return iFilePermissions
     */
    function getPerms()
    {
        return $this->filesystem()->getFilePerms($this);
    }

    /**
     * Gets last access time of the file
     *
     * @return int Unix-TimeStamp
     */
    function getATime()
    {
        return $this->filesystem()->getATime($this);
    }

    /**
     * Returns the inode change time for the file
     *
     * @return int Unix-TimeStamp
     */
    function getCTime()
    {
        return $this->filesystem()->getCTime($this);
    }

    /**
     * Gets the last modified time
     *
     * @return int Unix-TimeStamp
     */
    function getMTime()
    {
        return $this->filesystem()->getMTime($this);
    }

    /**
     * Tells if file is readable
     *
     * @return bool
     */
    function isReadable()
    {
        return $this->filesystem()->isReadable($this);
    }

    /**
     * Set Filesystem
     *
     * @param iFilesystem $filesystem
     *
     * @return $this
     */
    function setFilesystem(iFilesystem $filesystem)
    {
        $this->filesystem = $filesystem;

        return $this;
    }

    /**
     * @return iFilesystem
     */
    function filesystem()
    {
        if (!$this->filesystem)
            $this->filesystem = new LocalFS();

        return $this->filesystem;
    }

    function __clone()
    {
        foreach ($this as &$var)
            if (is_object($var))
                $var = clone $var;
    }
}
