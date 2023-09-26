# Symfony Forms

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем форму для создания и редактирования пользователя

1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет Symfony Forms командой `composer require symfony/form`
3. Устанавливаем валидатор командой `composer require symfony/validator`
4. В классе `App\Entity\User` добавляем поля и геттеры/сеттеры для них
    ```php
    #[ORM\Column(type: 'string', length: 32, nullable: false)]
    private string $password;

    #[ORM\Column(type: 'integer', nullable: false)]
    private int $age;

    #[ORM\Column(type: 'boolean', nullable: false)]
    private bool $isActive;

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): void
    {
        $this->password = $password;
    }

    public function getAge(): int
    {
        return $this->age;
    }

    public function setAge(int $age): void
    {
        $this->age = $age;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): void
    {
        $this->isActive = $isActive;
    }
    ```
5. Создадем миграцию `bin/console doctrine:migrations:diff` и выполняем ее `bin/console doctrin:migrations:migrate`
6. В классе `App\Manager\UserManager`
    1. переименовываем метод `create` в `createByLogin`
    2. Добавляем метод `saveUser`
        ```php
        public function saveUser(User $user): void
        {
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
        ```
7. Добавляем класс `App\Form\Type\UserType`
    ```php
    <?php
    
    namespace App\Form\Type;
    
    use App\Entity\User;
    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
    use Symfony\Component\Form\Extension\Core\Type\IntegerType;
    use Symfony\Component\Form\Extension\Core\Type\PasswordType;
    use Symfony\Component\Form\Extension\Core\Type\SubmitType;
    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\FormBuilderInterface;
    use Symfony\Component\OptionsResolver\OptionsResolver;
    
    class UserType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder
                ->add('login', TextType::class, [
                    'label' => 'Логин пользователя',
                    'attr' => [
                        'data-time' => time(),
                        'placeholder' => 'Логин пользователя',
                        'class' => 'user-login',
                    ],
                ]);
    
            if ($options['isNew'] ?? false) {
                $builder->add('password', PasswordType::class, [
                    'label' => 'Пароль пользователя',
                ]);
            }

            $builder
                ->add('age', IntegerType::class, [
                    'label' => 'Возраст',
                ])
                ->add('isActive', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('submit', SubmitType::class);
        }
    
        public function configureOptions(OptionsResolver $resolver): void
        {
            $resolver->setDefaults([
                'data_class' => User::class,
                'empty_data' => new User(),
                'isNew' => false,
            ]);
        }
    
        public function getBlockPrefix(): string
        {
            return 'save_user';
        }
    }
    ```
8. В классе `App\Controller\Api\v1\UserController`
    1. Добавляем зависимость от FormFactoryInterface
        ```php
        public function __construct(
              private readonly UserManager $userManager,
              private readonly EventDispatcherInterface $eventDispatcher,
              private readonly FormFactoryInterface $formFactory,
        ) {
        }
        ``` 
    2. Добавляем метод `manageUserAction`
        ```php
        #[Route(path: '/create-user', name: 'create_user', methods: ['GET', 'POST'])]
        #[Route(path: '/update-user/{id}', name: 'update-user', methods: ['GET', 'POST'])]
        public function manageUserAction(Request $request, string $_route, ?User $user = null): Response
        {
             $form = $this->formFactory->create(UserType::class, $user, ['isNew' => $_route === 'create_user']);
             $form->handleRequest($request);
     
             if ($form->isSubmitted() && $form->isValid()) {
                 /** @var User $user */
                 $user = $form->getData();
                 $this->userManager->saveUser($user);
             }
  
             return $this->renderForm('manageUser.html.twig', [
                 'form' => $form,
                 'isNew' => $_route === 'create_user',
                 'user' => $user,
             ]);
        }
        ```
9. Добавляем файл `src/templates/manageUser.html.twig`
    ```html
    {% extends 'layout.twig' %}
   
    {% block body %}
        <div>
            {{ form(form) }}
        </div>
    {% endblock %}
    ```
