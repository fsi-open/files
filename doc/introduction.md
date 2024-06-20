# Key concepts and interfaces

## WebFile
The most basic interface of the package is `FSi\Component\Files\WebFile` - a
representation of a single persisted file tied to an entity class. It contains two
things - the name of the used filesystem and it's path relative to that filesystem.
This is the bare minimum required to perform operations on the file through the
`FSi\Component\Files\FileManager`, which is an intermediary between the file itself
and it's filesystem. This makes the file interface as generic as possible, without
tying it to any single implementation.

## Upload
When uploading a new file, you will be using `FSi\Component\Files\UploadedWebFile`.
It contains additional data needed to save the contents on the target filesystem.
Similairly to how the `FSi\Component\Files\FileManager` handles operations for stored
files, `FSi\Component\Files\Upload\FileFactory` is used to create files from various
sources.

However, you can use existing `WebFile` instances from one entity in others, just
remember to not copy the path that is persisted in the entity. If two entities
have the same filesystem and the same file path for a given field, then they will
be operating on the same file and conflicts will occur.

**Important** Instances of `UploadedWebFile` need to be persisted to the target
filesystem before you can perform any operations on it via the `FileManager` or
`UrlAdapter` (see below).

## Url adapters

File manipulation and storage is one part of dealing with files, but as often you
will want to let your users either download or display files. To that effect you
may use implementations of `FSi\Component\Files\UrlAdapter`, that will return a
`Psr\Http\Message\UriInterface` instance for a given `WebFile`. Currently there are
two adapters:

- `FSi\Component\Files\UrlAdapter\BaseUrlAdapter` - for local filesystems that  can be
  accessed without additional credentials.
- `FSi\Component\Files\Integration\AmazonS3\UrlAdapter\S3PrivateUrlAdapter` - creates
  a presigned url to private files, which expire after a certain time. The time can be
  set through a constructor parameter and by default is 1 hour. Requires an Amazon S3
  account and SDK.

For files not avaible publicly you will need to handle their download / display
manually or create a dedicated adapter based on the used filesystem.

Available url adapters are aggregated in the `FSi\Component\Files\FileUrlResolver`,
which finds an adapter matching the file's filesystem.

## Configuration

All configuration for file properties is handled through instances of
`FSi\Component\Files\FilePropertyConfiguration`. This consists of:

- `entityClass` - the class of the entity into which the `WebFile` and file path are loaded.
- `filePropertyName` - the property of the class where the `WebFile` instance is being stored.
- `pathPropertyName` - the property of the class where the filesystem path of the file is being stored.
- `fileSystemName` - the name of the file system where the file contents are stored.
- `pathPrefix` - common prefix under which all files for this file property are stored in the filesystem.
  For example, a single directory with that name.

File operations are done through reflection, so fields corresponding to `filePropertyName`
and `pathPropertyName` do not need neither a getter nor setter, if made private. Union
types are supported for typed properties.

**Important**: If using typed properties, it is important to make sure that either a
`null` is allowed for these fields or that they will always be instantiated with exisiting files.

`FSi\Component\Files\FilePropertyConfigurationResolver` aggregates all configured
file properties and can fetch the relevant configuration for a class / field combination.

## File - entity interaction and lifecycle

The process of storing, updating and removing files in an entity is pretty straightforward.
There is a set of dedicated classes that read the file property configuration, check if
there have been any changes to the field where the `WebFile` object is being stored
and then perform necessary operations if need be. These are:

- `FSi\Component\Files\Entity\FileLoader` - populates an entity with files and paths read
  from all available file property configurations for that class.
- `FSi\Component\Files\Entity\FileRemover` - schedules file removal, but the actual operation
  is performed in a separate action. This way if any errors occur the removal can be
  reverted and files are not lost.
- `FSi\Component\Files\Entity\FileUpdater` - will decide whether to create a new file or
  replace/remove existing one. Existing files are removed when there is a `null` in the
  `WebFile` property field.

All of these are internal services and should not be used directly in your project.

### Example entity with configuration

Here is how an entity with a single persisted file fields would look like:

```php
declare(strict_types=1);

namespace Tests\Entity;

use FSi\Component\Files\WebFile;

class FileEntity
{
    private ?WebFile $file;
    private ?string $filePath; // you cannot use anything else than string|null for the path
    private ?WebFile $image;
    private ?string $imagePath;

    public function getFile(): ?WebFile
    {
        return (null !== $this->filePath) ? $this->file : null; // this is a trick to trigger the proxy loading
    }

    public function setFile(?WebFile $file): void
    {
        $this->file = $file;
    }

    public function getImage(): ?WebFile
    {
        return (null !== $this->imagePath) ? $this->image : null; // this is a trick to trigger the proxy loading
    }

    public function setImage(?WebFile $image): void
    {
        $this->image = $image;
    }

    // Adding getters or setters for the path field properties is not advised,
    // since these are modified by the component. Trying to set it manually
    // will lead to errors when loading the entity.
    // However, static analysis tools might raise an error if the field is seamingly
    // unused, so you may need to explicitly ignore it or add a getter just so it
    // is satisfied.
}
```

And here is how you would create a configuration for it:

```php
declare(strict_types=1);

namespace Tests;

use FSi\Component\Files\FilePropertyConfiguration;
use Tests\Entity\FileEntity;

$fileFieldConfiguration = new FilePropertyConfiguration(
    FileEntity::class,
    'file',
    'local_filesystem',
    'filePath',
    'file_entity'
);
$imageFieldConfiguration = new FilePropertyConfiguration(
    FileEntity::class,
    'image',
    'local_filesystem',
    'imagePath',
    'file_entity'
);

$resolver = new FilePropertyConfiguration([$fileFieldConfiguration, $imageFieldConfiguration]);
$resolver->resolveFileProperty(FileEntity::class, 'file') === $fileFieldConfiguration // true;
$resolver->resolveFileProperty(FileEntity::class, 'image') === $imageFieldConfiguration // true;

```
