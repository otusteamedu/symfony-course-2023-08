# REST-приложения и FOSRestBundle

Запускаем контейнеры командой `docker-compose up -d`

## Устанавливам rest-bundle и добавляем контроллер

1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакеты `jms/serializer-bundle` и `friendsofsymfony/rest-bundle`
3. В файле `config/packages/fos_rest.yaml` раскомментируем строки
    ```yaml
    fos_rest:
        view:
            view_response_listener:  true

        format_listener:
            rules:
                - { path: ^/api, prefer_extension: true, fallback_format: json, priorities: [ json, html ] }
    ```
4. Добавляем класс `Controller\Api\GetUsers\v4\GetUsersAction`
    ```php
    <?php

    namespace App\Controller\Api\GetUsers\v4;
    
    use App\Manager\UserManager;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    
    class GetUsersAction extends AbstractFOSRestController
    {
        private const DEFAULT_PAGE = 0;
        private const DEFAULT_PER_PAGE = 20;

        public function __construct(private readonly UserManager $userManager)
        {
        }
    
        #[Rest\Get(path: '/api/v4/users')]
        public function __invoke(Request $request): Response
        {
            $perPage = $request->request->get('perPage');
            $page = $request->request->get('page');
            $users = $this->userManager->getUsers($page ?? self::DEFAULT_PAGE, $perPage ?? self::DEFAULT_PER_PAGE);
            $code = empty($users) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
    
            return $this->handleView($this->view(['users' => $users], $code));
        }
    }
    ```
5. Выполняем запрос Get user list v4 из Postman-коллекции v4, видим, что возвращается список пользователей, хотя мы
   не выполняем явно `toArray` для каждого из них

## Добавляем атрибуты для типов при сериализации

1. В классе `App\Entity\User` 
    1. Импортируем класс `JMS\Serializer\Annotation as JMS`
    2. исправляем атрибуты для полей `$age` и `$isActive`
        ```php
        #[Assert\NotBlank]
        #[Assert\GreaterThan(18)]
        #[ORM\Column(type: 'integer', nullable: false)]
        #[JMS\Type('string')]
        private int $age;

        #[ORM\Column(type: 'boolean', nullable: false)]
        #[JMS\Type('int')]
        private bool $isActive;
        ```
    3. Добавляем атрибует Exclude для password
        ```php
        #[ORM\Column(type: 'string', length: 120, nullable: false)]
        #[JMS\Exclude]
        private string $password;
        ```
2. Выполняем запрос Get user list v4 из Postman-коллекции v4 и видим, что типы данных в сериализованном ответе
   отличаются от типов данных в БД, а поля password нет в отвтепше 

## Добавляем группу сериализации

1. В классе `App\Entity\User` добавляем атрибуты группы для полей `$login`, `$age` и `$isActive`
    ```php
    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: false)]
    #[JMS\Groups(['video-user-info'])]
    private string $login;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(18)]
    #[ORM\Column(type: 'integer', nullable: false)]
    #[JMS\Type('string')]
    #[JMS\Groups(['video-user-info'])]
    private int $age;

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[JMS\Type('int')]
    #[JMS\Groups(['video-user-info'])]
    private bool $isActive;
    ```
2. В классе `App\Controller\GetUsers\v4\GetUsersAction` исправляем метод `__invoke`
    ```php
    #[Rest\Get(path: '/api/v4/users')]
    public function __invoke(Request $request): Response
    {
        $perPage = $request->request->get('perPage');
        $page = $request->request->get('page');
        $users = $this->userManager->getUsers($page ?? self::DEFAULT_PAGE, $perPage ?? self::DEFAULT_PER_PAGE);
        $code = empty($users) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
        $context = (new Context())->setGroups(['video-user-info']);

        return $this->handleView(
            $this->view(['users' => $users], $code)->setContext($context),
        );
    }
    ```
3. Выполняем запрос Get user list v4 из Postman-коллекции v4 и видим, что отдаются только атрибутированные поля

## Добавляем формат ответа