10. В браузере переходим по адресу `http://localhost:7777/api/v1/user/create-user`, видим форму
11. Вводим данные, отправляем и проверяем базу. Данные должны быть сохранены
12. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/user/update-user/ID`,
проверяем работоспособность сохранения.

## Добавляем валидацию

1. В классе `App\Entity\User`
    1. Добавляем импорт `Symfony\Component\Validator\Constraints as Assert` 
    2. Атрибуты для валидации для поля `age`
        ```php
        #[Assert\NotBlank]
        #[Assert\GreaterThan(18)]
        #[ORM\Column(type: 'integer', nullable: false)]
        private int $age;
        ```
2. Переходим по адресу `http://localhost:7777/api/v1/user/create-user` и пробуем ввести невалидный возраст, видим ошибку
3. Пробуем ввести возраст больше 18 и проверяем, что пользователь сохраняется в БД.

## Добавляем DTO

1. Добавляем класс `App\DTO\ManageUserDTO`
    ```php
    <?php

    namespace App\DTO;
   
    use Symfony\Component\Validator\Constraints as Assert;
    use App\Entity\User;
   
    class ManageUserDTO
    {
        public function __construct(
            #[Assert\NotBlank]
            #[Assert\Length(max: 32)]
            public string $login = '',
    
            #[Assert\NotBlank]
            #[Assert\Length(max: 32)]
            public string $password = '',
    
            #[Assert\NotBlank]
            public int $age = 0,
    
            public bool $isActive = false,
        ) {
        }
    
        public static function fromEntity(User $user): self
        {
            return new self(...[
                'login' => $user->getLogin(),
                'password' => $user->getPassword(),
                'age' => $user->getAge(),
                'isActive' => $user->isActive(),
            ]);
        }
    }
    ```
2. В классе `App\Manager\UserManager` добавляем метод `saveUserFromDTO`
    ```php
    public function saveUserFromDTO(User $user, ManageUserDTO $manageUserDTO): ?int
    {
        $user->setLogin($manageUserDTO->login);
        $user->setPassword($manageUserDTO->password);
        $user->setAge($manageUserDTO->age);
        $user->setIsActive($manageUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }
    ```
3. В классе `App\Form\Type\UserType` исправляем метод `configureOptions`
    ```php
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => ManageUserDTO::class,
            'empty_data' => new ManageUserDTO(),
            'isNew' => false,
        ]);
    }
    ```
4. В классе `App\Controller\Api\v1\UserController` исправляем метод `manageUserAction`
    ```php
    #[Route(path: '/create-user', name: 'create_user', methods: ['GET', 'POST'])]
    #[Route(path: '/update-user/{id}', name: 'update-user', methods: ['GET', 'POST'])]
    public function manageUserAction(Request $request, string $_route, ?int $id = null): Response
    {
        if ($id !== null) {
            $user = $this->userManager->findUser($id);
            $dto = ManageUserDTO::fromEntity($user);
        }
        $form = $this->formFactory->create(UserType::class, $dto ?? null, ['isNew' => $_route === 'create_user']);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ManageUserDTO $userDto */
            $userDto = $form->getData();

            $this->userManager->saveUserFromDTO($user ?? new User(), $userDto);
        }

        return $this->renderForm('manageUser.html.twig', [
            'form' => $form,
            'isNew' => $_route === 'create_user',
            'user' => $dto ?? null,
        ]);
    }
    ```
