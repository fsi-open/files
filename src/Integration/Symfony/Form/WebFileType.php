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
use FSi\Component\Files\FilePropertyConfigurationResolver;
use FSi\Component\Files\FileUrlResolver;
use FSi\Component\Files\Integration\Symfony\Form\Listener\RemovableWebFileListener;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\CompoundWebFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Listener\DirectlyUploadedWebFileListener;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\FormFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\MultipleFileTransformerFactory;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\MultipleFileTransformer;
use FSi\Component\Files\DirectUpload\DirectUploadTargetEncryptor;
use FSi\Component\Files\Upload\FileFactory;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_replace;
use function implode;
use function is_array;
use function iterator_to_array;
use function reset;
use function sprintf;

final class WebFileType extends AbstractType
{
    public const FILE_FIELD = 'file';
    public const PATH_FIELD = 'path';
    public const REMOVE_FIELD = 'remove';

    private FileUrlResolver $urlResolver;
    private FileManager $fileManager;
    private FileFactory $fileFactory;
    private MultipleFileTransformerFactory $multipleFileTransformerFactory;
    /**
     * @var array<FormFileTransformer>
     */
    private array $fileTransformers;
    private FilePropertyConfigurationResolver $filePropertyConfigurationResolver;
    private DirectUploadTargetEncryptor $directUploadTargetEncryptor;
    private ?string $temporaryFileSystemName;
    private ?string $temporaryFileSystemPrefix;
    /**
     * @var array<MultipleFileTransformer>
     */
    private ?array $multipleFileTransformers = null;

