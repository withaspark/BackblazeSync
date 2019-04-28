<?php

namespace withaspark\BackblazeSync;

/**
 * The Backblaze API handler.
 */
class BackblazeSync
{
    /**
     * @var string
     */
    protected $bucket = '';

    /**
     * @var array
     */
    protected $local_dirs = [];

    /**
     * @var LocalFile[]
     */
    protected $local_files_map = [];

    /**
     * @var RemoteFile[]
     */
    protected $remote_files_map = [];

    /**
     * @var LocalFile[]
     */
    protected $local_files = [];

    /**
     * @var RemoteFile[]
     */
    protected $remote_files = [];

    /**
     * @var int
     */
    protected $count_deleted = 0;

    /**
     * @var int
     */
    protected $count_uploaded = 0;


    public function __construct(string $bucket, array $local_dirs)
    {
        $this->bucket = $bucket;

        $this->local_dirs = $local_dirs;
    }

    public function __destruct()
    {
        foreach ($this->local_files_map as $i => $f) {
            unset($this->local_files_map[$i]);
        }

        foreach ($this->remote_files_map as $i => $f) {
            unset($this->remote_files_map[$i]);
        }
    }

    /**
     * Add a local file to internal tracking.
     *
     * @param LocalFile $file
     * @return void
     */
    protected function addLocalFile(LocalFile $file): void
    {
        $this->local_files[$file->getHash()][] = $file;

        $this->local_files_map[$file->getHash()] = $file;
    }

    /**
     * Add a remote file to internal tracking.
     *
     * @param RemoteFile $file
     * @return void
     */
    protected function addRemoteFile(RemoteFile $file): void
    {
        $this->remote_files[$file->getHash()][] = $file;

        $this->remote_files_map[$file->getHash()] = $file;
    }

    /**
     * Get all duplicate remote files. Will return an array (indexed by file
     * hash) containing an array of the duplicate files.
     *
     * @return array
     */
    public function getDuplicateRemoteFiles(): array
    {
        $duplicates = [];

        foreach ($this->remote_files as $hash => $files) {
            if (count($files) > 1) {
                foreach ($files as $file) {
                    $duplicates[$hash][] = $file;
                }
            }
        }

        return $duplicates;
    }

    /**
     * Deletes all duplicate remote files keeping only a single instance.
     *
     * @return void
     */
    public function deleteDuplicateRemoteFiles(): void
    {
        $duplicates = $this->getDuplicateRemoteFiles();

        foreach ($duplicates as $set) {
            $this->deleteDuplicateRemoteFile($set[0]);
        }
    }

    /**
     * Will delete all duplicates of the given file keeping only the first
     * one returned by the B2 API.
     *
     * @param RemoteFile $file The file to keep only one of
     * @return void
     */
    public function deleteDuplicateRemoteFile(RemoteFile $file): void
    {
        if (is_null($file->getHash())) {
            return;
        }

        $files = $this->remote_files[$file->getHash()];

        if (count($files) < 2) {
            return;
        }

        // Keep the first one
        array_shift($files);

        foreach ($files as $file) {
            $this->deleteFileVersion($file->getRaw('fileId'));
        }
    }

    /**
     * Deletes the file with given fileId.
     *
     * @param string $file_id FileId of file to delete
     * @return void
     */
    protected function deleteFileVersion(string $file_id): void
    {
        echo "\nDeleting " . $file_id;
        shell_exec(sprintf('b2 delete-file-version %s', escapeshellarg($file_id)));

        $this->count_deleted++;
    }

    /**
     * Get all local files in the specified local directory.
     *
     * @return array
     */
    public function getFilesInDir(): array
    {
        foreach ($this->local_dirs as $dir) {
            if ($handle = opendir($dir)) {
                while (($entry = readdir($handle)) !== false) {
                    $file = sprintf("%s%s%s", rtrim($dir, DIRECTORY_SEPARATOR), DIRECTORY_SEPARATOR, $entry);

                    // Skip dotfiles and directories
                    if (strpos($entry, '.') !== 0 && ! is_dir($file)) {
                        $f = new LocalFile($file);

                        $this->addLocalFile($f);
                    }
                }

                closedir($handle);
            }
        }

        return $this->local_files_map;
    }

    /**
     * Get all remote files in B2 bucket.
     *
     * @return array
     */
    public function getExistingFiles(): array
    {
        $next = '';

        do {
            $result = json_decode(shell_exec(sprintf('b2 list-file-names %s %s %d', escapeshellarg($this->bucket), escapeshellarg($next), 1000)));

            if (! is_object($result)) {
                if ($result === false) {
                    $result = 'ERROR: Failed to parse list of file response.';
                }
                fprintf(STDERR, '%s', $result);
                exit(4);
            }

            $next = (property_exists($result, 'nextFileName') && $result->nextFileName != '')
                ? $result->nextFileName
                : null;

            foreach ($result->files as $file) {
                $f = new RemoteFile($file);

                $this->addRemoteFile($f);
            }
        } while ($next != '');

        return $this->remote_files_map;
    }

    /**
     * Is the local file found in remote storage already.
     *
     * @param LocalFile $file
     * @return boolean
     */
    public function isAlreadyUploaded(LocalFile $file): bool
    {
        return array_key_exists($file->getHash(), $this->remote_files_map);
    }

    /**
     * Upload a given local file to remote storage.
     *
     * @param LocalFile $file File to upload
     * @param string|null $as (optional) Target location to save the file as
     * @param boolean $force (optional) Should upload be forced even if another instance of file is already stored; default, false
     * @return void
     */
    public function upload(LocalFile $file, ?string $as = null, bool $force = false): void
    {
        if (is_null($as)) {
            $as = $file->cleanFilename();
        }

        if ($force || ! $this->isAlreadyUploaded($file)) {
            $result = shell_exec(sprintf('b2 upload-file --sha1 %s --info sha1=%s %s %s %s', escapeshellarg($file->getHash()), escapeshellarg($file->getHash()), escapeshellarg($this->bucket), escapeshellarg($file->getUrl()), escapeshellarg($as)));

            $this->count_uploaded++;
        }
    }

    /**
     * Get the number of remote files deleted this run.
     *
     * @return integer
     */
    public function getDeletedCount(): int
    {
        return $this->count_deleted;
    }

    /**
     * Get the number of local files uploaded this run.
     *
     * @return integer
     */
    public function getUploadedCount(): int
    {
        return $this->count_uploaded;
    }
}
