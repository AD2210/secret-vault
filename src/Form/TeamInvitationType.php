<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Entity\UserInvitation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class TeamInvitationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'choices' => array_flip($options['role_choices']),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => UserInvitation::class,
            'role_choices' => User::roleLabels(),
        ]);
        $resolver->setAllowedTypes('role_choices', 'array');
    }
}
