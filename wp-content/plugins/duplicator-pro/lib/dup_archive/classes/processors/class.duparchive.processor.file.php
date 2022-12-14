<?php
defined('ABSPATH') || defined('DUPXABSPATH') || exit;
/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


require_once(dirname(__FILE__).'/../headers/class.duparchive.header.file.php');
require_once(dirname(__FILE__).'/../headers/class.duparchive.header.glob.php');

if (!class_exists('DupArchiveFileProcessor')) {

    class DupArchiveFileProcessor
    {

        protected static $newFilePathCallback = null;

        public static function setNewFilePathCallback($callback)
        {
            if (!is_callable($callback)) {
                self::$newFilePathCallback = null;
                return false;
            }

            self::$newFilePathCallback = $callback;
            return true;
        }

        protected static function getNewFilePath($basePath, $relativePath)
        {
            if (is_null(self::$newFilePathCallback)) {
                return $basePath.'/'.$relativePath;
            } else {
                return call_user_func_array(self::$newFilePathCallback, array($relativePath));
            }
        }

        public static function writeFilePortionToArchive($createState, $archiveHandle, $sourceFilepath, $relativeFilePath)
        {
            /* @var $createState DupArchiveCreateState */

            DupArchiveUtil::tlog("writeFileToArchive for {$sourceFilepath}");

            // profile ok
            // switching to straight call for speed
            $sourceHandle = @fopen($sourceFilepath, 'rb');

            // end profile ok

            if ($sourceHandle === false) {
                $createState->archiveOffset     = DupProSnapLibIOU::ftell($archiveHandle);
                $createState->currentFileIndex++;
                $createState->currentFileOffset = 0;
                $createState->skippedFileCount++;
                $createState->addFailure(DupArchiveFailureTypes::File, $sourceFilepath, "Couldn't open $sourceFilepath", false);

                return;
            }

            if ($createState->currentFileOffset > 0) {
                DupArchiveUtil::tlog("Continuing {$sourceFilepath} so seeking to {$createState->currentFileOffset}");

                DupProSnapLibIOU::fseek($sourceHandle, $createState->currentFileOffset);
            } else {
                DupArchiveUtil::tlog("Starting new file entry for {$sourceFilepath}");


                // profile ok
                $fileHeader = DupArchiveFileHeader::createFromFile($sourceFilepath, $relativeFilePath);
                // end profile ok
                // profile ok
                $fileHeader->writeToArchive($archiveHandle);
                // end profile ok
            }

            // profile ok
            $sourceFileSize = filesize($sourceFilepath);

            DupArchiveUtil::tlog("writeFileToArchive for {$sourceFilepath}, size {$sourceFileSize}");

            $moreFileDataToProcess = true;

            while ((!$createState->timedOut()) && $moreFileDataToProcess) {

                if ($createState->throttleDelayInUs !== 0) {
                    usleep($createState->throttleDelayInUs);
                }

                DupArchiveUtil::tlog("Writing offset={$createState->currentFileOffset}");

                // profile ok
                $moreFileDataToProcess = self::appendGlobToArchive($createState, $archiveHandle, $sourceHandle, $sourceFilepath, $sourceFileSize);
                // end profile ok
                // profile ok
                if ($moreFileDataToProcess) {

                    DupArchiveUtil::tlog("Need to keep writing {$sourceFilepath} to archive");
                    $createState->currentFileOffset += $createState->globSize;
                    $createState->archiveOffset     = DupProSnapLibIOU::ftell($archiveHandle); //??
                } else {

                    DupArchiveUtil::tlog("Completed writing {$sourceFilepath} to archive");
                    $createState->archiveOffset     = DupProSnapLibIOU::ftell($archiveHandle);
                    $createState->currentFileIndex++;
                    $createState->currentFileOffset = 0;
                }

                // end profile ok

                if ($createState->currentFileIndex % 100 == 0) {
                    DupArchiveUtil::log("Archive Offset={$createState->archiveOffset}; Current File Index={$createState->currentFileIndex}; Current File Offset={$createState->currentFileOffset}");
                }

                // Only writing state after full group of files have been written - less reliable but more efficient
                // $createState->save();
            }

            // profile ok
            DupProSnapLibIOU::fclose($sourceHandle);
            // end profile ok
        }

        // Assumption is that this is called at the beginning of a glob header since file header already writtern
        public static function writeToFile($expandState, $archiveHandle)
        {
            /* @var $expandState DupArchiveExpandState */
            $destFilepath = self::getNewFilePath($expandState->basePath, $expandState->currentFileHeader->relativePath);

            $parentDir = dirname($destFilepath);

            $moreGlobstoProcess = true;

            DupProSnapLibIOU::dirWriteCheckOrMkdir($parentDir, 'u+rwx', true);

            if ($expandState->currentFileHeader->fileSize > 0) {

                if ($expandState->currentFileOffset > 0) {
                    $destFileHandle = DupProSnapLibIOU::fopen($destFilepath, 'r+b');

                    DupArchiveUtil::tlog('Continuing '.$destFilepath.' so seeking to '.$expandState->currentFileOffset);

                    DupProSnapLibIOU::fseek($destFileHandle, $expandState->currentFileOffset);
                } else {
                    DupArchiveUtil::tlog('Starting to write new file '.$destFilepath);
                    $destFileHandle = DupProSnapLibIOU::fopen($destFilepath, 'w+b');
                }

                DupArchiveUtil::tlog('writeToFile for '.$destFilepath.', size '.$expandState->currentFileHeader->fileSize);

                while (!$expandState->timedOut()) {

                    $moreGlobstoProcess = $expandState->currentFileOffset < $expandState->currentFileHeader->fileSize;

                    if ($moreGlobstoProcess) {
                        DupArchiveUtil::tlog('Need to keep writing to '.$destFilepath.' because current file offset='.$expandState->currentFileOffset.' and file size='.$expandState->currentFileHeader->fileSize);

                        if ($expandState->throttleDelayInUs !== 0) {
                            usleep($expandState->throttleDelayInUs);
                        }

                        DupArchiveUtil::tlog('Writing offset='.$expandState->currentFileOffset);

                        self::appendGlobToFile($expandState, $archiveHandle, $destFileHandle, $destFilepath);

                        DupArchiveUtil::tlog('After glob write');

                        $expandState->currentFileOffset = ftell($destFileHandle);
                        $expandState->archiveOffset     = DupProSnapLibIOU::ftell($archiveHandle);

                        $moreGlobstoProcess = $expandState->currentFileOffset < $expandState->currentFileHeader->fileSize;

                        if (!$moreGlobstoProcess) {

                            break;
                        }

                        if (rand(0, 1000) > 990) {
                            DupArchiveUtil::log("Archive Offset={$expandState->archiveOffset}; Current File={$destFilepath}; Current File Offset={$expandState->currentFileOffset}");
                        }
                    } else {
                        // No more globs to process
                        // Reset the expand state here to ensure it stays consistent
                        DupArchiveUtil::tlog('Writing of '.$destFilepath.' to archive is done');

                        // rsr todo record fclose error
                        @fclose($destFileHandle);
                        $destFileHandle = null;

                        self::setFileMode($expandState, $destFilepath);

                        if ($expandState->validationType == DupArchiveValidationTypes::Full) {
                            self::validateExpandedFile($expandState);
                        }

                        break;
                    }
                }

                DupArchiveUtil::tlog('Out of glob loop');

                if ($destFileHandle != null) {
                    // rsr todo record file close error
                    @fclose($destFileHandle);
                    $destFileHandle = null;
                }

                if (!$moreGlobstoProcess && $expandState->validateOnly && ($expandState->validationType == DupArchiveValidationTypes::Full)) {
                    if (!is_writable($destFilepath)) {
                        DupProSnapLibIOU::chmod($destFilepath, 'u+rw');
                    }
                    if (@unlink($destFilepath) === false) {
                        //      $expandState->addFailure(DupArchiveFailureTypes::File, $destFilepath, "Couldn't delete {$destFilepath} during validation", false);
                        // TODO: Have to know how to handle this - want to report it but don???t want to mess up validation - some non critical errors could be important to validation
                    }
                }
            } else {
                // 0 length file so just touch it
                $moreGlobstoProcess = false;

                if (file_exists($destFilepath)) {
                    @unlink($destFilepath);
                }

                if (touch($destFilepath) === false) {
                    throw new Exception("Couldn't create {$destFilepath}");
                }

                self::setFileMode($expandState, $destFilepath);
            }

            if (!$moreGlobstoProcess) {

                DupArchiveUtil::tlog('No more globs to process');

                if ((!$expandState->validateOnly) && (isset($expandState->fileRenames[$expandState->currentFileHeader->relativePath]))) {
                    $newRelativePath = $expandState->fileRenames[$expandState->currentFileHeader->relativePath];
                    $newFilepath     = self::getNewFilePath($expandState->basePath, $newRelativePath);

                    $perform_rename = true;

                    if (@file_exists($newFilepath)) {
                        if (@unlink($newFilepath) === false) {

                            $perform_rename = false;

                            $error_message = "Couldn't delete {$newFilepath} when trying to rename {$destFilepath}";

                            $expandState->addFailure(DupArchiveFailureTypes::File, $expandState->currentFileHeader->relativePath, $error_message, true);
                            DupArchiveUtil::tlog($error_message);
                        }
                    }

                    if ($perform_rename && @rename($destFilepath, $newFilepath) === false) {

                        $error_message = "Couldn't rename {$destFilepath} to {$newFilepath}";

                        $expandState->addFailure(DupArchiveFailureTypes::File, $expandState->currentFileHeader->relativePath, $error_message, true);
                        DupArchiveUtil::tlog($error_message);
                    }
                }

                $expandState->fileWriteCount++;
                $expandState->resetForFile();
            }

            return !$moreGlobstoProcess;
        }

        /**
         * 
         * @param DupArchiveExpandState $expandState
         * @param DupArchiveDirectoryHeader $directoryHeader
         * @return boolean
         */
        public static function createDirectory($expandState, $directoryHeader)
        {
            /* @var $expandState DupArchiveExpandState */
            $destDirPath = self::getNewFilePath($expandState->basePath, $directoryHeader->relativePath);

            $mode = $directoryHeader->permissions;

            if ($expandState->directoryModeOverride != -1) {
                $mode = $expandState->directoryModeOverride;
            }

            if (!DupProSnapLibIOU::dirWriteCheckOrMkdir($destDirPath, $mode, true)) {
                $error_message = "Unable to create directory $destDirPath";
                $expandState->addFailure(DupArchiveFailureTypes::Directory, $destDirPath, $error_message, false);
                DupArchiveUtil::tlog($error_message);
                return false;
            } else {
                return true;
            }
        }

        public static function setFileMode($expandState, $filePath)
        {
            $mode = 'u+rw';
            if ($expandState->fileModeOverride !== -1) {
                $mode = $expandState->fileModeOverride;
            }
            DupProSnapLibIOU::chmod($filePath, $mode);
        }

        public static function standardValidateFileEntry(&$expandState, $archiveHandle)
        {
            /* @var $expandState DupArchiveExpandState */

            $moreGlobstoProcess = $expandState->currentFileOffset < $expandState->currentFileHeader->fileSize;

            if (!$moreGlobstoProcess) {

                // Not a 'real' write but indicates that we actually did fully process a file in the archive
                $expandState->fileWriteCount++;
            } else {

                while ((!$expandState->timedOut()) && $moreGlobstoProcess) {

                    // Read in the glob header but leave the pointer at the payload
                    // profile ok
                    $globHeader = DupArchiveGlobHeader::readFromArchive($archiveHandle, false);

                    // profile ok
                    $globContents = fread($archiveHandle, $globHeader->storedSize);

                    if ($globContents === false) {
                        throw new Exception("Error reading glob from $destFilePath");
                    }

                    $hash = hash('crc32b', $globContents);

                    if ($hash != $globHeader->hash) {
                        $expandState->addFailure(DupArchiveFailureTypes::File, $expandState->currentFileHeader->relativePath, 'Hash mismatch on DupArchive file entry', true);
                        DupArchiveUtil::tlog("Glob hash mismatch during standard check of {$expandState->currentFileHeader->relativePath}");
                    } else {
                        //    DupArchiveUtil::tlog("Glob MD5 passes");
                    }

                    $expandState->currentFileOffset += $globHeader->originalSize;

                    // profile ok
                    $expandState->archiveOffset = DupProSnapLibIOU::ftell($archiveHandle);


                    $moreGlobstoProcess = $expandState->currentFileOffset < $expandState->currentFileHeader->fileSize;

                    if (!$moreGlobstoProcess) {


                        $expandState->fileWriteCount++;

                        // profile ok
                        $expandState->resetForFile();
                    }
                }
            }

            return !$moreGlobstoProcess;
        }

        private static function validateExpandedFile(&$expandState)
        {
            /* @var $expandState DupArchiveExpandState */
            $destFilepath = self::getNewFilePath($expandState->basePath, $expandState->currentFileHeader->relativePath);

            if ($expandState->currentFileHeader->hash !== '00000000000000000000000000000000') {

                $hash = hash_file('crc32b', $destFilepath);

                if ($hash !== $expandState->currentFileHeader->hash) {
                    $expandState->addFailure(DupArchiveFailureTypes::File, $destFilepath, "MD5 mismatch for {$destFilepath}", false);
                } else {
                    DupArchiveUtil::tlog('MD5 Match for '.$destFilepath);
                }
            } else {
                DupArchiveUtil::tlog('MD5 non match is 0\'s');
            }
        }

        private static function appendGlobToArchive($createState, $archiveHandle, $sourceFilehandle, $sourceFilepath, $fileSize)
        {
            DupArchiveUtil::tlog("Appending file glob to archive for file {$sourceFilepath} at file offset {$createState->currentFileOffset}");

            if ($fileSize > 0) {
                $fileSize -= $createState->currentFileOffset;

                // profile ok
                $globContents = @fread($sourceFilehandle, $createState->globSize);
                // end profile ok

                if ($globContents === false) {
                    throw new Exception("Error reading $sourceFilepath");
                }

                // profile ok
                $originalSize = strlen($globContents);
                // end profile ok

                if ($createState->isCompressed) {
                    // profile ok
                    $globContents = gzdeflate($globContents, 2);    // 2 chosen as best compromise between speed and size
                    $storeSize    = strlen($globContents);
                    // end profile ok
                } else {
                    $storeSize = $originalSize;
                }


                $globHeader = new DupArchiveGlobHeader();

                $globHeader->originalSize = $originalSize;
                $globHeader->storedSize   = $storeSize;
                $globHeader->hash         = hash('crc32b', $globContents);

                // profile ok
                $globHeader->writeToArchive($archiveHandle);
                // end profile ok
                // profile ok
                if (@fwrite($archiveHandle, $globContents) === false) {
                    // Considered fatal since we should always be able to write to the archive - plus the header has already been written (could back this out later though)
                    throw new Exception("Error writing $sourceFilepath to archive. Ensure site still hasn't run out of space.", DupArchiveExceptionCodes::Fatal);
                }
                // end profile ok

                $fileSizeRemaining = $fileSize - $createState->globSize;

                $moreFileRemaining = $fileSizeRemaining > 0;

                return $moreFileRemaining;
            } else {
                // 0 Length file
                return false;
            }
        }

        // Assumption is that archive handle points to a glob header on this call
        private static function appendGlobToFile($expandState, $archiveHandle, $destFileHandle, $destFilePath)
        {
            /* @var $expandState DupArchiveExpandState */
            DupArchiveUtil::tlog('Appending file glob to file '.$destFilePath.' at file offset '.$expandState->currentFileOffset);

            // Read in the glob header but leave the pointer at the payload
            $globHeader = DupArchiveGlobHeader::readFromArchive($archiveHandle, false);

            $globContents = @fread($archiveHandle, $globHeader->storedSize);

            if ($globContents === false) {
                throw new Exception("Error reading glob from $destFilePath");
            }

            if ($expandState->isCompressed) {
                $globContents = gzinflate($globContents);
            }

            if (@fwrite($destFileHandle, $globContents) === false) {
                throw new Exception("Error writing glob to $destFilePath");
            } else {
                DupArchiveUtil::tlog('Successfully wrote glob');
            }
        }
    }
}