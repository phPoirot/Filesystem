<?php
namespace Poirot\Filesystem\Interfaces\Filesystem;

interface iFile extends iFileInfo, iCommon
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
     * Reads entire file into a string
     *
     * ! if file not exists return null
     * ! check permissions, getPerms
     *
     * @return string|null
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
     * - If Content provided, it must use set content method
     *   OtherWise Use Current Content With getContent method
     *
     * @param string|null $content Content
     *
     * @return $this
     */
    function putContents($content = null);

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
    function copy(/* iFile|iDirectory */ $fileDir);

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
    function move(/* iFile|iDirectory */$fileDir);

    /**
     * Rename File And Write To Storage
     *
     * @param string $newName New File name
     *
     * @return $this
     */
    function rename($newName);

    /**
     * Deletes a file from storage
     *
     * @return $this
     */
    function unlink();
}
