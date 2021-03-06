<?php
namespace Poirot\Filesystem\Adapter;

use Poirot\Filesystem\Interfaces\Filesystem\File\iFileContentDelivery;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iFile;
use Poirot\Filesystem\Interfaces\Filesystem\iFilePermissions;

class File extends AbstractCommonNode
    implements
    iFile
{
    /**
     * @var file content internal cache
     */
    protected $_fcontent;

    /**
     * Set Owner
     *
     * @param int $owner
     *
     * @return $this
     */
    function chown($owner)
    {
        $this->filesystem()->chown($this, $owner);

        return $this;
    }

    /**
     * Changes file mode
     *
     * @param iFilePermissions $mode
     *
     * @return $this
     */
    function chmod(iFilePermissions $mode)
    {
        $this->filesystem()->chmod($this, $mode);

        return $this;
    }

    /**
     * Set Group
     *
     * @param $group
     *
     * @return $this
     */
    function chgrp($group)
    {
        $this->filesystem()->chgrp($this, $group);

        return $this;
    }

    /**
     * Gets the file group
     *
     * @return mixed
     */
    function getGroup()
    {
        return $this->filesystem()->getFileGroup($this);
    }

    /**
     * Copy to new directory
     *
     * - Merge if directory exists
     * - Create If Directory Not Exists
     *
     * @param $fileDir
     *
     * @return $this
     */
    function copy($fileDir)
    {
        $this->filesystem()->copy($this, $fileDir);

        return $this;
    }

    /**
     * Move to new directory
     *
     * ! use class copy/rmDir
     *
     * - Merge if directory exists
     * - Create If Directory Not Exists
     * - Use Temp Folder For Safe Move
     *
     * @param iDirectory $fileDir
     *
     * @return $this
     */
    function move($fileDir)
    {

    }

    /**
     * Is File/Folder Exists?
     *
     * @return bool
     */
    function isExists()
    {
        return $this->Filesystem()->isExists($this);
    }

    /**
     * Tells if the entry is writable
     *
     * - The writable beside of filesystem must
     *   implement iWritable
     *
     * @return bool
     */
    function isWritable()
    {
        return $this->filesystem()->isWritable($this);
    }

    /**
     * Lock File
     *
     * @return $this
     */
    function lock()
    {
        $this->filesystem()->flock($this);

        return $this;
    }

    /**
     * Unlock file
     *
     * @return $this
     */
    function unlock()
    {
        $this->filesystem()->flock($this, LOCK_UN);

        return $this;
    }

    /**
     * Reads entire file into a string
     *
     * ! if file not exists return null
     * ! check permissions, getPerms
     *
     * @return string|null
     */
    function getContents()
    {
        if ($this->_fcontent === null)
            if ($this->isExists())
                $this->setContents(
                    $this->filesystem()->getFileContents($this)
                );

        return $this->_fcontent;
    }

    /**
     * Set File Contents
     *
     * ! check permissions, getPerms
     *
     * @param string|iFileContentDelivery $contents Contents
     *
     * @return $this
     */
    function setContents($contents)
    {
        $this->_fcontent = $contents;

        return $this;
    }

    /**
     * Put File Contents to Storage
     *
     * - If Content provided, it must use set content method
     *   OtherWise Use Current Content With getContent method
     *
     * @param string|null $content Content
     *
     * @return $this
     */
    function putContents($content = null)
    {
        if ($content !== null)
            $this->setContents($content);
        else
            $content = $this->getContents();

        $this->filesystem()->putFileContents($this, $content);

        return $this;
    }

    /**
     * Rename File And Write To Storage
     *
     * @param string $newName New File name
     *
     * @return $this
     */
    function rename($newName)
    {
        $this->filesystem()->rename($this, $newName);

        return $this;
    }

    /**
     * Deletes a file from storage
     *
     * @return $this
     */
    function unlink()
    {
        $this->filesystem()->unlink($this);

        return $this;
    }

    /**
     * Gets the file size in bytes for the file referenced
     *
     * @return int
     */
    function getSize()
    {
        return $this->filesystem()->getFileSize($this);
    }
}
