<?php

namespace withaspark\BackblazeSync;

/**
 * A file
 */
abstract class File
{
    /**
     * @var string
     */
    protected $url = null;

    /**
     * @var string
     */
    protected $hash = null;


    public function __construct(string $file)
    {
        $this->url = $file;
    }

    /**
     * Get the full path or URL of the file.
     *
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the file content hash.
     *
     * @return string|null
     */
    public function getHash(): ?string
    {
        return $this->hash;
    }

    /**
     * Get the filename portion of the file only. Includes extension.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return pathinfo($this->getUrl(), PATHINFO_BASENAME);
    }

    /**
     * Get the filename but cleaned to remove unwanted characters, fit length, etc.
     *
     * @return string
     */
    public function cleanFilename(): string
    {
        return substr(preg_replace('/[^a-zA-Z0-9-_\.\+\&\s\(\)#]/', '', preg_replace('/[\s]+/', '_', $this->getFilename())), 0, 200);
    }
}
