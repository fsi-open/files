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
Similarly to how the `FSi\Component\Files\FileManager` handles operations for stored
files, `FSi\Component\Files\Upload\FileFactory` is used to create files from various
sources.

However, you can use existing `WebFile` instances from one entity in others, just
remember to not copy the path which is persisted in the entity. If two entities
have the same filesystem and the same file path for a given field, then they will
be operating on the same file and conflicts will occur.

**Important** Instances of `UploadedWebFile` need to be persisted to the target
filesystem before you can perform any operations on it via the `FileManager` or
`UrlAdapter` (see below).

## Direct upload
When an application must deal with a large file and/or large number of files, it is
more convenient for the user to upload files separately and directly to the target
filesystem rather than through a single HTML form. Such files can be then associated
with the entity during the HTML form submission. There are two types of directly
uploaded files: `FSi\Component\Files\TemporaryWebFile` and
`FSi\Component\Files\DirectlyUploadedWebFile`. Temporary file is uploaded to any
filesystem configured for this component with any path prefix. It can be assigned
to properly configured entity field i.e., during the form submission, which results 
in their movement to the target filesystem and the path configured for this field
i.e., during committing ORM changes to the database. File of the other type is uploaded
directly to the target filesystem and path specific for some entity field. It can
also be assigned to the entity field during the form submission, but it will not be
moved anywhere because it is presumed to be already in the right place.

Direct upload is possible thanks to the
`FSi\Component\Files\DirectUpload\Controller\DirectUploadController`
which methods can be used to generate parameters (such as signed URL) for the
browser to perform the upload directly to the target filesystem such as S3, OneDrive
or similar. This controller is also responsible for handling multipart uploads
available in some of the mentioned storage services. This controller relies on
implementations of `FSi\Component\Files\DirectUpload\DirectUploadAdapter` for
different storages. As a last resort it uses
`FSi\Component\Files\Integration\FlySystem\DirectUpload\LocalAdapter` which directs
the real upload to `FSi\Component\Files\DirectUpload\Controller\LocalUploadController`.
That controller receives an uploaded file and moves it to the target filesystem which
can be local or remote.

**Important** Directly uploaded files of both types can be left without assigning
to any entity field, i.e., when user does not submit the HTML form at all. It is up
to the application how to handle such files. Example scenarios:
- (any filesystem) Remove all directly uploaded temporary files older than some time
  threshold,
