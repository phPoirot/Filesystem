<?php
namespace Poirot\Filesystem\Interfaces\Filesystem;

use Poirot\PathUri\Interfaces\iFilePathUri;

interface iCommonInfo
{
    /**
     * Get Path Uri Filename
     *
     * - it used to build uri address to file
     *
     * note: you must retrieve PathUri Object
     *       from Filesystem on classes that extends
     *       from iFilesystemProvider
     *
     * @return iFilePathUri
     */
    function pathUri();

    /**
     * Gets the file group
     *
     * @return mixed
     */
    function getGroup();

    /**
     * Gets the owner of the file
     *
     * @return mixed
     */
    function getOwner();

    /**
     * Gets file permissions
     * Should return an or combination of the PERMISSIONS
     *
     * exp. from storage WRITABLE|EXECUTABLE
     *
     * @return iFilePermissions
     */
    function getPerms();

    /**
     * Gets last access time of the file
     *
     * @return int Unix-TimeStamp
     */
    function getATime();

    /**
     * Returns the inode change time for the file
     *
     * @return int Unix-TimeStamp
     */
    function getCTime();

    /**
     * Gets the last modified time
     *
     * @return int Unix-TimeStamp
     */
    function getMTime();

    /**
     * Returns parent directory's path
     *
     * /etc/passwd => /etc
     *
     * @return iDirectory
     */
    function dirUp();

    /**
     * Tells if file is readable
     *
     * @return bool
     */
    function isReadable();
}
