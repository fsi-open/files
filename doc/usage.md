# Usage

In order to actually use this component, you will need to choose implementation
of these three things:

1. Where you will store your entities.
2. How you will define and instantiate services classes.
3. How you will define filesystems and perform the actual file operations.

`FSi\Files` has an out-of-the-box solution that utilizes `Doctrine\ORM` for entity
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

This will register all services for whichever additional bundles set you decided to use.

If you want to use the direct upload feature, you will need to import required routes:

```yaml
# config/routes.yaml
fsi_files_direct_upload:
  resource: "@FilesBundle/Resources/config/routing/direct_upload.yaml"
  prefix: /direct-upload
```

There is also an optional 


Below is a short example of bundle's configuration with comments:

```yaml
# config/packages/fsi_files.yaml
fsi_files:
    default_entity_filesystem: null # this filesystem will be used if any entity does not define
                                    # one for itself
    temporary_filesystem: null # this filesystem will be taken as a default value for the direct_upload.filesystem_name
                               # form option in the FSi\Component\Files\Integration\Symfony\Form\WebFileType form field
                               # when direct_upload.mode form option is set to 'temporary'
    direct_upload:
      signature_expiration: '+1 hour' # the time after which signed URLs for the direct upload will
                                      # expire
      local_upload_path: null # the path prefix at which FSi\Component\Files\DirectUpload\Controller\LocalUploadController
                              # is imported to the router; when left null,
                              # FSi\Component\Files\Integration\FlySystem\DirectUpload\LocalAdapter will not be used
                              # for direct uploads to filesystems other than S3
      local_upload_signature_algo: 'sha512' # the algorithm used to sign URLs to the local direct uploads
    url_adapters:
        # Here you can assign a url adapter service to a filesystem. This will
        # add it to the `FileUrlResolver` and will be used for every file in the
        # `local_filesystem` filesystem.
        local_filesystem: 'fsi.files.local_adapter'
    entities:

        Tests\Entity\FileEntity:
            filesystem: 'local_filesystem' # Name of the filesystem defined in the OneUp/FlysystemBundle configuration.
            prefix: file_entity # Will be used in fields' `prefix` option by default if the field does not have it
                                  explicitly defined. It can be omitted, but then each field needs to have it set.
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

In order to create instances of `FSi\Component\Files\UploadedWebFile`, `FSi\Component\Files\TemporaryWebFile` or
`FSi\Component\Files\DirectlyUploadedWebFile` inside Symfony form, you can use the
`FSi\Component\Files\Integration\Symfony\Form\WebFileType`.

The field can be furthered configured with these options:

- `image` - this option passes a variable to the form view and can be helpful if you would
  like to display the image next to the field. It serves an informational purpose only.
- `removable` - this will transform the field into a compound one, with an additional checkbox
  that can be checked in order to remove an existing file. The checkbox field can be configured
  through the `remove_field_options` option.
- `url_resolver` - by default, the `FSi\Component\Files\FileUrlResolver` service will be used
  to create an url to the file. You can pass a callable to this option that will generate
  the link instead.
- `resolve_url` - set this option to `false` if you do not want the file url to be created
  at all, for example, if there is no option to create a publicly available url.
- `direct_upload` - nested set of options that configures direct upload feature for the field:
    - `mode` (default `none`) - set this option to `temporary` or `entity` if you want to use the direct upload feature
      for the field. Value `temporary` will cause instance of `FSi\Component\Files\TemporaryWebFile` to be created in
      form's data. Value `entity` will cause instance of `FSi\Component\Files\DirectlyUploadedWebFile` to be created in
      form's data.
    - `filesystem_name` - when `mode` is set to `temporary` then this option takes default value from
      `fsi_files.temporary_filesystem` bundle's configuration option, otherwise default value is `null`. When `mode` is
      set to `entity` then this option's value is set to the filesystem configured for the entity and field chosen in
      `target_entity` and `target_property` options.
    - `filesystem_prefix` (default `null`) - path prefix for directly uploaded files in chosen filesystem. when `mode`
      is set to `entity` then this option's value is set to the path prefix configured for the entity and field chosen
      in `target_entity` and `target_property` options.
    - `target_entity` - FQCN of the entity that will be used to store the directly uploaded file.
    - `target_property` - name of the property in the entity that will be used to store the directly uploaded file.
    - `target` - this option's default value is calculated as an encrypted value of `target_entity` and `target_property`
      options. It is passed to form's view instead of those two options in order not to reveal their values in the HTML. 

For constraints, you will need to use either of these:

- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedWebFile`
- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\UploadedImage`

Default Symfony file constraints will not work, but the options are the same between them.

You can also constrain the length of the filename using the constraint below:

- `FSi\Component\Files\Integration\Symfony\Validator\Constraint\BasenameLength`

This will check the length of the filename *only*, not the full path created during
the file upload.

### Direct upload

`FSi\Component\Files\Integration\Symfony\Form\WebFileType` simplifies the process of directly uploading files to target
filesystems. When the form field is configured to use direct upload, the form will be rendered with an additional
hidden input field for the path of directly uploaded file. These field's name is ended with `[path]` suffix. It's up to
you to use uploader of your choice i.e. [Uppy](https://uppy.io/) to handle the whole process in the browser. The upload
process should start when the User selects a file using the file input field. Then, depending upon the upload type,
different actions from the `FSi\Component\Files\DirectUpload\Controller\DirectUploadController` class should be used
to handle different parts of the process. All of these actions described below accept POST JSON requests with some
common parameters: 

```jsonc
{
   "target": "", // value taken from the 'data-direct-target' attribute of [path] input field
   "fileSystemName": "", // value taken from the 'data-direct-filesystem' attribute of [path] input field
   "fileSystemPrefix": "", // value taken from the 'data-direct-prefix' attribute of [path] input field
}
```

#### Single upload

If the uploader decides that the file should be uploaded in one part, then it calls the
`FSi\Component\Files\DirectUpload\Controller\DirectUploadController::params` action with a JSON object with the
following additional parameters:

```jsonc
{
   // common parameters
   "filename": "somefile.pdf",
   "contentType": "application/pdf"
}
```

The action will return a JSON object with the following structure:

```jsonc
{
   "url": "/upload/filesystem/prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf", // upload URL that should be used to upload the file
   "key": "prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf", // path of the file in the target filesystem
   "headers": { // headers that should be added to the request to the upload URL
       "name": "value"
   },
   "publicUrl": "/uploaded/prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf" // public URL of the uploaded file which can be null
}
```

The uploader should use returned information to perform the upload using PUT request to the returned URL with returned
headers. After successful upload the uploader should save the `key` of the uploaded file in the `[path]` input
field. The upload could also use returned `publicUrl`, if it does not equal to `null`, to display preview of the 
uploaded file.

#### Multipart upload (not supported by LocalAdapter)

If multipart upload is required, then the uploader should use the following actions to handle the process:

- `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::createMultipart` - this action must be
  called at first to create a new multipart upload with JSON object of the same structure as for the
  `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::params`. It will return a JSON object with
  the following structure:

  ```jsonc
  {
      "uploadId": "35d5dbc8-7c3a-44f0-8161-bae7232c2a0d", // ID of the created multipart upload
      "key": "prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf", // path of the file in the target filesystem
      "publicUrl": "/uploaded/prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf" // public URL of the uploaded file which can be null
  }
  ```

- `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::listParts` - this action can be used to
  return the list of already uploaded parts for the specific multipart upload. It must be called with a JSON object
  with the following structure

  ```jsonc
  {
      // common parameters
      "uploadId": "35d5dbc8-7c3a-44f0-8161-bae7232c2a0d", // multipart upload ID returned from the createMultipart controller 
      "key": "prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf" // path of the file in the target filesystem returned by the createMultipart controller
  }
  ```

  Returned JSON will be in the following format:
    
  ```jsonc
  [
    {
      "PartNumber": 1, // index of the part inside the multipart upload
      "ETag": "..." // used to indentify tha part along with its number when completing the multipart upload
    },
    // subsequent parts
  ]
  ```
  
- `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::signPart` - this action must be called to
  prepare each part of the uploaded file for the real upload. It must be called with a JSON object with the following
  structure:

  ```jsonc
  {
      // common parameters
      "uploadId": "35d5dbc8-7c3a-44f0-8161-bae7232c2a0d", // multipart upload ID returned from the createMultipart
      "key": "prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf" // path of the file in the target filesystem returned by the createMultipart controller
      "partNumber": 1, // index of the part inside the multipart upload
  }
  ```

  The action will return a JSON object with the following structure:

  ```jsonc
  {
      "url": "/upload/filesystem/prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf?uploadId=35d5dbc8-7c3a-44f0-8161-bae7232c2a0d&part=1", // upload URL that should be used to upload the part
  }
  ```

- `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::completeMultipart` - this action must be
  called to complete multipart upload and combine all the uploaded parts into a single file. It must be called with
  a JSON object with the following structure:

  ```jsonc
  {
      // common parameters
      "parts": [
          {
            "PartNumber": 1, // index of the part inside the multipart upload
            "ETag": "..." // used to indentify tha part along with its number when completing the multipart upload
          },
          // subsequent parts
      ]
  }
  ```

  The action will return empty response with status code 200 on success. In such a case the uploader should save the
  `key` of the uploaded file in the `[path]` input field. The uploader could also use `publicUrl` returned from
  `createMultipart`, if it does not equal to `null`, to display preview of the uploaded file.

- `FSi\Component\Files\DirectUpload\Controller\DirectUploadController::abortMultipart` - this action may be called
  when the User wants to abort the multipart upload. It must be called with a JSON object with the following structure:

  ```jsonc
  {
      // common parameters
      "uploadId": "35d5dbc8-7c3a-44f0-8161-bae7232c2a0d", // multipart upload ID returned from the createMultipart
      "key": "prefix/012/345/678/90abcdef0123456789abcdef/somefile.pdf" // path of the file in the target filesystem returned by the createMultipart controller
  }
  ``` 

  The action will return empty response with status code 200 on success.


## Doctrine\ORM

Integration with `Doctrine\ORM` is very easy:

1. Make all file path properties persistent (either nullable or not depending on your requirements)
in their relative entity configuration.
2. Register the `FSi\Component\Files\Integration\Doctrine\ORM\EntityFileSubscriber`
as an event subscriber. It will listen on the relevant `Doctrine` events and fire up
corresponding `FSi\Component\Files\Entity\File*` services (remember that these needs to be
registered as well). This will be done automatically if the Symfony bundle is used.

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

If the Twig bundle is registered, two additional filters and a function are made available:

- `file_url` - returns a url as string from a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileUrlResolver` to resolve an ul adapter
  for the file.
- `file_name` - return the filename of a `WebFile`. If a `null` is passed, will return
  an empty string. Uses `FSi\Component\Files\FileManager` to read the filename.
- `is_web_file` - this function will determine whether a value is an instance of `WebFile`.

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
            // create a url to the file. However, this method is not the proper
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
for removing existing files, you do not need to re-upload the file on every submission.
An empty value will simply be ignored, and the entity will receive the existing file
on submission.

## Use case 2 - creating files through the file factory

First, you need to configure an HTTP client for the `FileFactory` to use. Then
you can create files simply by using any of its methods. Below is how you would
create one from a URL.

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
see `FileFactory::createFromPath`. In both of these cases, the factory will attempt
to read the MIME type for you.

You can also create manually from a stream, see `FileFactory::create` for the
required parameters, however, these are pretty standard and do not require an explanation.

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
