<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('firstName', null, [
                'label' => 'Prénom',
            ])
            ->add('lastName', null, [
                'label' => 'Nom',
            ])
            ->add('isActive', CheckboxType::class, [
                'label' => 'Compte actif',
                'required' => false,
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => $options['require_password'],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
                'first_options' => [
                    'label' => 'Mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Confirmation du mot de passe',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ])
            ->add('role', ChoiceType::class, [
                'label' => 'Rôle',
                'mapped' => false,
                'choices' => array_flip($options['role_choices']),
                'data' => $options['current_role'],
            ])
            ->add('reset_totp', CheckboxType::class, [
                'label' => 'Réinitialiser le 2FA',
                'mapped' => false,
                'required' => false,
            ])
            ->add('projects', EntityType::class, [
                'class' => \App\Entity\Project::class,
                'choice_label' => static fn (\App\Entity\Project $project): string => sprintf('%s · %s', $project->getName(), $project->getClient()),
                'choices' => $options['project_choices'],
                'data' => $options['selected_projects'],
                'label' => 'Projets affectés',
                'mapped' => false,
                'multiple' => true,
                'required' => false,
                'by_reference' => false,
                'attr' => [
                    'data-controller' => 'relation-picker',
                    'data-relation-picker-placeholder-value' => 'Rechercher un projet...',
                    'data-relation-picker-empty-label-value' => 'Aucun projet disponible.',
                    'data-relation-picker-selected-label-value' => 'Aucun projet affecté.',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => true,
            'role_choices' => User::roleLabels(),
            'current_role' => User::ROLE_USER,
            'project_choices' => [],
            'selected_projects' => [],
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
        $resolver->setAllowedTypes('role_choices', 'array');
        $resolver->setAllowedTypes('current_role', 'string');
        $resolver->setAllowedTypes('project_choices', 'array');
        $resolver->setAllowedTypes('selected_projects', 'array');
    }
}
