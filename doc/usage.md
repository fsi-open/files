# Usage

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

## Symfony

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

## Form

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

## Doctrine\ORM

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

## OneUp/FlysystemBundle

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


## Twig

If Twig bundle is registered, two additional filters are made available:

- `file_url` - returns a url as string from a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileUrlResolver` to resolve an ul adapter
  for the file.
- `file_name` - return the filename of a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileManager` to read the filename.

Do not use instances of `UploadedWebFile` here, since they are not yet saved to any
filesystem and the underlying services will throw an exception.

## Use case 1 - file upload through a form

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

## Use case 2 - creating files through the file factory

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

## Extending FileManager

Sometimes you may wish to change how some aspects of file management are handled.
For this purpose you may use the `FSi\Component\Files\FileManagerConfigurator`