1. В классе `App\Controller\GetUsers\v4\GetUserAction` исправляем метод `__invoke`
    ```php
    #[Rest\Get(path: '/api/v4/users.{format}', defaults: ['format' => 'json'])]
    public function __invoke(Request $request, string $format): Response
    {
        $perPage = $request->request->get('perPage');
        $page = $request->request->get('page');
        $users = $this->userManager->getUsers($page ?? self::DEFAULT_PAGE, $perPage ?? self::DEFAULT_PER_PAGE);
        $code = empty($users) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
        $context = (new Context())->setGroups(['video-user-info']);
   
        return $this->handleView(
            $this->view(['users' => $users], $code)->setContext($context)->setFormat($format),
        );
    }
    ```
2. Выполняем запрос Get user list v4 XML и видим, что ответ возвращается в xml

## Добавляем ещё одну группу сериализации

1. В классе `App\Entity\User` Добавляем атрибут для другой группы сериализации для поля `$id`
    ```php
    #[ORM\Column(name: 'id', type: 'bigint', unique:true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[JMS\Groups(['user-id-list'])]
    private ?int $id = null;
    ```
2. В классе `App\Controller\GetUsers\v4\GetUserAction` в методе `__invoke` добавляем в контекст ещё одну группу
    ```php
    $context = (new Context())->setGroups(['video-user-info', 'user-id-list']);
    ```
3. Выполняем запрос Get user list v4 из Postman-коллекции v4 и видим, что в ответ добавилось поле `id`

## Добавляем параметры запроса

1. Добавляем класс `App\Controller\Api\CreateUser\v4\CreateUserAction`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v4;
    
    use App\Entity\User;
    use App\Manager\UserManager;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\Controller\Annotations\RequestParam;
    use JsonException;
    use Symfony\Component\HttpFoundation\Response;
    use App\DTO\ManageUserDTO;
    
    class CreateUserAction extends AbstractFOSRestController
    {
        public function __construct(private readonly UserManager $userManager)
        {
        }
    
        /**
         * @throws JsonException
         */
        #[Rest\Post(path: '/api/v4/users')]
        #[RequestParam(name: 'login')]
        #[RequestParam(name: 'password')]
        #[RequestParam(name: 'roles')]
        #[RequestParam(name: 'age', requirements: '\d+')]
        #[RequestParam(name: 'isActive', requirements: 'true|false')]
        public function __invoke(
            string $login,
            string $password,
            string $roles,
            string $age,
            string $isActive,
        ): Response {
            $userDTO = new ManageUserDTO(...[
                'login' => $login,
                'password' => $password,
                'age' => (int)$age,
                'isActive' => $isActive === 'true',
                'roles' => json_decode($roles, true, 512, JSON_THROW_ON_ERROR),
            ]);
    
            $userId = $this->userManager->saveUserFromDTO(new User(), $userDTO);
            [$data, $code] = ($userId === null) ? [['success' => false], 400] : [['id' => $userId], 200];
    
            return $this->handleView($this->view($data, $code));
        }
    }
    ```
 2. Выполняем запрос Add user v4 из Postman-коллекции v4, видим ошибку

## Форсируем ParamFetcherListener

1. В файле `config/packages/fos_rest.yaml` добавляем строку
      ```yaml
      param_fetcher_listener:  force
      ```
2. Выполняем запрос Add user v4 из Postman-коллекции v4, видим, что пользователь сохранился в БД

## Добавляем маппинг строки запроса

1. В классе `App\Controller\Api\GetUsers\v4\GetUserAction` исправляем метод `__invoke`
    ```php
    #[Rest\Get(path: '/api/v4/users.{format}', defaults: ['format' => 'json'])]
    public function __invoke(
        #[MapQueryParameter] ?int $perPage,
        #[MapQueryParameter] ?int $page,
        string $format
    ): Response
    {
        $users = $this->userManager->getUsers($page ?? self::DEFAULT_PAGE, $perPage ?? self::DEFAULT_PER_PAGE);
        $code = empty($users) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
        $context = (new Context())->setGroups(['video-user-info', 'user-id-list']);

        return $this->handleView(
            $this->view(['users' => $users], $code)->setContext($context)->setFormat($format),
        );
    }
    ```
2. Выполняем запрос Get user list v4 с параметрами `perPage = 1` и `page = 0` из Postman-коллекции v4 и видим, что
пагинация отрабатывает

## Добавляем ParamConverter

1. Устанавливаем пакет `symfony/options-resolver`
2. В файл `config/services.yaml` исправляем секцию `App\` добавляем строку
    ```yaml
    App\:
        resource: '../src/'
        exclude:
            - '../src/DependencyInjection/'
            - '../src/Entity/'
            - '../src/Kernel.php'
            - '../src/Controller/Common/*'
    ```
3. Добавляем класс `App\Controller\Common\Error`
    ```php
    <?php
 
    namespace App\Controller\Common;
 
    class Error
    {
        public function __construct(
            public readonly string $propertyPath,
            public readonly string $message
        ) {
        }
    }
    ```
4. Добавляем класс `App\Controller\Common\ErrorResponse`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    class ErrorResponse
    {
        public bool $success = false;
    
        /** @var Error[] */
        public array $errors;
    
        public function __construct(Error ...$errors)
        {
            $this->errors = $errors;
        }
    }
    ```
