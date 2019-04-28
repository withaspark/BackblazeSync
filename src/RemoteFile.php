<?php

namespace withaspark\BackblazeSync;

use \stdClass;
use \withaspark\BackblazeSync\File;

/**
 * A file existing remotely in B2 storage.
 */
class RemoteFile extends File
{
    protected $raw = null;


    public function __construct(stdClass $raw)
    {
        $this->raw = $raw;

        $file = $this->extractFilename();
        parent::__construct($file);

        $this->hash = $this->extractHash();
    }

    /**
     * Get the hash as it is stored in B2.
     *
     * @return string|null
     */
    protected function extractHash(): ?string
    {
        return $this->getRaw('contentSha1') ?: $this->getRaw('fileInfo.sha1');
    }

    /**
     * Get the filename as it is stored in B2.
     *
     * @return string
     */
    protected function extractFilename(): string
    {
        return $this->getRaw('fileName');
    }

    /**
     * Get the fileId as it is stored in B2.
     *
     * @return string
     */
    protected function getFileId(): string
    {
        return $this->getRaw('fileId');
    }

    /**
     * Get the value from the raw B2 file info. If key is provided, will return
     * the value at key; if no key provided, will return the raw object.
     *
     * @param string|null $key (optional) A dot syntax key to fetch the value of (i.e., foo.bar)
     * @return mixed|null
     */
    public function getRaw(?string $key = null)
    {
        if (is_null($key)) {
            return $this->raw;
        }

        $o = json_decode(json_encode($this->raw), true);

        foreach (explode('.', $key) as $part) {
            if (! is_array($o) || ! array_key_exists($part, $o)) {
                return null;
            }

            $o = $o[$part];
        }

        return $o;
    }

    /**
     * Fetch info for the file from remote storage system.
     *
     * @return stdClass
     */
    public function fetchFileInfo(): stdClass
    {
        $result = shell_exec(sprintf('b2 get-file-info "%s"', $this->getFileId()));

        return json_decode($result);
    }
}