- (S3) Mark file uploaded directly to the filesystem and path configured for some
  entity field with some 
  [tag](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-tagging.html)
  and remove this tag after assigning the file to the entity field. To achieve 
  these tasks, you can subscribe to Events dispatched by this component. Files marked
  with the aforementioned tag can be removed after some time threshold using the 
  [Lifecycle configuration](https://docs.aws.amazon.com/AmazonS3/latest/userguide/object-lifecycle-mgmt.html).

There are some additional requirements that must be met to use direct upload:
1. Import `@FilesBundle/Resources/config/routing/direct_upload.xml` in your routing and use routes it defines in
   browser code to handle direct uploads,
2. Optionally, if you want to use local direct uploads (not S3 or any other compatible)
   - add `psr/clock` and some of its implementation to your `composer.json` file and register `Psr\Clock\ClockInterface`
     as some of its implementations.
   - add `symfony/psr-http-message-bridge` to your `composer.json` file and register required services in your
     service container: `Symfony\Bridge\PsrHttpMessage\EventListener\PsrResponseListener`,
     `Symfony\Bridge\PsrHttpMessage\ArgumentValueResolver\PsrServerRequestResolver`,
     `Psr\Http\Message\ResponseFactoryInterface`, `Psr\Http\Message\StreamFactoryInterface`
   - import `@FilesBundle/Resources/config/routing/local_upload.xml` in your routing 
   - configure `direct_upload.local_upload_path` bundle's config option to path prefix where the above routes where
     imported,

## Url adapters

File manipulation and storage are one part of dealing with files, but as often you
will want to let your users either download or display files. To that effect you
may use implementations of `FSi\Component\Files\UrlAdapter`, that will return a
`Psr\Http\Message\UriInterface` instance for a given `WebFile`. Currently, there are
two adapters:

- `FSi\Component\Files\UrlAdapter\BaseUrlAdapter` - for local filesystems that can be
  accessed without additional credentials.
- `FSi\Component\Files\Integration\AmazonS3\UrlAdapter\S3PrivateUrlAdapter` - creates
  a presigned url to private files, which expire after a certain time. The time can be
  set through a constructor parameter and by default is 1 hour. Requires an Amazon S3
  account and SDK.

For files not available publicly, you will need to handle their download / display
manually or create a dedicated adapter based on the used filesystem.

Available url adapters are aggregated in the `FSi\Component\Files\FileUrlResolver`,
which finds an adapter matching the file's filesystem.

## Configuration

All configuration for file properties is handled through instances of
`FSi\Component\Files\FilePropertyConfiguration`. This consists of:

- `entityClass` - the class of the entity into which the `WebFile` and it's path are loaded.
- `filePropertyName` - the property of the class where the `WebFile` instance is being stored.
- `pathPropertyName` - the property of the class where the filesystem path of the file is being stored.
- `fileSystemName` - the name of the file system where the file contents are stored.
- `pathPrefix` - common prefix under which all files for this file property are stored in the filesystem.
  For example, a single directory with that name.

File operations are done through reflection, so fields corresponding to `filePropertyName`
and `pathPropertyName` do not need neither a getter nor setter, if made private. Union
types are supported for typed properties.

**Important**: If using typed properties, it is important to make sure that either a
`null` is allowed for these fields or that they will always be instantiated with existing files.

`FSi\Component\Files\FilePropertyConfigurationResolver` aggregates all configured
file properties and can fetch the relevant configuration for a class / field combination.

## Events

There are two groups of events that are dispatched by the component:

### Direct upload events

- `FSi\Component\Files\DirectUpload\Event\WebFileDirectEntityUpload` - dispatched before
  a file is directly uploaded to the target filesystem and path specific for some entity field.
- `FSi\Component\Files\DirectUpload\Event\WebFileDirectTemporaryUpload` - dispatched before
  a file is directly uploaded to some temporary filesystem and/or path prefix.

### Entity lifecycle events

- `FSi\Component\Files\Entity\Event\WebFilePersisted` - dispatched when a file is assigned to
  an entity field. Strictly speaking, this event is dispatched when the file path is effectively
  saved in entity field during ORM's flush operation.
- `FSi\Component\Files\Entity\Event\WebFileRemoved` - dispatched when the file is removed
  from the filesystem and its path is removed from entity field during ORM's flush operation.

**Important**: Dispatching event during flush operation does not necessarily mean that path
changes are saved in DB i.e., when this operation is wrapped in higher level DB transaction
with another flush operation(s).


## File - entity interaction and lifecycle

The process of storing, updating and removing files in an entity is pretty straightforward.
There is a set of dedicated classes that read the file property configuration, check if
there have been any changes to the field where the `WebFile` object is being stored
and then perform the necessary operations. These are:

- `FSi\Component\Files\Entity\FileLoader` - populates an entity with files and paths read
  from all available file property configurations for that class.
- `FSi\Component\Files\Entity\FileRemover` - schedules file removal, but the actual operation
  is performed in a separate action. This way, if any errors occur, the removal can be
  reverted and files are not lost.
- `FSi\Component\Files\Entity\FileUpdater` - will decide whether to create a new file or
  replace/remove existing one. Existing files are removed when there is a `null` in the
  `WebFile` property field. Files directly uploaded in 'temporary' mode will be effectively
  moved to the filesystem specific for the entity field. Files uploaded in 'entity' mode
  are left untouched.

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