5. Добавляем трейт `App\Controller\Common\ErrorResponseTrait`
    ```php
    <?php
    
    namespace App\Controller\Common;
    
    use FOS\RestBundle\View\View;
    use Symfony\Component\Validator\ConstraintViolationInterface;
    use Symfony\Component\Validator\ConstraintViolationListInterface;
    
    trait ErrorResponseTrait
    {
        private function createValidationErrorResponse(int $code, ConstraintViolationListInterface $validationErrors): View
        {
            $errors = [];
            foreach ($validationErrors as $error) {
                /** @var ConstraintViolationInterface $error */
                $errors[] = new Error($error->getPropertyPath(), $error->getMessage());
            }
    
            return View::create(new ErrorResponse($errors), $code);
        }
    }    
    ```
6. Добавляем трейт `App\DTO\Traits\SafeLoadFieldsTrait`
    ```php
    <?php
   
    namespace App\DTO\Traits;
   
    use Symfony\Component\HttpFoundation\Request;
   
    trait SafeLoadFieldsTrait
    {
        abstract public function getSafeFields(): array;
   
        public function loadFromJsonString(string $json): void
        {
            $this->loadFromArray(json_decode($json, true, 512, JSON_THROW_ON_ERROR));
        }
   
        public function loadFromJsonRequest(Request $request): void
        {
            $this->loadFromJsonString($request->getContent());
        }
   
        public function loadFromArray(?array $input): void
        {
            if (empty($input)) {
                return;
            }
            $safeFields = $this->getSafeFields();
   
            foreach ($safeFields as $field) {
                if (array_key_exists($field, $input)) {
                    $this->{$field} = $input[$field];
                }
            }
        }
    }
    ```
