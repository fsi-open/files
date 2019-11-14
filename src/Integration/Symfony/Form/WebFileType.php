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
use FSi\Component\Files\UploadedWebFile;
use FSi\Component\Files\WebFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use function array_replace;

final class WebFileType extends AbstractType
{
    /**
     * @var FileUrlResolver
     */
    private $urlResolver;

    /**
     * @var iterable<FormFileTransformer>
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
        foreach ($this->fileTransformers as $transformer) {
            $builder->addEventListener(FormEvents::PRE_SUBMIT, $transformer);
        }
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $data = $form->getData();
        $view->vars = array_replace($view->vars, [
            'type' => 'file',
            'value' => '',
            'multipart' => true,
            'url' => $this->createFileUrl($data),
            'basename' => $this->createFileBasename($data)
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('allow_file_upload', true);
        $resolver->setDefault('data_class', WebFile::class);
        $resolver->setDefault('compound', false);
        $resolver->setDefault('empty_data', null);
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
