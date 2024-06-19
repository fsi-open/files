# Upgrade to 1.1

## BC Break: Using doctrine/orm >= 3.0 requires manually initializing proxied entity before returning any WebFile property

If you are using `doctrine/orm` version 3.0 or newer, which implies using Symfony\Component\VarExporter\LazyGhostTrait
instead of legacy Doctrine proxies, you need to manually initialize the entity proxy before returning any
`WebFile`-typed property. This is due to the changes in the way the proxy based on lazy ghost is initialized.

This action is not always required in order to use the component, but it is necessary when you want to access a
`WebFile`-typed property of an entity that is being lazy loaded through some relation. However, it's perfectly valid and
safe to initialize the proxy before accessing any `WebFile`-typed property in any entity.

Here is an example of how to do it:

```php
use FSi\Component\Files\Integration\Doctrine\ORM\ProxyTrait;

class EntityWithFile {
    use ProxyTrait;

    private ?WebFile $file;

    ...

    public function getFile(): ?WebFile
    {
        $this->initializeProxy();

        return $this->file;
    }

    ...
}
```

Another way is not to use the mentioned trait, but some trick that will trigger the proxy loading:

```php
use FSi\Component\Files\Integration\Doctrine\ORM\ProxyTrait;

class EntityWithFile {
    private ?WebFile $file;
    private ?string $filePath;

    ...

    public function getFile(): ?WebFile
    {
        return (null !== $this->filePath) ? $this->file : null;
    }

    ...
}
```

The last possible solution is to eagerly load the entity with the file through the relation
(see https://www.doctrine-project.org/projects/doctrine-orm/en/3.2/reference/working-with-objects.html#by-eager-loading).

```php
