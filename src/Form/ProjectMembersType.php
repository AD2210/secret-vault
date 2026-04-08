<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectMembersType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('members', EntityType::class, [
            'class' => User::class,
            'choice_label' => static fn (User $user): string => sprintf('%s · %s', $user->getDisplayName(), $user->getRoleLabel()),
            'choices' => $options['member_choices'],
            'data' => $options['selected_members'],
            'label' => 'Membres affectés',
            'mapped' => false,
            'multiple' => true,
            'required' => false,
            'by_reference' => false,
            'attr' => [
                'data-controller' => 'relation-picker',
                'data-relation-picker-placeholder-value' => 'Rechercher un membre...',
                'data-relation-picker-empty-label-value' => 'Aucun membre disponible.',
                'data-relation-picker-selected-label-value' => 'Aucun membre affecté.',
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'member_choices' => [],
            'selected_members' => [],
        ]);
        $resolver->setAllowedTypes('member_choices', 'array');
        $resolver->setAllowedTypes('selected_members', 'array');
    }
}