7. Добавляем класс `App\Controller\Api\CreateUser\v5\Input\CreateUserDTO`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5\Input;
    
    use App\Entity\Traits\SafeLoadFieldsTrait;
    use Symfony\Component\Validator\Constraints as Assert;
    
    class CreateUserDTO
    {
        use SafeLoadFieldsTrait;
    
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 32)]
        public string $login;
    
        #[Assert\NotBlank]
        #[Assert\Type('string')]
        #[Assert\Length(max: 32)]
        public string $password;
    
        #[Assert\NotBlank]
        #[Assert\Type('array')]
        public array $roles;
    
        #[Assert\NotBlank]
        #[Assert\Type('numeric')]
        public int $age;
    
        #[Assert\NotBlank]
        #[Assert\Type('bool')]
        public bool $isActive;
    
        public function getSafeFields(): array
        {
            return ['login', 'password', 'roles', 'age', 'isActive'];
        }
    }
    ```
8. Добавляем класс `App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO`
    ```php
    <?php
 
    namespace App\Controller\Api\CreateUser\v5\Output;
 
    use App\DTO\Traits\SafeLoadFieldsTrait;
 
    class UserIsCreatedDTO
    {
        use SafeLoadFieldsTrait;
 
        public int $id;
    
        public string $login;
    
        public int $age;
    
        public bool $isActive;
    
        public function getSafeFields(): array
        {
            return ['id', 'login', 'age', 'isActive'];
        }
    }
    ```
9. Добавляем класс `App\Controller\Api\CreateUser\v5\CreateUserAction`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5;
    
    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use App\Controller\Common\ErrorResponseTrait;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Validator\ConstraintViolationListInterface;
    
    class CreateUserAction extends AbstractFOSRestController
    {
        use ErrorResponseTrait;
    
        public function __construct(private readonly CreateUserManager $saveUserManager)
        {
        }
    
        #[Rest\Post(path: '/api/v5/users')]
        public function saveUserAction(CreateUserDTO $request, ConstraintViolationListInterface $validationErrors): Response
        {
            if ($validationErrors->count()) {
                $view = $this->createValidationErrorResponse(Response::HTTP_BAD_REQUEST, $validationErrors);
                return $this->handleView($view);
            }
            $user = $this->saveUserManager->saveUser($request);
            [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];
    
            return $this->handleView($this->view($data, $code));
        }
    }
    ```
10. Добавляем класс `App\Controller\Api\CreateUser\v5\CreateUserManager`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5;
    
    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
    use App\Entity\User;
    use Doctrine\ORM\EntityManagerInterface;
    use JMS\Serializer\SerializationContext;
    use JMS\Serializer\SerializerInterface;
    use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
    
    class CreateUserManager
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly SerializerInterface $serializer,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
        ) {
        }
    
        public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
        {
            $user = new User();
            $user->setLogin($saveUserDTO->login);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $saveUserDTO->password));
            $user->setRoles($saveUserDTO->roles);
            $user->setAge($saveUserDTO->age);
            $user->setIsActive($saveUserDTO->isActive);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
    
            $result = new UserIsCreatedDTO();
            $context = (new SerializationContext())->setGroups(['video-user-info', 'user-id-list']);
            $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));
    
            return $result;
        }
    }
    ```
11. Добавляем класс `App\Symfony\MainParamConverter`
    ```php
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
    ```
12. В классе `App\Entity\User` возвращаем правильные типы данных в атрибутах для полей `$age` и `$isActive`, а также
добавляем к полю `$isActive` атрибут `#JMS\SerializedName`
    ```php
        #[Assert\NotBlank]
        #[Assert\GreaterThan(18)]
        #[ORM\Column(type: 'integer', nullable: false)]
        #[JMS\Groups(['video-user-info'])]
        private int $age;
    
        #[ORM\Column(type: 'boolean', nullable: false)]
        #[JMS\Groups(['video-user-info'])]
        #[JMS\SerializedName('isActive')]
        private bool $isActive;
    ```
13. Выполняем запрос Add user v5 из Postman-коллекции v4, видим, что пользователь добавился

## Переходим на новый маппинг

1. Устанавливаем пакет `symfony/serializer-pack`
2. В классе `App\Controller\Api\CreateUser\v5\Input\CreateUserDTO` убираем использование трейта `SafeLoadFieldsTrait` 
3. Выполняем запрос Add user v5 из Postman-коллекции v4, видим ошибку
4. В классе `App\Controller\Api\CreateUser\v5\CreateUserAction` исправляем метод `saveUserAction`
    ```php
    #[Rest\Post(path: '/api/v5/users')]
    public function saveUserAction(#[MapRequestPayload] CreateUserDTO $request): Response
    {
        $user = $this->saveUserManager->saveUser($request);
        [$data, $code] = ($user->id === null) ? [['success' => false], 400] : [['user' => $user], 200];

        return $this->handleView($this->view($data, $code));
    }
    ```
5. Выполняем запрос Add user v5 из Postman-коллекции v4, видим, что пользователь добавился
6. Выполняем запрос Add user v5 из Postman-коллекции v4 с ошибочными данными (например, без пароля), видим код 422

