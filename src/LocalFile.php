<?php

namespace withaspark\BackblazeSync;

use \withaspark\BackblazeSync\File;

/**
 * A file existing locally on the user's machine.
 */
class LocalFile extends File
{
    public function __construct(string $file)
    {
        parent::__construct($file);

        $this->hash = $this->calcHash();
    }

    /**
     * Calculate the file content hash.
     *
     * @return string
     */
    protected function calcHash(): string
    {
        return sha1_file($this->getUrl());
    }
}
