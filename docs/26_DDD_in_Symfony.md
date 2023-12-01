# Symfony Messenger

Запускаем контейнеры командой `docker-compose up -d`

### Добавляем синхронную команду

1. Добавляем класс `App\Domain\Command\CreateUserCommand`
    ```php
    <?php
    
    namespace App\Domain\Command\CreateUser;
    
    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use JMS\Serializer\Annotation as JMS;
    
    final class CreateUserCommand
    {
        private function __construct(
            private readonly string $login,
            private readonly string $password,
            /** @JMS\Type("array<string>") */
            private readonly array $roles,
            private readonly int $age,
            private readonly bool $isActive,
        ) {
        }
    
        public function getLogin(): string
        {
            return $this->login;
        }
    
        public function getPassword(): string
        {
            return $this->password;
        }
    
        public function getRoles(): array
        {
            return $this->roles;
        }
    
        public function getAge(): int
        {
            return $this->age;
        }
    
        public function isActive(): bool
        {
            return $this->isActive;
        }
    
        public static function createFromRequest(CreateUserDTO $request): self
        {
            return new self(
                $request->login,
                $request->password,
                $request->roles,
                $request->age,
                $request->isActive,
            );
        }
    }
    ```
2. Добавляем класс `App\Domain\Command\CreateUser\Handler`
    ```php
    <?php
    
    namespace App\Domain\Command\CreateUser;
    
    use App\Entity\User;
    use Doctrine\ORM\EntityManagerInterface;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
        ) {
        }
    
        public function __invoke(CreateUserCommand $command): void
        {
            $user = new User();
            $user->setLogin($command->getLogin());
            $user->setPassword($command->getPassword());
            $user->setRoles($command->getRoles());
            $user->setAge($command->getAge());
            $user->setIsActive($command->isActive());
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }
    }
    ```
3. В файле `config/packages/messenger.yaml` исправляем секцию `messenger.routing`
    ```yaml
    App\DTO\AddFollowersDTO: add_followers
    FeedBundle\DTO\SendNotificationDTO: doctrine
    App\DTO\SendNotificationAsyncDTO: send_notification
    App\Domain\Command\CreateUser\CreateUserCommand: sync
    ```
