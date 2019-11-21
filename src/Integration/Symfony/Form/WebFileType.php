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
use FSi\Component\Files\FileUrlResolver;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\FormFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\RemovableFileTransformer;
use FSi\Component\Files\Integration\Symfony\Form\Transformer\RemovableWebFileListener;
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
    /**
     * @var FileUrlResolver
     */
    private $urlResolver;

    /**
     * @var FormFileTransformer[]
     */
    private $fileTransformers;

    public function __construct(FileUrlResolver $urlResolver, iterable $fileTransformers)
    {
        if (false === is_array($fileTransformers)) {
            $fileTransformers = iterator_to_array($fileTransformers);
        }

        Assertion::allIsInstanceOf($fileTransformers, FormFileTransformer::class);
        $this->urlResolver = $urlResolver;
        $this->fileTransformers = $fileTransformers;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if (true === $options['removable']) {
            /** @var array $fileFieldOptions */
            $fileFieldOptions = array_replace($options, [
                'allow_file_upload' => true,
                'compound' => false,
                'error_bubbling' => false,
                'removable' => false
            ]);

            $builder->add($builder->getName(), WebFileType::class, $fileFieldOptions);
            $builder->add($options['remove_field_name'], CheckboxType::class, [
                'label' => 'web_file.remove',
                'mapped' => false,
                'required' => false
            ]);

            $builder->addEventListener(FormEvents::PRE_SUBMIT, new RemovableWebFileListener());
            $builder->addModelTransformer(new RemovableFileTransformer($builder->getName()));
        } else {
            foreach ($this->fileTransformers as $transformer) {
                $builder->addEventListener(FormEvents::PRE_SUBMIT, $transformer);
            }
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $data = $form->getData();
        $removable = $options['removable'];
        $view->vars = array_replace($view->vars, [
            'basename' => false === $removable ? $this->createFileBasename($data) : null,
            'multipart' => true,
            'multiple' => false,
            'removable' => $removable,
            'remove_field_name' => $options['remove_field_name'],
            'url' => false === $removable ? $this->createFileUrl($data) : null,
            'value' => ''
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'allow_file_upload' => function (Options $options): bool {
                return false === $options['removable'];
            },
            'compound' => function (Options $options): bool {
                return true === $options['removable'];
            },
            'data_class' => function (Options $options) {
                return false === $options['removable'] ? WebFile::class : null;
            },
            'removable' => false,
            'remove_field_name' => 'remove',
            'translation_domain' => 'FSiFiles'
        ]);

        $resolver->setAllowedTypes('removable', ['bool']);
        $resolver->setAllowedTypes('remove_field_name', ['string']);
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

        return basename($file->getPath());
    }
}
