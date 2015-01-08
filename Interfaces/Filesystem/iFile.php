<?php
namespace Poirot\Filesystem\Interfaces\Filesystem;

interface iFile extends iFileInfo, iCommon, iWritable
{
    /**
     * Lock File
     *
     * @return $this
     */
    function lock();

    /**
     * Unlock file
     *
     * @return $this
     */
    function unlock();

    /**
     * Set the file extension
     *
     * ! throw exception if file is lock
     *
     * @param string|null $ext File Extension
     *
     * @return $this
     */
    function setExtension($ext);

    /**
     * Reads entire file into a string
     *
     * ! check permissions, getPerms
     *
     * @return string
     */
    function getContents();

    /**
     * Set File Contents
     *
     * ! check permissions, getPerms
     *
     * @param string $contents Contents
     *
     * @return $this
     */
    function setContents($contents);

    /**
     * Put File Contents to Storage
     *
     * @param string $content Content
     *
     * @return $this
     */
    function putContents($content);

    /**
     * Copy to new file
     *
     * - If Directory Given Copy With Same Name To Directory
     *
     * @param iFile|iDirectory $fileDir
     *
     * @throws \Exception Throw Exception If File Exists
     * @return $this
     */
    function copy($fileDir);

    /**
     * Move to new file
     *
     * - If Directory Given Copy With Same Name To Directory
     *
     * @param iFile|iDirectory $fileDir
     *
     * @throws \Exception Throw Exception If File Exists
     * @return $this
     */
    function move($fileDir);

    /**
     * Rename File And Write To Storage
     *
     * @param string $newname New name
     *
     * @return $this
     */
    function rename($newname);

    /**
     * Deletes a file from storage
     *
     * @return bool
     */
    function unlink();
}
