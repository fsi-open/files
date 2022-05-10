# FSi/Files

## About Files

A component for handling file upload and storage. It streamlines the process of
uploading a file from different sources (API call, HTML form, local filesystem)
to a storage endpoint - SQL/NoSQL database, remote or local filesystem. This is
done through entity objects, that contain two fields per a `single persisted file` -
an object with the file itself and a path that is used for storing it. The entity
does not need to be tied to an ORM/ODM, but it can be used in tandem with them.

## Key concepts and interfaces

### WebFile
The most basic interface of the package is `FSi\Component\Files\WebFile` - a
representation of a single persisted file tied to an entity class. It contains two
things - the name of the used filesystem and it's path relative to that filesystem.
This is the bare minimum required to perform operations on the file through the
`FSi\Component\Files\FileManager`, which is an intermediary between the file itself
and it's filesystem. This makes the file interface as generic as possible, without
tying it to any single implementation.

### Upload
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

### Url adapters

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
        return $this->file;
    }

    public function setFile(?WebFile $file): void
    {
        $this->file = $file;
    }

    public function getImage(): ?WebFile
    {
        return $this->image;
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

## Usage

In order to actually use this component, you will need to choose implementation
of these three things:

1. Where you will store your entities.
2. How you will define and instantiate services classes.
3. How you will define filesystems and perform the actual file operations.

`FSi\Files` has an out of the box solution that utilizes `Doctrine\ORM` for entity
storage, `Symfony` for dependency injection and `League\Flysystem` (through
`1up-lab/OneupFlysystemBundle`) for file operations, but you are free to create any
implementation for each of these components if you want to. Below you can find
information on how to use the available solution:

### Symfony

This integration is the binding agent for the provided solution. You can register
the `FilesBundle` in `config/bundles.php`:

```php
return [
    // Doctrine and Flysystem bundles are required if you do not have an alternatives
    // for them.
    // Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class => ['all' => true],
    // Oneup\FlysystemBundle\OneupFlysystemBundle::class => ['all' => true],

    // You can register Twig and there will be dedicated filters unlocked for you
    // to use in themplates.
    // Symfony\Bundle\TwigBundle\TwigBundle::class => ['all' => true]

    FSi\Component\Files\Integration\Symfony\FilesBundle::class => ['all' => true]
    // ... other bundles
];
```

This will register all services for whichever additonal bundles set you decided to use.

Below is an example of entity / url adapter configuration:

```yaml
# config/packages/fsi_files.yaml
fsi_files:
    url_adapters:
        # Here you can assign a url adapter service to a filesystem. This will
        # add it to the `FileUrlResolver` and will be used for every file in the
        # `local_filesystem` filesystem.
        local_filesystem: 'fsi.files.local_adapter'
    entities:

        Tests\Entity\FileEntity:
            filesystem: 'local_filesystem' # name of the filesystem defined in the OneUp/FlysystemBundle configuration
            prefix: file_entity # will be used in fields' `prefix` option by default if not explicitly defined
            fields:
                # Full configuration
                - { name: file, prefix: file_entity_file, pathField: filePath }
                # Minimal configuration equal to { name: image, prefix: file_entity, pathField: imagePath }
                - [image]
                - # configuration for other fields

        # Configuration for other entities

# config/services.yaml

services:

    fsi.files.local_adapter:
        class: FSi\Component\Files\UrlAdapter\BaseUrlAdapter
        arguments:
            $uriFactory: '@Psr\Http\Message\UriFactoryInterface'
            $baseUrl: '/files' # the publicly available directory where your files are stored

```

#### Form

In order to create instances of `FSi\Component\Files\UploadedWebFile` inside of Symfony
forms, you can use the `FSi\Component\Files\Integration\Symfony\Form\WebFileType`.
It utilizes a collection of services implementing `Symfony\Component\Form\FormEvent\FormFileTransformer`,
that can create them from various sources.

The field can be furthered configured with these options:

- `image` - this option passes a variable to the form view and can be helpful if you would
  like to display the image next to the field. It servers an informational purpose only.
- `removable` - this will transform the field into a compound one, with an additional checkbox
  that can be checked in order to remove an existing file. The checkbox field can be configured
  through the `remove_field_options` option.
- `url_resolver` - by default the `FSi\Component\Files\FileUrlResolver` service will be used
  to create an url to the file. You can pass a callable to this option that will generate
  the link instead.
- `resolve_url` - set this option to `false` if you do not want the file url to be created
  at all, for example if there is no option to create a publicy available url.

For constraints, you will need to use either of these:

- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFile`
- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImage`

Default Symfony file constraints will not work, but the options are the same between them.

You can also constrain the length of the filename using the constraint below:

- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\BasenameLength`

This will check the length of the filename *only*, not the full path created during
the file upload.

### Doctrine\ORM

Integration with `Doctrine\ORM` is very easy:

1. Make all file path properties persistent (either nullable or not depending on your requirements)
in their relative entity configuration.
2. Register the `FSi\Component\Files\Integration\Doctrine\ORM\EntityFileSubscriber`
as an event subscriber. It will listen on the relevant `Doctrine` events and fire up
corresponding `FSi\Component\Files\Entity\File*` services (remember these need to be
registered as well). This will be done automatically if Symfony bundle is used.

Embeddables are supported. Just create a configuration for the embeddable class
the way you would for a standard entity. There is no need to duplicate the configuration
if the embeddable is used in more than one field / entity.

### OneUp/FlysystemBundle

An example configuration for a local filesystem:

```yaml
# config/packages/oneup_flysystem.yaml
oneup_flysystem:
    adapters:
        local_adapter:
            local:
                location: 'the directory where the files are to be stored'
    filesystems:
        local_filesystem:
            mount: local_filesystem
            adapter: local_adapter
            alias: League\Flysystem\Filesystem
```

For more detailed explanation on how to configure filesystems and adapters, refer
to the `1up-lab/OneupFlysystemBundle` itself.


### Twig

If Twig bundle is registered, two additional filters are made available:

- `file_url` - returns a url as string from a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileUrlResolver` to resolve an ul adapter
  for the file.
- `file_name` - return the filename of a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileManager` to read the filename.

Do not use instances of `UploadedWebFile` here, since they are not yet saved to any
filesystem and the underlying services will throw an exception.

### Use case 1 - file upload through a form

Using our example entity defined earlier, here is how you would go about uploading
a file through form to an entity object. First, you will need a form:

```php
declare(strict_types=1);

namespace Tests\Form;

use FSi\Component\Files\Integration\Symfony\Form\WebFileType;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImage;
use FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Tests\Entity\TestEntity;

final class TestType extends AbstractType
{
    private UriFactoryInterface $uriFactory;

    // constructor

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array<string, mixed> $options
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', WebFileType::class, [
            'label' => 'Standard file',
            'constraints' => [new UploadedWebFile()],
            'removable' => true,
            'required' => false
        ]);

        $builder->add('image', WebFileType::class, [
            'label' => 'Image file',
            'constraints' => [new UploadedImage()],
            'image' => true,
            'required' => false,
            // An example on how to use the 'url_resolver' option to manually
            // create a url to the file. However this method is not the proper
            // way and is instructional only.
            'url_resolver' => fn(WebFile $file): UriInterface
                => $this->uriFactory->createUri($file->getPath())
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('data_class', TestEntity::class);
        $resolver->setDefault('method', Request::METHOD_POST);
    }
}
```

Now the moving bits part:

```php
declare(strict_types=1);

