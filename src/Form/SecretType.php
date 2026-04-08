<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Secret;
use App\Secrets\SecretTypeRegistry;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class SecretType extends AbstractType
{
    public function __construct(
        private readonly SecretTypeRegistry $secretTypes,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var array<string, string|null> $plaintextDefaults */
        $plaintextDefaults = $options['plaintext_defaults'];
        /** @var string $secretType */
        $secretType = $options['secret_type'];

        $builder
            ->add('name', null, [
                'label' => 'Nom',
            ])
            ->add('type', HiddenType::class, [
                'mapped' => false,
                'data' => $secretType,
            ]);

        foreach ($this->secretTypes->get($secretType)['fields'] as $field) {
            $fieldOptions = [
                'label' => $field['label'],
                'mapped' => false,
                'required' => (bool) ($field['required'] ?? true),
                'data' => $plaintextDefaults[$field['key']] ?? null,
                'help' => $field['help'] ?? null,
            ];
            if ('choice' === $field['input']) {
                $fieldOptions['choices'] = array_flip($field['choices'] ?? []);
            }

            $builder->add($field['key'], $this->resolveFieldType($field['input']), $fieldOptions);
        }
    }

    private function resolveFieldType(string $input): string
    {
        return match ($input) {
            'integer' => IntegerType::class,
            'textarea' => TextareaType::class,
            'choice' => ChoiceType::class,
            default => TextType::class,
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Secret::class,
            'plaintext_defaults' => [],
            'secret_type' => Secret::TYPE_SECRET,
            'locked_type' => false,
        ]);
        $resolver->setAllowedTypes('plaintext_defaults', 'array');
        $resolver->setAllowedTypes('secret_type', 'string');
        $resolver->setAllowedTypes('locked_type', 'bool');
    }
}
