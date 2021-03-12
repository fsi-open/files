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
use FSi\Component\Files\Integration\Symfony\Form\Transformer\RemovableFileTransformer;
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

use function array_replace;

final class WebFileType extends AbstractType
{
    public const FILE_FIELD = 'file';
    public const REMOVE_FIELD = 'remove';

    /**
     * @var FileUrlResolver
     */
    private $urlResolver;

    /**
     * @var FileManager
     */
    private $fileManager;

    /**
     * @var FormFileTransformer[]
     */
    private $fileTransformers;

    /**
     * @param FileUrlResolver $urlResolver
     * @param FileManager $fileManager
     * @param iterable<FormFileTransformer> $fileTransformers
     */
    public function __construct(FileUrlResolver $urlResolver, FileManager $fileManager, iterable $fileTransformers)
    {
        if (false === is_array($fileTransformers)) {
            $fileTransformers = iterator_to_array($fileTransformers);
        }

        Assertion::allIsInstanceOf($fileTransformers, FormFileTransformer::class);
        $this->urlResolver = $urlResolver;
        $this->fileManager = $fileManager;
        $this->fileTransformers = $fileTransformers;
    }

    /**
     * @param FormBuilderInterface<FormBuilderInterface> $builder
     * @param array{removable: bool, remove_field_options: array} $options
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

            $builder->add(self::FILE_FIELD, WebFileType::class, $fileFieldOptions);
            $builder->add(self::REMOVE_FIELD, CheckboxType::class, $removeFieldOptions);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, new RemovableWebFileListener());
            $builder->addModelTransformer(new RemovableFileTransformer(self::FILE_FIELD));
        } else {
            foreach ($this->fileTransformers as $transformer) {
                $builder->addEventListener(FormEvents::PRE_SUBMIT, $transformer);
            }
        }
    }

    /**
     * @param FormView $view
     * @param FormInterface<FormInterface> $form
     * @param array{image: bool, removable: bool, resolve_url: bool} $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options): void
    {
        $data = $form->getData();
        $removable = $options['removable'];
        $resolveUrl = $options['resolve_url'];
        $view->vars = array_replace($view->vars, [
            'basename' => false === $removable ? $this->createFileBasename($data) : null,
            'image' => $options['image'],
            'label' => false === $removable ? $view->vars['label'] : false,
            'multipart' => true,
            'multiple' => false,
            'removable' => $removable,
            'type' => 'file',
            'url' => (false === $removable && true === $resolveUrl) ? $this->createFileUrl($data) : null,
            'value' => ''
        ]);
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
                return false === $options['removable'] ? WebFile::class : null;
            },
            'empty_data' => null,
            'error_mapping' => function (Options $options): array {
                return true === $options['removable'] ? ['.' => self::FILE_FIELD] : [];
            },
            'image' => false,
            'removable' => false,
            'remove_field_options' => [
                'block_prefix' => 'web_file_remove',
                'label' => 'web_file.remove',
                'translation_domain' => 'FSiFiles'
            ],
            'resolve_url' => true,
        ]);

        $resolver->setAllowedTypes('image', ['bool']);
        $resolver->setAllowedTypes('removable', ['bool']);
        $resolver->setAllowedTypes('remove_field_options', ['array']);
        $resolver->setAllowedTypes('resolve_url', ['bool']);
    }

    private function createFileUrl(?WebFile $file): ?string
    {
        if (null === $file || true === $file instanceof UploadedWebFile) {
            return null;
        }

        return (string) $this->urlResolver->resolve($file);
    }

    private function createFileBasename(?WebFile $file): ?string
    {
        if (null === $file || true === $file instanceof UploadedWebFile) {
            return null;
        }

        return $this->fileManager->filename($file);
    }
}
