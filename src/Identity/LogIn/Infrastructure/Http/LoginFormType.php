<?php

declare(strict_types=1);

namespace App\Identity\LogIn\Infrastructure\Http;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<LoginFormData>
 */
final class LoginFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            ])
            ->add('password', PasswordType::class, [
                'label' => 'Contraseña',
                'attr' => ['autocomplete' => 'current-password'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => LoginFormData::class,
            // Stateless CSRF (config/packages/csrf.yaml): validated with Origin/Sec-Fetch-Site headers
            // or the double-submit cookie set by assets/controllers/csrf_protection_controller.js.
            'csrf_protection' => true,
            'csrf_token_id' => 'authenticate',
        ]);
    }
}
