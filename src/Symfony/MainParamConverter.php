<?php

namespace App\Symfony;

use App\DTO\Traits\SafeLoadFieldsTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Sensio\Bundle\FrameworkExtraBundle\Request\ParamConverter\ParamConverterInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class MainParamConverter implements ParamConverterInterface
{
    public function __construct(private readonly ValidatorInterface $validator)
    {
    }

    public function apply(Request $httpRequest, ParamConverter $configuration): bool
    {
        $class = $configuration->getClass();
        /** @var SafeLoadFieldsTrait $request */
        $request = new $class();
        $request->loadFromJsonRequest($httpRequest);
        $errors = $this->validate($request, $httpRequest, $configuration);
        $httpRequest->attributes->set('validationErrors', $errors);

        return true;
    }

    public function supports(ParamConverter $configuration): bool
    {
        return !empty($configuration->getClass()) &&
            in_array(SafeLoadFieldsTrait::class, class_uses($configuration->getClass()), true);
    }

    public function validate($request, Request $httpRequest, ParamConverter $configuration): ConstraintViolationListInterface
    {
        $httpRequest->attributes->set($configuration->getName(), $request);
        $options = $configuration->getOptions();
        $resolver = new OptionsResolver();
        $resolver->setDefaults([
            'groups' => null,
            'traverse' => false,
            'deep' => false,
        ]);
        $validatorOptions = $resolver->resolve($options['validator'] ?? []);

        return $this->validator->validate($request, null, $validatorOptions['groups']);
    }
}
