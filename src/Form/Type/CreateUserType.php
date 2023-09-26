<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;

class CreateUserType extends UserType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('password', PasswordType::class, [
            'label' => 'Пароль пользователя',
        ])
            ->setMethod('POST');
    }
}
