<?php
namespace Poirot\Filesystem\Abstracts;

use Poirot\Core\BuilderSetterTrait;
use Poirot\Filesystem\Interfaces\Filesystem\iDirectory;
use Poirot\Filesystem\Interfaces\Filesystem\iPermissions;
use Poirot\Filesystem\Interfaces\iFilesystem;
use Poirot\Filesystem\Interfaces\iFilesystemAware;
use Poirot\Filesystem\Interfaces\iFilesystemProvider;
use Poirot\Filesystem\Local\Filesystem;
use Poirot\Filesystem\Permissions;
use Poirot\Filesystem\Util;

class Directory extends Common
    implements
    iDirectory
{
    /**
     * Get Path Name To File Or Folder
     *
     * - include full path for remote files
     * - include extension for files
     * - usually use Util::normalizePath on return
     *
     * @return string
     */
    function getRealPathName()
    {
        // remove trailing slashes, happen if current path is /
        $prefix = ($this->getPath()) ? $this->getPath().'/' : '';

        return Util::normalizePath($prefix.$this->getBasename());
    }

    /**
     * Makes directory Recursively
     *
     * @return $this
     */
    function mkDir()
    {
        $this->filesystem()->mkDir($this
            , $this->getPerms()
        );

        return $this;
    }

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
     * Gets the owner of the file
     *
     * @return mixed
     */
    function getOwner()
    {
        return $this->filesystem()->getFileOwner($this);
    }

    /**
     * Changes file mode
     *
     * @param iPermissions $mode
     *
     * @return $this
     */
    function chmod(iPermissions $mode)
    {
        $this->filesystem()->chmod($this, $mode);

        return $this;
    }

    /**
     * Gets file permissions
     * Should return an or combination of the PERMISSIONS
     *
     * exp. from storage WRITABLE|EXECUTABLE
     *
     * @return iPermissions
     */
    function getPerms()
    {
        return $this->filesystem()->getFilePerms($this);
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
     * Returns parent directory's path
     *
     * /etc/passwd => /etc
     *
     * @return iDirectory
     */
    function getDirname()
    {
        return $this->filesystem()->getDirname($this);
    }

    /**
     * Delete a directory from storage
     *
     * @return bool
     */
    function rmDir()
    {
        $this->filesystem()->rmDir($this);
    }

    /**
     * Copy to new directory
     *
     * - Merge if directory exists
     * - Create If Directory Not Exists
     *
     * @param iDirectory $directory
     *
     * @return $this
     */
    function copy(iDirectory $directory)
    {
        $this->filesystem()->copy($this, $directory);

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
     * @param iDirectory $directory
     *
     * @return $this
     */
    function move(iDirectory $directory)
    {

    }

    /**
     * List an array of files/directories Object from the directory
     *
     * @return array
     */
    function scanDir()
    {
        return $this->filesystem()->scanDir($this);
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
     * Tells if file is readable
     *
     * @return bool
     */
    function isReadable()
    {
        return $this->filesystem()->isReadable($this);
    }
}