    /**
     * @param FileUrlResolver $urlResolver
     * @param FileManager $fileManager
     * @param FileFactory $fileFactory
     * @param MultipleFileTransformerFactory $multipleFileTransformerFactory
     * @param FilePropertyConfigurationResolver $filePropertyConfigurationResolver
     * @param DirectUploadTargetEncryptor $directUploadTargetEncryptor
     * @param iterable<FormFileTransformer> $fileTransformers
     * @param string|null $temporaryFileSystemName
     */
    public function __construct(
        FileUrlResolver $urlResolver,
        FileManager $fileManager,
        FileFactory $fileFactory,
        MultipleFileTransformerFactory $multipleFileTransformerFactory,
        FilePropertyConfigurationResolver $filePropertyConfigurationResolver,
        DirectUploadTargetEncryptor $directUploadTargetEncryptor,
        iterable $fileTransformers,
        ?string $temporaryFileSystemName = null,
        ?string $temporaryFileSystemPrefix = null
    ) {
        if (false === is_array($fileTransformers)) {
            $fileTransformers = iterator_to_array($fileTransformers);
        }

        Assertion::allIsInstanceOf($fileTransformers, FormFileTransformer::class);
        $this->urlResolver = $urlResolver;
        $this->fileManager = $fileManager;
        $this->fileFactory = $fileFactory;
        $this->multipleFileTransformerFactory = $multipleFileTransformerFactory;
        $this->filePropertyConfigurationResolver = $filePropertyConfigurationResolver;
        $this->directUploadTargetEncryptor = $directUploadTargetEncryptor;
        $this->fileTransformers = $fileTransformers;
        $this->temporaryFileSystemName = $temporaryFileSystemName;
        $this->temporaryFileSystemPrefix = $temporaryFileSystemPrefix;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array{
     *     compound: bool,
     *     multiple: bool,
     *     removable: bool,
     *     remove_field_options: array<string, mixed>,
     *     direct_upload: array{
     *         mode: 'none'|'temporary'|'entity',
     *         path_field_options: array<string, mixed>,
     *         filesystem_name: string|null,
     *         filesystem_prefix: string|null,
     *         target_entity: string|null,
     *         target_property: string|null,
     *     },
     * } $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (true === $options['removable'] || 'none' !== $options['direct_upload']['mode']) {
            /** @var array<string, mixed> $fileFieldOptions */
            $fileFieldOptions = array_replace($options, [
                'allow_file_upload' => true,
                'block_prefix' => 'web_file_file',
                'constraints' => [],
                'compound' => false,
                'removable' => false,
                'direct_upload' => ['mode' => 'none'],
                'error_bubbling' => false,
                'error_mapping' => [],
                'required' => false,
            ]);

            $builder->add(self::FILE_FIELD, self::class, $fileFieldOptions);

            if (true === $options['removable']) {
                /** @var array<string, mixed> $removeFieldOptions */
                $removeFieldOptions = array_replace($options['remove_field_options'], [
                    'mapped' => false,
                    'required' => false
                ]);

                unset($options['remove_field_options']);

                $builder->add(self::REMOVE_FIELD, CheckboxType::class, $removeFieldOptions);

                $builder->addEventListener(FormEvents::PRE_SUBMIT, new RemovableWebFileListener());
            }
            if ('none' !== $options['direct_upload']['mode']) {
                /** @var array<string, mixed> $pathFieldOptions */
                $pathFieldOptions = array_replace($options['direct_upload']['path_field_options'], [
                    'required' => false
                ]);

                unset($options['direct_upload']['path_field_options']);

                $builder->add(self::PATH_FIELD, HiddenType::class, $pathFieldOptions);
            }
            if (true === $options['compound']) {
                if (
                    'none' !== $options['direct_upload']['mode']
                    && null !== $options['direct_upload']['filesystem_name']
                ) {
                    $builder->addEventListener(FormEvents::PRE_SUBMIT, new DirectlyUploadedWebFileListener(
                        $this->fileManager,
                        $this->fileFactory,
                        'temporary' === $options['direct_upload']['mode'],
                        $options['direct_upload']['filesystem_name']
                    ));
                }
                $builder->addModelTransformer(new CompoundWebFileTransformer(self::FILE_FIELD));
            }
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
     *     compound: bool,
     *     image: bool,
     *     multiple: bool,
     *     removable: bool,
     *     resolve_url: bool,
     *     url_resolver: callable|null,
     *     direct_upload: array{
     *         mode: 'none'|'temporary'|'entity',
     *         path_field_options: array<string, mixed>,
     *         filesystem_name: string|null,
     *         filesystem_prefix: string|null,
     *         target: string|null,
     *         target_entity: string|null,
     *         target_property: string|null,
     *     }
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
            'basename' => ('none' === $options['direct_upload']['mode'] && true === $data instanceof WebFile)
                ? $this->createFileBasename($data)
                : null,
            'image' => $options['image'],
            'label' => (false === $options['compound']) ? $view->vars['label'] : false,
            'multipart' => true,
            'removable' => $removable,
            'type' => 'file',
            'url' => (false === $options['compound'] && true === $resolveUrl && true === $data instanceof WebFile)
                ? $this->createFileUrl($data, $urlResolver)
                : null,
            'value' => '',
        ]);
        if ('temporary' === $options['direct_upload']['mode']) {
            $view[self::PATH_FIELD]->vars['attr']['data-direct-filesystem']
                = $options['direct_upload']['filesystem_name'];
            $view[self::PATH_FIELD]->vars['attr']['data-direct-prefix']
                = $options['direct_upload']['filesystem_prefix'];
        } elseif ('entity' === $options['direct_upload']['mode']) {
            $view[self::PATH_FIELD]->vars['attr']['data-direct-target'] = $options['direct_upload']['target'];
        }
        if (true === $multiple) {
            Assertion::keyExists($view->vars, 'full_name');
            $view->vars['full_name'] .= '[]';
            $view->vars['attr']['multiple'] = 'multiple';
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $directUploadResolver = function (OptionsResolver $directUploadResolver): void {
            $directUploadResolver->setDefaults([
                'mode' => 'none',
                'path_field_options' => [
                    'block_prefix' => 'web_file_path',
                    'label' => false,
                    'translation_domain' => 'FSiFiles'
                ],
                'filesystem_name' => null,
                'filesystem_prefix' => null,
                'target_entity' => null,
                'target_property' => null,
                'target' => null,
            ]);
            $directUploadResolver->setRequired(['mode', 'path_field_options', 'filesystem_name', 'filesystem_prefix']);
            $directUploadResolver->setAllowedValues('mode', ['none', 'temporary', 'entity']);
            $directUploadResolver->setAllowedTypes('path_field_options', ['array']);
            $directUploadResolver->setAllowedTypes('filesystem_name', ['null', 'string']);
            $directUploadResolver->setAllowedTypes('filesystem_prefix', ['null', 'string']);
            $directUploadResolver->setAllowedTypes('target_entity', ['null', 'string']);
            $directUploadResolver->setAllowedTypes('target_property', ['null', 'string']);
            $directUploadResolver->setAllowedTypes('target', ['null', 'string']);
            $directUploadResolver->setDefault('filesystem_name', function (Options $options): ?string {
                if ('temporary' === $options['mode']) {
                    return $this->temporaryFileSystemName;
                }

                if ('entity' !== $options['mode']) {
                    return null;
                }

                if (null === $options['target_entity']) {
                    throw new InvalidOptionsException(
                        'Missing required option "target_entity" when direct_upload.mode option is set to "entity"'
                    );
                }
                if (null === $options['target_property']) {
                    throw new InvalidOptionsException(
                        'Missing required option "target_property" when direct_upload.mode option is set to "entity"'
                    );
                }

                $filePropertyConfiguration = $this->filePropertyConfigurationResolver->resolveFileProperty(
                    $options['target_entity'],
                    $options['target_property']
                );

                return $filePropertyConfiguration->getFileSystemName();
            });
            $directUploadResolver->setDefault('filesystem_prefix', function (Options $options): ?string {
                if ('temporary' === $options['mode']) {
                    return $this->temporaryFileSystemPrefix;
                }

                if ('entity' !== $options['mode']) {
                    return null;
                }

                if (null === $options['target_entity']) {
                    throw new InvalidOptionsException(
                        'Missing required option "target_entity" when direct_upload.mode option is set to "entity"'
                    );
                }
                if (null === $options['target_property']) {
                    throw new InvalidOptionsException(
                        'Missing required option "target_property" when direct_upload.mode option is set to "entity"'
                    );
                }

                $filePropertyConfiguration = $this->filePropertyConfigurationResolver->resolveFileProperty(
                    $options['target_entity'],
                    $options['target_property']
                );

                return $filePropertyConfiguration->getPathPrefix();
            });
            $directUploadResolver->setDefault('target', function (Options $options): ?string {
                if ('entity' !== $options['mode']) {
                    return null;
                }

                return $this->directUploadTargetEncryptor->encrypt(
                    $options['target_entity'],
                    $options['target_property']
                );
            });
        };

        $resolver->setDefaults([
            'allow_file_upload' => function (Options $options): bool {
                return false === $options['compound'];
            },
            'block_prefix' => function (Options $options): ?string {
                return true === $options['compound'] ? 'web_file_parent' : null;
            },
            'compound' => function (Options $options): bool {
                return true === $options['removable'] || 'none' !== $options['direct_upload']['mode'];
            },
            'data_class' => function (Options $options): ?string {
                if (true === $options['multiple']) {
                    return null;
                }

                return (false === $options['multiple'] && false === $options['compound']) ? WebFile::class : null;
            },
            'empty_data' => null,
            'error_mapping' => function (Options $options): array {
                return (true === $options['compound']) ? ['.' => self::FILE_FIELD] : [];
            },
            'error_bubbling' => false,
            'image' => false,
            'multiple' => false,
            'url_resolver' => null,
            'removable' => false,
            'remove_field_options' => [
                'block_prefix' => 'web_file_remove',
                'label' => 'web_file.remove',
                'translation_domain' => 'FSiFiles'
            ],
            'direct_upload' => $directUploadResolver,
            'resolve_url' => true,
        ]);

        $resolver->setAllowedTypes('image', ['bool']);
        $resolver->setAllowedTypes('multiple', ['bool']);
        $resolver->setAllowedTypes('removable', ['bool']);
        $resolver->setAllowedTypes('remove_field_options', ['array']);
        $resolver->setAllowedTypes('resolve_url', ['bool']);
        $resolver->setAllowedTypes('url_resolver', ['callable', 'null']);

        $resolver->setNormalizer('multiple', function (Options $options, bool $value): bool {
            if (false === $value) {
                return false;
            }

            if (
                true === $options['removable']
                || true === $options['image']
            ) {
                throw new InvalidOptionsException(
                    "'multiple' option is forbidden when 'removable' or 'image' option is set"
                );
            }

            if ('none' !== $options['direct_upload']['mode']) {
                throw new InvalidOptionsException(
                    "'multiple' option is forbidden when direct upload mode is other than 'none'"
                );
            }

            return true;
        });

        $resolver->setNormalizer(
            'direct_upload',
            function (Options $options, array $value): array {
                if ('none' === $value['mode']) {
                    $notAllowedOptions = ['filesystem_name', 'filesystem_prefix', 'target_entity', 'target_property'];
                    $passedNotAllowedOptions = [];
                    foreach ($notAllowedOptions as $option) {
                        if (null !== $value[$option]) {
                            $passedNotAllowedOptions[] = $option;
                        }
                    }
                    if (0 !== count($passedNotAllowedOptions)) {
                        throw new InvalidOptionsException(
                            sprintf(
                                'Options "%s" are not allowed when "direct_upload[mode]" is set to "none"',
                                implode('", "', $passedNotAllowedOptions)
                            )
                        );
                    }
                } elseif ('temporary' === $value['mode'] && null === $value['filesystem_name']) {
                    throw new InvalidOptionsException(
                        'Missing required option "filesystem_name" and no "temporary_filesystem" has been defined '
                            . 'in FilesBundle\'s configuration'
                    );
                }

                return $value;
            }
        );
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
