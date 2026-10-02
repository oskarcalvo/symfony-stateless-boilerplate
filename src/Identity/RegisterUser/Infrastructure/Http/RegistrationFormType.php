<?php

declare(strict_types=1);

namespace App\Identity\RegisterUser\Infrastructure\Http;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<RegistrationFormData>
 */
final class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre',
                'attr' => ['autocomplete' => 'name', 'autofocus' => true],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add('password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Las contraseñas no coinciden.',
                'first_options' => [
                    'label' => 'Contraseña',
                    'help' => \sprintf('Al menos %d caracteres.', RegistrationFormData::PASSWORD_MIN_LENGTH),
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'Repite la contraseña',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationFormData::class,
            // Stateless CSRF (config/packages/csrf.yaml): validated with Origin/Sec-Fetch-Site headers
            // or the double-submit cookie set by assets/controllers/csrf_protection_controller.js.
            'csrf_protection' => true,
            'csrf_token_id' => 'submit',
        ]);
    }
}
