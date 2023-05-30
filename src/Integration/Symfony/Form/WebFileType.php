<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form;

use Assert\Assertion;
use FSi\Component\Files\FileManager;
use FSi\Component\Files\FileUrlResolver;
use FSi\Component\Files\Integration\Symfony\Form\Listener\RemovableWebFileListener;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\FormFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\MultipleFileTransformerFactory;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\MultipleFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\RemovableFileTransformer;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_replace;
use function is_array;
use function iterator_to_array;
use function sprintf;

final class WebFileType extends AbstractType
{
    public const FILE_FIELD = 'file';
    public const REMOVE_FIELD = 'remove';

    private FileUrlResolver $urlResolver;
    private FileManager $fileManager;
    /**
     * @var array<FormFileTransformer>
     */
    private array $fileTransformers;
    private MultipleFileTransformerFactory $multipleFileTransformerFactory;
    /**
     * @var array<MultipleFileTransformer>
     */
    private ?array $multipleFileTransformers = null;

    /**
     * @param FileUrlResolver $urlResolver
     * @param FileManager $fileManager
     * @param MultipleFileTransformerFactory $multipleFileTransformerFactory
     * @param iterable<FormFileTransformer> $fileTransformers
     */
    public function __construct(
        FileUrlResolver $urlResolver,
        FileManager $fileManager,
        MultipleFileTransformerFactory $multipleFileTransformerFactory,
        iterable $fileTransformers
    ) {
        if (false === is_array($fileTransformers)) {
            $fileTransformers = iterator_to_array($fileTransformers);
        }

        Assertion::allIsInstanceOf($fileTransformers, FormFileTransformer::class);
        $this->urlResolver = $urlResolver;
        $this->fileManager = $fileManager;
        $this->fileTransformers = $fileTransformers;
        $this->multipleFileTransformerFactory = $multipleFileTransformerFactory;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array{multiple: bool, removable: bool, remove_field_options: array<string, mixed>} $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['removable']) {
            /** @var array<string, mixed> $removeFieldOptions */
            $removeFieldOptions = array_replace($options['remove_field_options'], [
                'mapped' => false,
                'required' => false
            ]);

            unset($options['remove_field_options']);

            /** @var array<string, mixed> $fileFieldOptions */
            $fileFieldOptions = array_replace($options, [
                'allow_file_upload' => true,
                'block_prefix' => 'web_file_file',
                'constraints' => [],
                'compound' => false,
                'removable' => false,
                'error_bubbling' => false,
                'error_mapping' => []
            ]);

            $builder->add(self::FILE_FIELD, self::class, $fileFieldOptions);
            $builder->add(self::REMOVE_FIELD, CheckboxType::class, $removeFieldOptions);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, new RemovableWebFileListener());
            $builder->addModelTransformer(new RemovableFileTransformer(self::FILE_FIELD));
        } elseif (true === $options['multiple']) {
            $multipleFileTransformers = $this->getMultipleFileTransformers();

            foreach ($multipleFileTransformers as $transformer) {
                $builder->addEventListener(FormEvents::PRE_SUBMIT, $transformer);
            }
        } else {
            foreach ($this->fileTransformers as $transformer) {
                $builder->addEventListener(FormEvents::PRE_SUBMIT, $transformer);
            }
        }
    }

    /**
     * @param FormView $view
     * @param FormInterface<FormInterface> $form
     * @param array{
     *     image: bool,
     *     multiple: bool,
     *     removable: bool,
     *     resolve_url: bool,
     *     url_resolver: callable|null
     * } $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();
        $removable = $options['removable'];
        $multiple = $options['multiple'];
        $resolveUrl = $options['resolve_url'];
        /** @var callable|null $urlResolver */
        $urlResolver = $options['url_resolver'];
        $view->vars = array_replace($view->vars, [
            'basename' => (false === $removable && false === $multiple) ? $this->createFileBasename($data) : null,
            'image' => $options['image'],
            'label' => false === $removable ? $view->vars['label'] : false,
            'multipart' => true,
            'removable' => $removable,
            'type' => 'file',
            'url' => (false === $removable && false === $multiple && true === $resolveUrl)
                ? $this->createFileUrl($data, $urlResolver)
                : null,
            'value' => ''
        ]);
        if (true === $multiple) {
            Assertion::keyExists($view->vars, 'full_name');
            $view->vars['full_name'] .= '[]';
            $view->vars['attr']['multiple'] = 'multiple';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'allow_file_upload' => function (Options $options): bool {
                return false === $options['removable'];
            },
            'block_prefix' => function (Options $options): ?string {
                return true === $options['removable'] ? 'web_file_parent' : null;
            },
            'compound' => function (Options $options): bool {
                return true === $options['removable'];
            },
            'data_class' => function (Options $options): ?string {
                if (true === $options['multiple']) {
                    return null;
                }

                return false === $options['removable'] ? WebFile::class : null;
            },
            'empty_data' => null,
            'error_mapping' => function (Options $options): array {
                return true === $options['removable'] ? ['.' => self::FILE_FIELD] : [];
            },
            'image' => false,
            'multiple' => false,
            'url_resolver' => null,
            'removable' => false,
            'remove_field_options' => [
                'block_prefix' => 'web_file_remove',
                'label' => 'web_file.remove',
                'translation_domain' => 'FSiFiles'
            ],
            'resolve_url' => true,
        ]);

        $resolver->setAllowedTypes('image', ['bool']);
        $resolver->setAllowedTypes('multiple', ['bool']);
        $resolver->setAllowedTypes('removable', ['bool']);
        $resolver->setAllowedTypes('remove_field_options', ['array']);
        $resolver->setAllowedTypes('resolve_url', ['bool']);
        $resolver->setAllowedTypes('url_resolver', ['callable', 'null']);

        $resolver->setNormalizer('multiple', function (Options $options, bool $value): bool {
            if (false !== $value && (true === $options['removable'] || true === $options['image'])) {
                throw new InvalidOptionsException(
                    "'multiple' option is forbidden when 'removable' or 'image' option is set"
                );
            }

            return $value;
        });
    }

    private function createFileUrl(?WebFile $file, ?callable $fileResolver): ?string
    {
        if (null === $file || true === $file instanceof UploadedWebFile) {
            return null;
        }

        if (null !== $fileResolver) {
            $url = $fileResolver($file);
            Assertion::isInstanceOf(
                $url,
                UriInterface::class,
                sprintf('File url resolver must return an instance of  "%s"', UriInterface::class)
            );
        } else {
            $url = $this->urlResolver->resolve($file);
        }

        return (string) $url;
    }

    private function createFileBasename(?WebFile $file): ?string
    {
        if (null === $file || true === $file instanceof UploadedWebFile) {
            return null;
        }

        return $this->fileManager->filename($file);
    }

    /**
     * @return array<MultipleFileTransformer>
     */
    private function getMultipleFileTransformers(): array
    {
        if (null === $this->multipleFileTransformers) {
            $this->multipleFileTransformers = array_map(
                fn(FormFileTransformer $fileTransformer): callable => $this->multipleFileTransformerFactory->create(
                    $fileTransformer
                ),
                $this->fileTransformers
            );
        }

        return $this->multipleFileTransformers;
    }
}