4. Исправляем класс `App\Controller\Api\CreateUser\v5\CreateUserAction`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5;
    
    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
    use App\Controller\Common\ErrorResponseTrait;
    use App\Domain\Command\CreateUser\CreateUserCommand;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use Nelmio\ApiDocBundle\Annotation\Model;
    use OpenApi\Annotations as OA;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
    use Symfony\Component\Messenger\MessageBusInterface;
    
    class CreateUserAction extends AbstractFOSRestController
    {
        use ErrorResponseTrait;
    
        public function __construct(private readonly MessageBusInterface $messageBus)
        {
        }
    
        #[Rest\Post(path: '/api/v5/users')]
        /**
         * @OA\Post(
         *     operationId="addUser",
         *     tags={"Пользователи"},
         *     @OA\RequestBody(
         *         description="Input data format",
         *         @OA\JsonContent(ref=@Model(type=CreateUserDTO::class))
         *     ),
         *     @OA\Response(
         *         response=200,
         *         description="Success",
         *         @OA\JsonContent(ref=@Model(type=UserIsCreatedDTO::class))
         *     )
         * )
         */
        public function saveUserAction(#[MapRequestPayload] CreateUserDTO $request): Response
        {
            $this->messageBus->dispatch(CreateUserCommand::createFromRequest($request));
            
            return $this->handleView($this->view(['success' => true]));
        }
    }
    ```
5. В классе `config/services.yaml` убираем описание сервиса `App\Controller\Api\CreateUser\v5\CreateUserAction`
6. Выполняем запрос Add user v5 из Postman-коллекции v10. Видим успешный ответ, проверяем, что запись в БД создалась.

### Возвращаем ответ от команды

1. В классе `\App\Controller\Api\CreateUser\v5\CreateUserAction` исправляем мето `saveUserAction`
    ```php
    public function saveUserAction(#[MapRequestPayload] CreateUserDTO $request): Response
    {
        $envelope = $this->messageBus->dispatch(CreateUserCommand::createFromRequest($request));
        /** @var HandledStamp|null $handledStamp */
        $handledStamp = $envelope->last(HandledStamp::class);
        [$data, $code] = ($handledStamp?->getResult() === null) ? [['success' => false], 400] : [['userId' => $handledStamp?->getResult()], 200];

        return $this->handleView($this->view($data, $code));
    }
    ```
2. В классе `App\Domain\Command\CreateUser\Handler` исправляем метод `__invoke`
    ```php
    public function __invoke(CreateUserCommand $command): int
    {
        $user = new User();
        $user->setLogin($command->getLogin());
        $user->setPassword($command->getPassword());
        $user->setRoles($command->getRoles());
        $user->setAge($command->getAge());
        $user->setIsActive($command->isActive());
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        
        return $user->getId();
    }
    ```
3. Выполняем запрос Add user v5 из Postman-коллекции v10. Видим в ответе идентификатор пользователя и то, что запись в
   БД создалась.

### Проверяем ответ от асинхронной команды

1. В файле `config/packages/messenger.yaml`
   1. Добавляем новый транспорт в секцию `messenger.transports`
        ```yaml
        create_user:
            dsn: "%env(MESSENGER_AMQP_TRANSPORT_DSN)%"
            options:
                exchange:
                    name: 'old_sound_rabbit_mq.create_user'
                    type: direct
        ```
   2. Исправляем секцию `messenger.routing`
        ```yaml
        App\DTO\AddFollowersDTO: add_followers
        FeedBundle\DTO\SendNotificationDTO: doctrine
        App\DTO\SendNotificationAsyncDTO: send_notification
        App\Domain\Command\CreateUser\CreateUserCommand: create_user
        ```
2. В файл `supervisor/consumer.conf` добавляем новую секцию
    ```ini
    [program:create_user]
    command=php /app/bin/console messenger:consume create_user --limit=1000 --env=dev -vv
    process_name=create_user_%(process_num)02d
    numprocs=1
    directory=/tmp
    autostart=true
    autorestart=true
    startsecs=3
    startretries=10
    user=www-data
    redirect_stderr=false
    stdout_logfile=/app/var/log/supervisor.create_user.out.log
    stdout_capture_maxbytes=1MB
    stderr_logfile=/app/var/log/supervisor.create_user.error.log
    stderr_capture_maxbytes=1MB
    ```
3. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
4. Выполняем запрос Add user v5 из Postman-коллекции v10. Видим ответ `success: false`, но запись в БД создалась.

## Добавляем шину запросов

1. Добавляем класс `App\Service\QueryInterface`
    ```php
    <?php
    
    namespace App\Service;
    
    /**
     * @template T
     */
    interface QueryInterface
    {
    }
    ```
2. Добавляем класс `App\Service\QueryBusInterface`
    ```php
    <?php
    
    namespace App\Service;
    
    interface QueryBusInterface
    {
        /**
         * @template T
         *
         * @param QueryInterface<T> $query
         *
         * @return T
         */
        public function query(QueryInterface $query);
    }
    ```
3. Добавляем класс `App\Service\QueryBus`
    ```php
    <?php
    
    namespace App\Service;
    
    use Symfony\Component\Messenger\MessageBusInterface;
    use Symfony\Component\Messenger\Stamp\HandledStamp;
    
    class QueryBus implements QueryBusInterface
    {
        public function __construct(
            private readonly MessageBusInterface $baseQueryBus
        ) {
        }
    
        /**
         * @return mixed
         */
        public function query(QueryInterface $query)
        {
            $envelope = $this->baseQueryBus->dispatch($query);
            /** @var HandledStamp|null $handledStamp */
            $handledStamp = $envelope->last(HandledStamp::class);
            
            return $handledStamp?->getResult();
        }
    }
    ```
4. Добавляем класс `App\Domain\Query\GetFeed\GetFeedQuery`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use App\Service\QueryInterface;

    /**
     * @implements QueryInterface<GetFeedQueryResult>
     */
    class GetFeedQuery implements QueryInterface
    {
        public function __construct(
            private readonly int $userId,
            private readonly int $count,
        ) {
        }
    
        public function getUserId(): int
        {
            return $this->userId;
        }
    
        public function getCount(): int
        {
            return $this->count;
        }
    }
    ```
5. Добавляем класс `App\Domain\Query\GetFeed\GetFeedQueryResult`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use JMS\Serializer\Annotation as JMS;
    
    class GetFeedQueryResult
    {
        public function __construct(
           /** @JMS\Type("array") */
           private readonly array $tweets, 
        ) {
        }
    
        public function getTweets(): array
        {
            return $this->tweets;
        }
    
        public function isEmpty(): bool
        {
            return empty($this->tweets);
        }
    }
    ```
6. Добавляем класс `App\Domain\Query\GetFeed\Handler`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use FeedBundle\Service\FeedService;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(
            private readonly FeedService $feedService,
        ) {
        }
    
        public function __invoke(GetFeedQuery $query): GetFeedQueryResult
        {
            return new GetFeedQueryResult(
                $this->feedService->getFeed($query->getUserId(), $query->getCount())
            );
        }
    }
    ```
7. Исправляем класс `App\Controller\Api\GetFeed\v1\Controller`
    ```php
    <?php
    
    namespace App\Controller\Api\GetFeed\v1;
    
    use App\Domain\Query\GetFeed\GetFeedQuery;
    use App\Domain\Query\GetFeed\GetFeedQueryResult;
    use App\Service\QueryBusInterface;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations as Rest;
    use FOS\RestBundle\View\View;
    use Symfony\Component\HttpFoundation\Response;
    
    class Controller extends AbstractFOSRestController
    {
        /** @var int */
        private const DEFAULT_FEED_SIZE = 20;
    
        /**
         * @param QueryBusInterface<GetFeedQueryResult> $queryBus
         */
        public function __construct(
            private readonly QueryBusInterface $queryBus
        )
        {
        }
    
        #[Rest\Get('/api/v1/get-feed')]
        #[Rest\QueryParam(name: 'userId', requirements: '\d+')]
        #[Rest\QueryParam(name: 'count', requirements: '\d+', nullable: true)]
        public function getFeedAction(int $userId, ?int $count = null): View
        {
            $count = $count ?? self::DEFAULT_FEED_SIZE;
            $result = $this->queryBus->query(new GetFeedQuery($userId, $count));
    
            return View::create($result, $result->isEmpty() ? Response::HTTP_NO_CONTENT : Response::HTTP_OK);
        }
    }
    ```
8. Выполняем запрос Add followers из Postman-коллекции v10, чтобы получить подписчиков.
9. Выполняем запрос Post tweet из Postman-коллекции v10, дожидаемся обновления лент.
10. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика, видим твит.
11. Выполняем запрос Get feed из Postman-коллекции v10 для автора, видим пустой ответ с кодом 204.

### Добавляем ValueObject

1. Добавляем класс `App\Domain\ValueObject\ValueObjectInterface`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    interface ValueObjectInterface
    {
        public function equals(ValueObjectInterface $other): bool;
    
        public function getValue(): mixed;
    }
    ```
2. Добавляем класс `App\Domain\ValueObject\AbstractValueObjectString`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    use JsonSerializable;
    
    abstract class AbstractValueObjectString implements ValueObjectInterface, JsonSerializable
    {
        private readonly string $value;
    
        final public function __construct(string $value)
        {
            $this->validate($value);
    
            $this->value = $this->transform($value);
        }
    
        public function __toString(): string
        {
            return $this->value;
        }
    
        public static function fromString(string $value): static
        {
            return new static($value);
        }
    
        public function equals(ValueObjectInterface $other): bool
        {
            return get_class($this) === get_class($other) && $this->getValue() === $other->getValue();
        }
    
        public function getValue(): string
        {
            return $this->value;
        }
    
        public function jsonSerialize(): string
        {
            return $this->value;
        }
    
        protected function validate(string $value): void
        {
        }
    
        protected function transform(string $value): string
        {
            return $value;
        }
    }
    ```
3. Добавляем класс `App\Domain\ValueObject\UserLogin`
    ```php
    <?php
    
    namespace App\Domain\ValueObject;
    
    class UserLogin extends AbstractValueObjectString
    {
    }
    ```
4. Добавляем класс `App\Doctrine\AbstractStringType`
    ```php
    <?php
    
    namespace App\Doctrine;
    
    use App\Domain\ValueObject\AbstractValueObjectString;
    use Doctrine\DBAL\Platforms\AbstractPlatform;
    use Doctrine\DBAL\Types\ConversionException;
    use Doctrine\DBAL\Types\Type;
    
    abstract class AbstractStringType extends Type
    {
        abstract protected function getConcreteValueObjectType(): string;
    
        public function convertToPHPValue($value, AbstractPlatform $platform): ?AbstractValueObjectString
        {
            if ($value === null) {
                return null;
            }
    
            if (is_string($value)) {
                /** @var AbstractValueObjectString $concreteValueObjectType */
                $concreteValueObjectType = $this->getConcreteValueObjectType();
    
                return $concreteValueObjectType::fromString($value);
            }
    
            /** @psalm-suppress MixedArgument */
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    
        public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
        {
            if ($value === null) {
                return null;
            }
    
            if ($value instanceof AbstractValueObjectString) {
                return $value->getValue();
            }
    
            /** @psalm-suppress MixedArgument */
            throw ConversionException::conversionFailed($value, $this->getName());
        }
    
        public function requiresSQLCommentHint(AbstractPlatform $platform): bool
        {
            return true;
        }
    
        public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
        {
            return $platform->getStringTypeDeclarationSQL($column);
        }
    }
    ```
5. Добавляем класс `App\Domain\ValueObject\UserLogin`
    ```php
    <?php
    
    namespace App\Doctrine;
    
    use App\Domain\ValueObject\UserLogin;
    
    class UserLoginType extends AbstractStringType
    {
        public function getName()
        {
            return 'userLogin';
        }
    
        protected function getConcreteValueObjectType(): string
        {
            return UserLogin::class;
        }
    }
    ```
6. В файле `config/packages/doctrine.yaml` исправляем секцию `doctrine.dbal`
    ```yaml
    dbal:
        url: '%env(resolve:DATABASE_URL)%'
        types:
            'userLogin': App\Doctrine\UserLoginType

    ```
7. Исправляем класс `App\Entity\User`
    ```php
    <?php
    
    namespace App\Entity;
    
    use App\Domain\ValueObject\UserLogin;
    use App\Repository\UserRepository;
    use DateTime;
    use Doctrine\Common\Collections\ArrayCollection;
    use Doctrine\Common\Collections\Collection;
    use Doctrine\ORM\Mapping as ORM;
    use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
    use Symfony\Component\Security\Core\User\UserInterface;
    use Symfony\Component\Validator\Constraints as Assert;
    use Gedmo\Mapping\Annotation as Gedmo;
    use JMS\Serializer\Annotation as JMS;
    
    #[ORM\Table(name: '`user`')]
    #[ORM\Entity(repositoryClass: UserRepository::class)]
    class User implements HasMetaTimestampsInterface, UserInterface, PasswordAuthenticatedUserInterface
    {
        public const EMAIL_NOTIFICATION = 'email';
        public const SMS_NOTIFICATION = 'sms';
    
        #[ORM\Column(name: 'id', type: 'bigint', unique: true)]
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        #[JMS\Groups(['user-id-list'])]
        private ?int $id = null;
    
        #[ORM\Column(type: 'userLogin', length: 32, unique: true, nullable: false)]
        #[JMS\Groups(['video-user-info', 'elastica'])]
        private UserLogin $login;
    
        #[Gedmo\Timestampable(on: 'create')]
        #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
        private DateTime $createdAt;
    
        #[Gedmo\Timestampable(on: 'update')]
        #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
        private DateTime $updatedAt;
    
        #[ORM\OneToMany(mappedBy: 'author', targetEntity: 'Tweet')]
        private Collection $tweets;
    
        #[ORM\ManyToMany(targetEntity: 'User', mappedBy: 'followers')]
        private Collection $authors;
    
        #[ORM\ManyToMany(targetEntity: 'User', inversedBy: 'authors')]
        #[ORM\JoinTable(name: 'author_follower')]
        #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
        #[ORM\InverseJoinColumn(name: 'follower_id', referencedColumnName: 'id')]
        private Collection $followers;
    
        #[ORM\OneToMany(mappedBy: 'follower', targetEntity: 'Subscription')]
        private Collection $subscriptionAuthors;
    
        #[ORM\OneToMany(mappedBy: 'author', targetEntity: 'Subscription')]
        private Collection $subscriptionFollowers;
    
        #[ORM\Column(type: 'string', length: 120, nullable: false)]
        #[JMS\Exclude]
        private string $password;
    
        #[Assert\NotBlank]
        #[Assert\GreaterThan(18)]
        #[ORM\Column(type: 'integer', nullable: false)]
        #[JMS\Groups(['video-user-info', 'elastica'])]
        private int $age;
    
        #[ORM\Column(type: 'boolean', nullable: false)]
        #[JMS\Groups(['video-user-info'])]
        #[JMS\SerializedName('isActive')]
        private bool $isActive;
    
        #[ORM\Column(type: 'json', length: 1024, nullable: false)]
        private array $roles = [];
    
        #[ORM\Column(type: 'string', length: 32, unique: true, nullable: true)]
        private ?string $token = null;
    
        #[ORM\Column(type: 'string', length: 11, nullable: true)]
        #[JMS\Groups(['elastica'])]
        private ?string $phone = null;
    
        #[ORM\Column(type: 'string', length: 128, nullable: true)]
        #[JMS\Groups(['elastica'])]
        private ?string $email = null;
    
        #[ORM\Column(type: 'string', length: 10, nullable: true)]
        #[JMS\Groups(['elastica'])]
        private ?string $preferred = null;
    
        public function __construct()
        {
            $this->tweets = new ArrayCollection();
            $this->authors = new ArrayCollection();
            $this->followers = new ArrayCollection();
            $this->subscriptionAuthors = new ArrayCollection();
            $this->subscriptionFollowers = new ArrayCollection();
        }
    
        public function getId(): int
        {
            return $this->id;
        }
    
        public function setId(int $id): void
        {
            $this->id = $id;
        }
    
        public function getLogin(): UserLogin
        {
            return $this->login;
        }
    
        public function setLogin(UserLogin $login): void
        {
            $this->login = $login;
        }
    
        public function getCreatedAt(): DateTime {
            return $this->createdAt;
        }
    
        public function setCreatedAt(): void {
            $this->createdAt = new DateTime();
        }
    
        public function getUpdatedAt(): DateTime {
            return $this->updatedAt;
        }
    
        public function setUpdatedAt(): void {
            $this->updatedAt = new DateTime();
        }
    
        public function addTweet(Tweet $tweet): void
        {
            if (!$this->tweets->contains($tweet)) {
                $this->tweets->add($tweet);
            }
        }
    
        public function addFollower(User $follower): void
        {
            if (!$this->followers->contains($follower)) {
                $this->followers->add($follower);
            }
        }
    
        public function addAuthor(User $author): void
        {
            if (!$this->authors->contains($author)) {
                $this->authors->add($author);
            }
        }
    
        public function addSubscriptionAuthor(Subscription $subscription): void
        {
            if (!$this->subscriptionAuthors->contains($subscription)) {
                $this->subscriptionAuthors->add($subscription);
            }
        }
    
        public function addSubscriptionFollower(Subscription $subscription): void
        {
            if (!$this->subscriptionFollowers->contains($subscription)) {
                $this->subscriptionFollowers->add($subscription);
            }
        }
    
        public function toArray(): array
        {
            return [
                'id' => $this->id,
                'login' => $this->login->getValue(),
                'password' => $this->password,
                'roles' => $this->getRoles(),
                'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
                'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
                'tweets' => array_map(static fn(Tweet $tweet) => $tweet->toArray(), $this->tweets->toArray()),
                'followers' => array_map(
                    static fn(User $user) => ['id' => $user->getId(), 'login' => $user->getLogin()->getValue()],
                    $this->followers->toArray()
                ),
                'authors' => array_map(
                    static fn(User $user) => ['id' => $user->getLogin(), 'login' => $user->getLogin()->getValue()],
                    $this->authors->toArray()
                ),
                'subscriptionFollowers' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscription_id' => $subscription->getId(),
                        'user_id' => $subscription->getFollower()->getId(),
                        'login' => $subscription->getFollower()->getLogin()->getValue(),
                    ],
                    $this->subscriptionFollowers->toArray()
                ),
                'subscriptionAuthors' => array_map(
                    static fn(Subscription $subscription) => [
                        'subscription_id' => $subscription->getId(),
                        'user_id' => $subscription->getAuthor()->getId(),
                        'login' => $subscription->getAuthor()->getLogin()->getValue(),
                    ],
                    $this->subscriptionAuthors->toArray()
                ),
            ];
        }
    
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
    
        /**
         * @return User[]
         */
        public function getFollowers(): array
        {
            return $this->followers->toArray();
        }
    
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
    
        public function getSalt(): ?string
        {
            return null;
        }
    
        public function eraseCredentials(): void
        {
        }
    
        public function getUsername(): string
        {
            return $this->login->getValue();
        }
    
        public function getUserIdentifier(): string
        {
            return $this->login->getValue();
        }
    
        public function getToken(): ?string
        {
            return $this->token;
        }
    
        public function setToken(?string $token): void
        {
            $this->token = $token;
        }
    
        public function getPhone(): ?string
        {
            return $this->phone;
        }
    
        public function setPhone(?string $phone): void
        {
            $this->phone = $phone;
        }
    
        public function getEmail(): ?string
        {
            return $this->email;
        }
    
        public function setEmail(?string $email): void
        {
            $this->email = $email;
        }
    
        public function getPreferred(): ?string
        {
            return $this->preferred;
        }
    
        public function setPreferred(?string $preferred): void
        {
            $this->preferred = $preferred;
        }
    }
    ```
8. В классе `App\DTO\ManageUserDTO` исправляем метод `fromEntity`
    ```php
    public static function fromEntity(User $user): self
    {
        return new self(...[
            'login' => $user->getLogin()->getValue(),
            'password' => $user->getPassword(),
            'age' => $user->getAge(),
            'isActive' => $user->isActive(),
            'roles' => $user->getRoles(),
            'followers' => array_map(
                static function (User $user) {
                    return [
                        'id' => $user->getId(),
                        'login' => $user->getLogin()->getValue(),
                        'password' => $user->getPassword(),
                        'age' => $user->getAge(),
                        'isActive' => $user->isActive(),
                    ];
                },
                $user->getFollowers()
            ),
            'phone' => $user->getPhone(),
            'email' => $user->getEmail(),
            'preferred' => $user->getPreferred(),
        ]);
    }
    ```
9. В классе `App\Controller\Api\CreateUser\v5\CreateUserManager` исправляем метод `getUser`
    ```php
    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
    {
        $this->statsdAPIClient->increment('save_user_v5_attempt');

        $user = new User();
        $user->setLogin(UserLogin::fromString($saveUserDTO->login));
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $saveUserDTO->password));
        $user->setRoles($saveUserDTO->roles);
        $user->setAge($saveUserDTO->age);
        $user->setIsActive($saveUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CreateUserEvent($user->getLogin()->getValue()));

        $result = new UserIsCreatedDTO();
        $context = (new SerializationContext())->setGroups(['video-user-info', 'user-id-list']);
        $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));

        return $result;
    }
    ```
10. Исправляем класс `App\Domain\Command\CreateUser\CreateUserCommand`
     ```php
     <?php
    
     namespace App\Domain\Command\CreateUser;
    
     use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
     use App\Domain\ValueObject\UserLogin;
     use JMS\Serializer\Annotation as JMS;
    
     final class CreateUserCommand
     {
         private function __construct(
             private readonly UserLogin $login,
             private readonly string $password,
             /** @JMS\Type("array<string>") */
             private readonly array $roles,
             private readonly int $age,
             private readonly bool $isActive,
         ) {
         }
    
         public function getLogin(): UserLogin
         {
             return $this->login;
         }
    
         public function getPassword(): string
         {
             return $this->password;
         }
    
         public function getRoles(): array
         {
             return $this->roles;
         }
    
         public function getAge(): int
         {
             return $this->age;
         }
    
         public function isActive(): bool
         {
             return $this->isActive;
         }
    
         public static function createFromRequest(CreateUserDTO $request): self
         {
             return new self(
                 new UserLogin($request->login),
                 $request->password,
                 $request->roles,
                 $request->age,
                 $request->isActive,
             );
         }
     }
     ```
11. Перезапускаем контейнер супервизора командой `docker-compose restart supervisor`
12. Выполняем запрос Add user v5 из Postman-коллекции v10. Видим, что запись в БД создалась.
13. Выполняем запрос Get users list из Postman-коллекции v10, видим ошибку
14. Очищаем кэш метаданных Doctrine командой `php bin/console doctrine:cache:clear-metadata`
15. Ещё раз выполняем запрос Get users list из Postman-коллекции v10, видим созданного пользователя.

### Устанавливаем deptrac

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Устанавливаем deptrac командой `composer require qossmic/deptrac-shim --dev`
3. Исправляем файл `deptrac.yaml`
    ```yaml
    parameters:
      paths:
        - ./src
      exclude_files: []
      layers:
        - name: Controller
          collectors:
            - type: className
              regex: ^App\\Controller\\GetFeed\\.*
        - name: Domain
          collectors:
            - type: className
              regex: ^App\\Domain\\.*
        - name: Service
          collectors:
            - type: className
              regex: ^App\\Service\\.*
            - type: className
              regex: ^FeedBundle\\Service\\.*
      ruleset:
        Controller:
          - Domain
          - Service
        Domain:
        Service:
          - Domain
    ```
4. Запускаем `deptrac` командой `vendor/bin/deptrac --clear-cache`, видим 4 ошибки

### Исправляем зависимости

1. Переносим класс `App\Service\QueryInterface` в пространство имён `App\Domain\Query`
2. Исправляем класс `App\Domain\Query\GetFeed\Handler`
    ```php
    <?php
    
    namespace App\Domain\Query\GetFeed;
    
    use Doctrine\ORM\EntityManagerInterface;
    use FeedBundle\Entity\Feed;
    use Symfony\Component\Messenger\Attribute\AsMessageHandler;
    
    #[AsMessageHandler]
    class Handler
    {
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
        ) {
        }
    
        public function __invoke(GetFeedQuery $query): GetFeedQueryResult
        {
            $userRepository = $this->entityManager->getRepository(User::class);
            $reader = $userRepository->find($query->getUserId());
            if (!($reader instanceof User)) {
                return new GetFeedQueryResult([]);
            }

            $feedRepository = $this->entityManager->getRepository(Feed::class);
            $feed = $feedRepository->findOneBy(['readerId' => $reader]);

            if ($feed === null) {
                $tweets = [];
            } else {
                $tweets = array_slice($feed->getTweets(), -$query->getCount());
            }

            return new GetFeedQueryResult($tweets);
        }
    }
    ```
3. Выполняем запрос Get feed из Postman-коллекции v10 для любого подписчика, видим твит.
4. Запускаем `deptrac` командой `vendor/bin/deptrac --clear-cache`, видим, что ошибки исправлены
