#!/usr/bin/env php
<?php

require __DIR__ . '/vendor/autoload.php';

function getArgs(string &$bucket, array &$local_dirs)
{
    global $argv;
    global $argc;

    $bucket = '';
    $local_dirs = [];
    $optind = null;

    $options = getopt('d:', ['dir:'], $optind);

    foreach ($options as $i => $o) {
        if (! is_array($o)) {
            $options[$i] = [$o];
        }

        if (in_array($i, ['d', 'dir'])) {
            $local_dirs = array_merge($local_dirs, $options[$i]);
        }
    }

    if (array_key_exists($optind, $argv)) {
        $bucket = $argv[$optind];
    }

    if ($bucket == '') {
        fprintf(STDERR, 'ERROR: No bucket specified. Please provide bucket to sync with.');
        showUsage();
        exit(1);
    }

    if ($argc > ($optind + 1)) {
        fprintf(STDERR, 'ERROR: Options specified after bucket. Please specify bucket after all other options specified.');
        showUsage();
        exit(2);
    }

    if (count($local_dirs) < 1) {
        $local_dirs = [getcwd()];
    }
}

function showUsage()
{
    echo "\n";
    echo "\nUsage:";
    printf("\n    %s [-d|--dir <directory1> [-d|--dir <directory2> [...]]] <bucket>", __FILE__);
    echo "\n\nOptions:";
    echo "\n    -d, --dir";
    echo "\n        Directory to sync files from. Defaults to current dir if non specified. Non-recursive.";
    echo "\n";
}



/** main **/

$bucket = '';
$local_dirs = [];
getArgs($bucket, $local_dirs);

echo "\nFile Sync to Backblaze B2 by withaspark.com\n\n";
printf("%-20s: %s\n", 'Bucket', $bucket);
foreach ($local_dirs as $i => $dir) {
    printf("%-20s: %s\n", (($i < 1) ? 'Directories' : ''), $dir);
}

$b2 = new withaspark\BackblazeSync\BackblazeSync($bucket, $local_dirs);
$existing = $b2->getExistingFiles();
printf("%-20s: %s\n", 'Files in bucket', number_format(count($existing)));
$files = $b2->getFilesInDir();
printf("%-20s: %s\n", 'Files in directory', number_format(count($files)));

// Delete duplicate files in bucket
$b2->deleteDuplicateRemoteFiles();

// Upload new files
foreach ($files as $file) {
    $b2->upload($file);
}

printf("%-20s: %s\n", 'Files uploaded', number_format($b2->getUploadedCount()));
printf("%-20s: %s\n", 'Duplicates removed', number_format($b2->getDeletedCount()));
echo "\n";