namespace Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Entity\FileEntity;
use Tests\Form\TestForm;
use Twig\Environment;

final class TestController
{
    private EntityManagerInterface $objectManager;
    private FormFactoryInterface $formFactory;
    private Environment $twig;

    // constructor

    public function __invoke(Request $request): Response
    {
        $entity = new FileEntity();

        null === $entity->getFile(); // true
        null === $entity->getFilePath(); // true
        null === $entity->getImage(); // true
        null === $entity->getImagePath(); // true

        $form = $this->formFactory->create(TestEntityType::class, $entity);
        // Let us assume that the `file` field has been populated with a file,
        // but the `image` field was not.
        $form->handle($request);

        null === $entity->getFile(); // false, an instanceof `UploadedWebFile` is present here
        null === $entity->getFilePath(); // true, since the file was not yet persisted to the filesystem
        null === $entity->getImage(); // true
        null === $entity->getImagePath(); // true

        if (true === $form->isSubmitted() && $form->isValid()) {
            $this->objectManager->persist($entity); // For a new entity, the files are created here
            $this->objectManager->flush(); // For existing entities, files are created/updated on `preFlush`

            null === $entity->getFile(); // false, since now there is a `WebFile` instance here, that has been created from the previous `UploadedWebFile`
            null === $entity->getFilePath(); // false, since it has been populated with the path of the persisted file
            null === $entity->getImage(); // true
            null === $entity->getImagePath(); // true
        }

        return new Response(
            $this->twig->render('form.html.twig', ['form' => $form->createView()]
        );
    }
}
```

And that is basically it. Now whenever this entity is fetched from the database,
it's `file` and `filePath` fields will be populated with the persisted data.

You do not need to use the `TestEntity` object directly in the form. It is perfectly
fine to use a DTO object or a simple array in the form and pass the uploaded file
to the entity. As long as you do it before a flush, it will be saved.

**Important** Unless you use the `removable` option and then check the checkbox
for removing existing files, you do not need to re-upload file on every submission.
An empty value will simply be ignored and the entity will receive the existing file
on submission.

### Use case 2 - creating files through the file factory

First, you need to configure an HTTP client for the `FileFactory` to use. Then
you can create files simply by using any of it's methods. Below is how you would
create one from a url.

```php
declare(strict_types=1);

namespace Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Http\Message\UriFactoryInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Entity\FileEntity;

final class TestController
{
    private FileFactory $fileFactory;
    private EntityManagerInterface $objectManager;
    private UriFactoryInterface $uriFactory;

    // constructor

    public function __invoke(): Response
    {
        $file = $fileFactory->createFromUri(
            $this->uriFactory->create('https://some.domain.com/some_file.txt'),
            'changed_name.txt' // you can overwrite the name of the created file if you want
        );

        $entity = new TestEntity();
        $entity->setFile($file);

        $this->objectManager->persist($entity);
        $this->objectManager->flush();

        // return whatever response you like
    }
}
```

And that it is. Creating a file from a local path is even more straightforward,
see `FileFactory::createFromPath`. In both of these cases the factory will attempt
to read the MIME type for you.

You can also create manually from a stream, see `FileFactory::create` for the
required parameters, however these are pretty standard and do not require an explanation.

## Use case 3 - removing an existing file

```php
declare(strict_types=1);

namespace Tests\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Tests\Entity\FileEntity;

final class TestController
{
    private EntityManagerInterface $objectManager;

    // constructor

    public function __invoke(): Response
    {
        /** @var FileEntity $entity */
        $entity = $this->objectManager->getRepository(FileEntity::class)->find(1);
        $entity->setFile(null);

        // The file is actually removed during the `postFlush` event in case of any
        // errors occurring during the flush.
        $this->objectManager->flush();

        // return whatever response you like
    }
}
```
