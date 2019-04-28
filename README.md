# withaspark/BackblazeSync

## Install

1. Install B2 CLI following [these instructions](https://www.backblaze.com/b2/docs/quick_command_line.html).
2. Install package.
   ```sh
   composer require withaspark/BackblazeSync
   ```

## Usage

### CLI

```sh
path/to/backblaze_sync.php [-d|--dir <directory1> [-d|--dir <directory2> [...]]] <bucket>
```

### Code

```php
require __DIR__ . '/vendor/autoload.php';

$b2 = new withaspark\BackblazeSync\BackblazeSync($bucket, $local_dirs);

$existing = $b2->getExistingFiles(); // Get all files currently in bucket
$files = $b2->getFilesInDir(); // Get all files in local directory

$b2->deleteDuplicateRemoteFiles(); // Delete duplicate files in bucket

foreach ($files as $file) {
    $b2->upload($file); // Upload files if new
}

$b2->getUploadedCount(); // Get number of local files uploaded to B2 this run
$b2->getDeletedCount(); // Get number of remote files deleted from B2 this run
```
