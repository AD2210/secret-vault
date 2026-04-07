<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Project;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class ProjectType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string|null> $plaintextDefaults */
        $plaintextDefaults = $options['plaintext_defaults'];

        $builder
            ->add('name', null, [
                'label' => 'Nom du projet',
            ])
            ->add('client', null, [
                'label' => 'Client',
            ])
            ->add('domain', null, [
                'label' => 'Domaine',
                'required' => false,
            ])
            ->add('serverIp', null, [
                'label' => 'IP serveur',
                'required' => false,
            ])
            ->add('serverUser', null, [
                'label' => 'Utilisateur serveur',
                'required' => false,
            ])
            ->add('sshPort', IntegerType::class, [
                'label' => 'Port SSH',
            ])
            ->add('members', EntityType::class, [
                'class' => User::class,
                'query_builder' => static function (UserRepository $users) {
                    return $users->createQueryBuilder('u')
                        ->orderBy('u.lastName', 'ASC')
                        ->addOrderBy('u.firstName', 'ASC');
                },
                'choice_label' => static fn (User $user): string => sprintf('%s <%s>', $user->getDisplayName(), $user->getEmail()),
                'label' => 'Membres autorisés',
                'multiple' => true,
                'expanded' => false,
                'required' => false,
                'by_reference' => false,
            ])
            ->add('inviteEmail', EmailType::class, [
                'label' => 'Inviter par email',
                'mapped' => false,
                'required' => false,
                'empty_data' => '',
                'help' => 'Si la personne n’a pas encore de compte, elle recevra un lien d’inscription puis devra attendre votre validation.',
            ])
            ->add('sshPublicKey', TextareaType::class, [
                'label' => 'Clé SSH publique',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['sshPublicKey'] ?? null,
            ])
            ->add('sshPrivateKey', TextareaType::class, [
                'label' => 'Clé SSH privée',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['sshPrivateKey'] ?? null,
            ])
            ->add('serverPassword', TextareaType::class, [
                'label' => 'Mot de passe serveur',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['serverPassword'] ?? null,
            ])
            ->add('appSecret', TextareaType::class, [
                'label' => 'APP_SECRET',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['appSecret'] ?? null,
            ])
            ->add('dbName', null, [
                'label' => 'Nom de base',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['dbName'] ?? null,
            ])
            ->add('dbUser', null, [
                'label' => 'Utilisateur DB',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['dbUser'] ?? null,
            ])
            ->add('dbPassword', TextareaType::class, [
                'label' => 'Mot de passe DB',
                'mapped' => false,
                'required' => false,
                'data' => $plaintextDefaults['dbPassword'] ?? null,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Project::class,
            'plaintext_defaults' => [],
        ]);
        $resolver->setAllowedTypes('plaintext_defaults', 'array');
    }
}
