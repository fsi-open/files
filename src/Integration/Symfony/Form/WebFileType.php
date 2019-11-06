<?php

/**
 * (c) FSi sp. z o.o. <info@fsi.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace FSi\Component\Files\Integration\Symfony\Form;

use FSi\Component\Files\Integration\Symfony\Transformer\SymfonyFileToWebFileTransformer;
use FSi\Component\Files\UploadedWebFile;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class WebFileType extends AbstractType
{
    /**
     * @var SymfonyFileToWebFileTransformer
     */
    private $transformer;

    public function __construct(SymfonyFileToWebFileTransformer $transformer)
    {
        $this->transformer = $transformer;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            /** @var File|null $data */
            $data = $event->getData();
            if (null === $data) {
                return;
            }

            $event->setData($this->transformer->transform($data));
        });
    }

    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        $view->vars = array_replace($view->vars, [
            'type' => 'file',
            'value' => '',
            'multipart' => true
        ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefault('allow_file_upload', true);
        $resolver->setDefault('data_class', UploadedWebFile::class);
        $resolver->setDefault('compound', false);
        $resolver->setDefault('empty_data', null);
    }
}
