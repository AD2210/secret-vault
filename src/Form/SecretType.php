<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Secret;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SecretType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string|null> $plaintextDefaults */
        $plaintextDefaults = $options['plaintext_defaults'];

        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('publicSecret', TextareaType::class, [
                'label' => 'Secret public',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['publicSecret'] ?? null,
            ])
            ->add('privateSecret', TextareaType::class, [
                'label' => 'Secret privé',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['privateSecret'] ?? null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Secret::class,
            'plaintext_defaults' => [],
        ]);
        $resolver->setAllowedTypes('plaintext_defaults', 'array');
    }
}