5. Переходим по адресу `http://localhost:7777/api/v1/user/create-user`, создаём пользователя
6. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/user/update-user/ID`,
проверяем работоспособность сохранения.

## Меняем HTTP-метод для редактирования

1. В классе `App\Controller\Api\v1\UserController` исправляем для метода `manageUserAction` атрибут с маршрутом для редактирования 
   ```php
   #[Route(path: '/update-user/{id}', name: 'update-user', methods: ['GET', 'PATCH'])]
   ```
2. В классе `App\Form\Type\UserType` исправляем метод `buildForm`
    ```php
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('login', TextType::class, [
                'label' => 'Логин пользователя',
                'attr' => [
                    'data-time' => time(),
                    'placeholder' => 'Логин пользователя',
                    'class' => 'user-login',
                ],
            ]);

        if ($options['isNew'] ?? false) {
            $builder->add('password', PasswordType::class, [
                'label' => 'Пароль пользователя',
            ]);
        }

        $builder
            ->add('age', IntegerType::class, [
                'label' => 'Возраст',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
            ->add('submit', SubmitType::class)
            ->setMethod($options['isNew'] ? 'POST' : 'PATCH');
    }
    ```
3. Исправляем файл `public/index.php`
    ```php
    <?php
   
    use App\Kernel;
    use Symfony\Component\HttpFoundation\Request;
       
    require_once dirname(__DIR__).'/vendor/autoload_runtime.php';
       
    return function (array $context) {
        Request::enableHttpMethodParameterOverride();
        return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
    };
    ```
4. Переходим по адресу `http://localhost:7777/api/v1/user/create-user`, создаём пользователя
5. Берем в качестве ID последнего созданного пользователя, переходим по адресу `http://localhost:7777/api/v1/user/update-user/ID`,
   проверяем работоспособность сохранения.

## Добавляем boostrap в форму

1. Исправляем файл `src/templates/manageUser.html.twig`
    ```html
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        {% block head_css %}
            <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/1.1.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
        {% endblock %}
    </head>
    <body>
    {% form_theme form 'bootstrap_4_layout.html.twig' %}
    <div style="width:50%;margin-left:10px;margin-top:10px">
        {{ form(form) }}
    </div>
    {% block head_js %}
        <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
        <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    {% endblock %}
    </body>
    </html>
    ```
2. Переходим по адресу `http://localhost:7777/api/v1/user/create-user`, видим более красивый вариант формы

## Добавляем отображение отношений в форму редактирования

1. Добавляем класс `App\Form\Type\LinkedUserType`
    ```php
    <?php
    
    namespace App\Form\Type;
    
    use Symfony\Component\Form\AbstractType;
    use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
    use Symfony\Component\Form\Extension\Core\Type\HiddenType;
    use Symfony\Component\Form\Extension\Core\Type\IntegerType;
    use Symfony\Component\Form\Extension\Core\Type\PasswordType;
    use Symfony\Component\Form\Extension\Core\Type\TextType;
    use Symfony\Component\Form\FormBuilderInterface;
    
    class LinkedUserType extends AbstractType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            $builder->add('login', TextType::class)
            ->add('password', PasswordType::class, ['required' => false])
            ->add('age', IntegerType::class)
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('id', HiddenType::class);
        }
    }
    ```
2. Добавляем класс `App\Form\Type\CreateUserType`
    ```php
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
    ```
3. Добавляем класс `App\Form\Type\UpdateUserType`
    ```php
    <?php
    
    namespace App\Form\Type;
    
    use Symfony\Component\Form\Extension\Core\Type\CollectionType;
    use Symfony\Component\Form\FormBuilderInterface;
    
    class UpdateUserType extends UserType
    {
        public function buildForm(FormBuilderInterface $builder, array $options): void
        {
            parent::buildForm($builder, $options);
    
            $builder->setMethod('PATCH')
                ->add('followers', CollectionType::class, [
                    'entry_type' => LinkedUserType::class,
                    'entry_options' => ['label' => false],
                ]);
        }
    }
    ```
4. В классе `App\Controller\Api\v1\UserController` исправляем метод `manageUserAction` 
    ```php
    #[Route(path: '/create-user', name: 'create_user', methods: ['GET', 'POST'])]
    #[Route(path: '/update-user/{id}', name: 'update_user', methods: ['GET', 'PATCH'])]
    public function manageUserAction(Request $request, string $_route, ?int $id = null): Response
    {
        if ($id) {
            $user = $this->userManager->findUser($id);
            $dto = ManageUserDTO::fromEntity($user);
        }
        $form = $this->formFactory->create(
            $_route === 'create_user' ? CreateUserType::class : UpdateUserType::class,
            $dto ?? null,
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ManageUserDTO $userDto */
            $userDto = $form->getData();

            $this->userManager->saveUserFromDTO($user ?? new User(), $userDto);
        }

        return $this->renderForm('manageUser.html.twig', [
            'form' => $form,
            'isNew' => $_route === 'create_user',
            'user' => $user ?? null,
        ]);
    }
    ```
5. Исправляем файл `src/templates/form.twig`
    ```html
    <!DOCTYPE html>
    <html>
    <head>
       <meta charset="UTF-8">
       {% block head_css %}
       <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/1.1.1/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
       {% endblock %}
    </head>
    <body>
    {% form_theme form 'bootstrap_4_layout.html.twig' %}
    <div style="width:50%;margin-left:10px;margin-top:10px">
       {{ form_start(form) }}
       {{ form_row(form.login) }}
       {{ form_row(form.age) }}
       {{ form_row(form.isActive) }}
    
       {% if form.followers is defined %}
       <h3>Followers</h3>
       <ul class="followers">
          {% for follower in form.followers %}
          <li>{{ form_row(follower.login) }}</li>
          <li>{{ form_row(follower.age) }}</li>
          <li>{{ form_row(follower.isActive) }}</li>
          {% endfor %}
       </ul>
       {% endif %}
       {% if form.password is defined %}
       {{ form_row(form.password) }}
       {% endif %}
       {{ form_row(form.submit) }}
       {{ form_end(form, {'render_rest': false}) }}
    </div>
    {% block head_js %}
    <script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">
    {% endblock %}
    </body>
    </html>
    ```
6. В классе `App\Entity\User` добавляем метод `getFollowers`
    ```php
    /**
     * @return User[]
     */
    public function getFollowers(): array
    {
        return $this->followers->toArray();
    }
    ```
7. Исправляем класс `App\DTO\ManageUserDTO`
    ```php
    <?php
    
    namespace App\DTO;
    
    use Symfony\Component\Validator\Constraints as Assert;
    use App\Entity\User;
    
    class ManageUserDTO
    {
        public function __construct(
            #[Assert\NotBlank]
            #[Assert\Length(max: 32)]
            public string $login = '',
    
            #[Assert\NotBlank]
            #[Assert\Length(max: 32)]
            public string $password = '',
    
            #[Assert\NotBlank]
            #[Assert\GreaterThan(18)]
            public int $age = 0,
    
            public bool $isActive = false,
    
            #[Assert\Type('array')]
            public array $followers = [],
        ) {
        }
    
        public static function fromEntity(User $user): self
        {
            return new self(...[
                'login' => $user->getLogin(),
                'password' => $user->getPassword(),
                'age' => $user->getAge(),
                'isActive' => $user->isActive(),
                'followers' => array_map(
                    static function (User $user) {
                        return [
                            'id' => $user->getId(),
                            'login' => $user->getLogin(),
                            'password' => $user->getPassword(),
                            'age' => $user->getAge(),
                            'isActive' => $user->isActive(),
                        ];
                    },
                    $user->getFollowers()
                ),
            ]);
        }
    }    
    ```
8. В базе данных добавляем в таблицу `author_follower` связь между двумя пользователями, добавленными ранее в БД.
9. В браузере переходим по адресу `http://localhost:7777/api/v1/user/update-user/ID`, где ID - идентификатор
пользователя-автора из предыдущего пункта
10. Исправляем данные в форме, нажимаем на Submit и видим в БД, что обновились только данные пользователя-автора, но не
подписчика
