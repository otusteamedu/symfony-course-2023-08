# Авторизация и аутентификация 

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем пререквизиты

1. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/security-bundle`
3. Устанавливаем в dev-режиме пакет `symfony/maker-bundle`
4. В файле `config/packages/security.yaml`
    1. Исправляем секцию `providers`
        ```yaml
        providers:
            app_user_provider:
                entity:
                    class: App\Entity\User
                    property: login
        ```
    2. В секции `firewalls.main` заменяем `provider: users_in_memory` на `provider: app_user_provider`
5. В классе `App\Entity\User`
    1. исправляем атрибуты полей `$login` и `$password`
        ```php
        #[ORM\Column(type: 'string', length: 32, unique: true, nullable: false)]
        private string $login;

        #[ORM\Column(type: 'string', length: 120, nullable: false)]
        private string $password;
        ```
    2. добавляем поле `$roles`, а также геттер и сеттер для него
        ```php
        #[ORM\Column(type: 'json', length: 1024, nullable: false)]
        private array $roles = [];

        /**
         * @return string[]
         */
        public function getRoles(): array
        {
            $roles = $this->roles;
            // guarantee every user at least has ROLE_USER
            $roles[] = 'ROLE_USER';

            return array_unique($roles);
        }

        /**
         * @param string[] $roles
         */
        public function setRoles(array $roles): void
        {
            $this->roles = $roles;
        }
        ```
    3. имплементируем `Symfony\Component\Security\Core\User\UserInterface`
        ```php
        public function eraseCredentials(): void
        {
        }
     
        public function getUserIdentifier(): string
        {
            return $this->login;
        }
        ``` 
    4. имплементируем `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface` (нужный метод уже есть)
    5. Исправляем метод `toArray`
        ```php
        #[ArrayShape([
            'id' => 'int|null',
            'login' => 'string',
            'password' => 'string',
            'roles' => 'string[]',
            'createdAt' => 'string',
            'updatedAt' => 'string',
            'tweets' => ['id' => 'int|null', 'login' => 'string', 'createdAt' => 'string', 'updatedAt' => 'string'],
            'followers' => 'string[]',
            'authors' => 'string[]',
            'subscriptionFollowers' =>  ['subscriptionId' => 'int|null', 'userId' => 'int|null', 'login' => 'string'],
            'subscriptionAuthors' =>  ['subscriptionId' => 'int|null', 'userId' => 'int|null', 'login' => 'string'],
        ])]
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login,
                'password' => $this->password,
                'roles' => $this->getRoles(),
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getLogin(), 'login' => $user->getLogin()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscription_id' => $subscription->getId(),
                        'user_id' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscription_id' => $subscription->getId(),
                        'user_id' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }
        ```
6. Генерируем миграцию командой `php bin/console doctrine:migrations:diff`
7. Выполняем миграцию командой `php bin/console doctrine:migrations:migrate`
8. Исправляем класс `App\DTO\ManageUserDTO`
    ```php
    <?php
    
    namespace App\DTO;
    
    use Symfony\Component\HttpFoundation\Request;
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
    
            #[Assert\Type('array')]
            public array $roles = []
        ) {
        }
    
        public static function fromEntity(User $user): self
        {
            return new self(...[
                'login' => $user->getLogin(),
                'password' => $user->getPassword(),
                'age' => $user->getAge(),
                'isActive' => $user->isActive(),
                'roles' => $user->getRoles(),
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
    
        public static function fromRequest(Request $request): self
        {
            return new self(
                login: $request->request->get('login') ?? $request->query->get('login'),
                password: $request->request->get('password') ?? $request->query->get('password'),
                age: $request->request->get('age') ?? $request->query->get('age'),
                isActive: $request->request->get('isActive') ?? $request->query->get('isActive'),
                roles: $request->request->get('roles') ?? $request->query->get('roles') ?? [],
            );
        }
    }
    ```
9. В классе `App\Manager\UserManager`
    1. добавляем инъекцию `UserPasswordEncoderInterface`
        ```php
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
        ) {
        }
        ```
    2. Исправляем метод `saveUserFromDTO`
        ```php
        public function saveUserFromDTO(User $user, ManageUserDTO $manageUserDTO): ?int
        {
            $user->setLogin($manageUserDTO->login);
            $user->setPassword($this->userPasswordHasher->hashPassword($user, $manageUserDTO->password));
            $user->setAge($manageUserDTO->age);
            $user->setIsActive($manageUserDTO->isActive);
            $user->setRoles($manageUserDTO->roles);
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            return $user->getId();
        }
        ```
10. Добавляем класс `App\Controller\Api\v3\UserController`
     ```php
     <?php
    
     namespace App\Controller\Api\v3;
    
     use App\DTO\ManageUserDTO;
     use App\Entity\User;
     use App\Manager\UserManager;
     use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
     use Symfony\Component\HttpFoundation\JsonResponse;
     use Symfony\Component\HttpFoundation\Request;
     use Symfony\Component\HttpFoundation\Response;
     use Symfony\Component\Routing\Annotation\Route;
    
     #[Route(path: 'api/v3/user')]
     class UserController extends AbstractController
     {
         public function __construct(private readonly UserManager $userManager)
         {
         }
    
         #[Route(path: '', methods: ['POST'])]
         public function saveUserAction(Request $request): Response
         {
             $saveUserDTO = ManageUserDTO::fromRequest($request);
             $userId = $this->userManager->saveUserFromDTO(new User(), $saveUserDTO);
             [$data, $code] = $userId === null ?
                 [['success' => false], Response::HTTP_BAD_REQUEST] :
                 [['success' => true, 'userId' => $userId], Response::HTTP_OK];
    
             return new JsonResponse($data, $code);
         }
    
         #[Route(path: '', methods: ['GET'])]
         public function getUsersAction(Request $request): Response
         {
             $perPage = $request->query->get('perPage');
             $page = $request->query->get('page');
             $users = $this->userManager->getUsers($page ?? 0, $perPage ?? 20);
             $code = empty($users) ? Response::HTTP_NO_CONTENT : Response::HTTP_OK;
    
             return new JsonResponse(['users' => array_map(static fn(User $user) => $user->toArray(), $users)], $code);
         }
    
         #[Route(path: '', methods: ['DELETE'])]
         public function deleteUserAction(Request $request): Response
         {
             $userId = $request->query->get('userId');
             $result = $this->userManager->deleteUserById($userId);
    
             return new JsonResponse(['success' => $result], $result ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
         }
    
         #[Route(path: '', methods: ['PATCH'])]
         public function updateUserAction(Request $request): Response
         {
             $userId = $request->query->get('userId');
             $login = $request->request->get('login');
             $result = $this->userManager->updateUserLogin($userId, $login);
    
             return new JsonResponse(['success' => $result], $result ? Response::HTTP_OK : Response::HTTP_BAD_REQUEST);
         }
     }
     ```
11. Выполняем запрос Add user v3 из Postman-коллекции v3, видим, что пользователь добавлен в БД и пароль захэширован

## Добавляем форму логина 

1. В файле `config/packages/security.yaml` в секции `firewall.main` добавляем `security:false`
2. Генерируем форму логина `php bin/console make:auth`
     1. Выбираем `Login form authenticator`
     2. Указываем имя класса для аутентификатора `AppLoginAuthenticator` и контроллера `LoginController`
     3. Не создаём `/logout URL`
3. В файле `src/templates/security/login.html.twig` зависимость от базового шаблона `layout.twig`
4. Переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, которого создали при проверке
    API. Видим, что после нажатия на `Sign in` ничего не происходит.

## Включаем security 

1. Убираем в файле `config/packages/security.yaml` в секции `firewall.main` строку `security:false`
2. Ещё раз переходим по адресу `http://localhost:7777/login` и вводим логин/пароль пользователя, после нажатия на
    `Sign in` получаем ошибку

## Добавляем перенаправление
 
1. В классе `App\Security\AppLoginAuthenticator` исправляем метод `onAuthenticationSuccess`
    ```php
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_api_v3_user_getusers'));
    }
    ```
2. Проверяем, что всё заработало

## Добавляем авторизацию для ROLE_ADMIN

1. В файле `config/packages/security.yaml` в секцию `access_control` добавляем условие
     ```yaml
     - { path: ^/api/v3/user, roles: ROLE_ADMIN, methods: [DELETE] }
     ```
2. Выполняем запрос Add user v3 из Postman-коллекции v3 с другим значением логина, запоминаем id добавленного 
   пользователя
3. Выполняем запрос Delete user v3 из Postman-коллекции v3 с userId добавленного пользователя, добавив Cookie
   `PHPSESSID`, которую можно посмотреть в браузере после успешного логина. Проверяем, что возвращается ответ 403 с
   сообщением `Access denied`
4. Добавляем роль `ROLE_ADMIN` пользователю в БД, перелогиниваемся, чтобы получить корректную сессию и проверяем, что
    стал возвращаться ответ 200 и пользователь удалён из БД

## Добавляем авторизацию для ROLE_VIEW
 
1. В файле `config/packages/security.yaml` в секции `access_control` добавляем условие
     ```yaml
     - { path: ^/api/v3/user, roles: ROLE_VIEW, methods: [GET] }
     ```
2. Выполняем запрос Get user list v3 из Postman-коллекции v3. Проверяем, что возвращается ответ 403 с сообщением
    `Access denied`
 
## Добавляем иерархию ролей 

1. Добавляем в файл `config/packages/security.yaml` секцию `role_hierarchy`
     ```yaml
     role_hierarchy:
         ROLE_ADMIN: ROLE_VIEW
     ```
2. Ещё раз выполняем запрос Get user list v3 из Postman-коллекции v3. Проверяем, что возвращается ответ 200
 
## Добавляем Voter 

1. Добавляем класс `App\Security\Voter\UserVoter`
     ```php
     <?php
    
     namespace App\Security\Voter;
    
     use App\Entity\User;
     use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
     use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
     class UserVoter extends Voter
     {
         public const DELETE = 'delete';
    
         protected function supports(string $attribute, $subject): bool
         {
             return $attribute === self::DELETE && ($subject instanceof User);
         }
    
         protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
         {
             $user = $token->getUser();
             if (!$user instanceof User) {
                 return false;
             }
    
             /** @var User $subject */
             return $user->getId() !== $subject->getId();
         }
     }
     ```
2. В классе `App\Controller\Api\v3\UserController`
     1. добавляем инъекцию `Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface`
         ```php
         public function __construct(
             private readonly UserManager $userManager,
             private readonly AuthorizationCheckerInterface $authorizationChecker,
         )
         {
         }
         ```
     2. Исправляем метод `deleteUserAction`
         ```php
         #[Route(path: '', methods: ['DELETE'])]
         public function deleteUserAction(Request $request): Response
         {
             $userId = $request->query->get('userId');
             $user = $this->userManager->getUserById($userId);
             if (!$this->authorizationChecker->isGranted(UserVoter::DELETE, $user)) {
                 return new JsonResponse('Access denied', Response::HTTP_FORBIDDEN);
             }
             $result = $this->userManager->deleteUserById($userId);
     
             return new JsonResponse(['success' => $result], $result ? Response::HTTP_OK : Response::HTTP_NOT_FOUND);
         }
         ```
3. Выполняем запрос Add user v3 из Postman-коллекции v3 с новым значением логина, запоминаем id добавленного
   пользователя
4. Выполняем запрос Delete user v3 из Postman-коллекции v3 сначала с идентификатором добавленного пользователя, потом
   с идентификатором залогиненного пользователя. Проверяем, что в первом случае ответ 200, во втором - 403
 
## Изменяем стратегию для Voter'ов 

1. Выполняем запрос Add user v3 из Postman-коллекции v3 с новым значением логина, запоминаем id добавленного
   пользователя
2. В файл `config/packages/security.yaml` добавляем секцию `access_decision_manager`
     ```yaml
     access_decision_manager:
         strategy: consensus
     ```
3. Добавляем класс `App\Security\Voter\FakeUserVoter`
     ```php
     <?php
    
     namespace App\Security\Voter;
    
     use App\Entity\User;
     use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
     use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
     class FakeUserVoter extends Voter
     {
         protected function supports(string $attribute, $subject): bool
         {
             return $subject instanceof User;
         }
    
         protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
         {
             return false;
         }
     }
     ```
4. Добавляем класс `App\Security\Voter\DummyUserVoter`
     ```php
     <?php
    
     namespace App\Security\Voter;
        
     use App\Entity\User;
     use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
     use Symfony\Component\Security\Core\Authorization\Voter\Voter;
    
     class DummyUserVoter extends Voter
     {
         protected function supports(string $attribute, $subject): bool
         {
             return $subject instanceof User;
         }
    
         protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
         {
             return false;
         }
     }
     ```
5. Проверяем, что удалить добавленного пользователя тоже больше нельзя
