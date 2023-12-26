# API Platform: погружение

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем создание твита через API API Platform 

1. Подключаемся в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. В классе `App\Entity\Tweet` добавляем атрибут `#[ApiResource]` к классу
3. Выполняем запрос Post Tweet API Platform из Postman-коллекции v11. Видим, что твит записан в БД, но материализация в
   таблицу `Feed` сама не заработает

## Добавляем асинхронную материализацию

1. Добавляем класс `App\ApiPlatform\State\AsyncMessageTweetProcessorDecorator`
    ```php
    <?php
    
    namespace App\ApiPlatform\State;
    
    use ApiPlatform\Metadata\DeleteOperationInterface;
    use ApiPlatform\Metadata\Operation;
    use ApiPlatform\State\ProcessorInterface;
    use App\Entity\Tweet;
    use App\Service\AsyncService;
    
    class AsyncMessageTweetProcessorDecorator implements ProcessorInterface
    {
        public function __construct(
            private readonly ProcessorInterface $persistProcessor,
            private readonly ProcessorInterface $removeProcessor,
            private readonly AsyncService $asyncService,
        ) {
        }
    
        public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
        {
            if ($operation instanceof DeleteOperationInterface) {
                return $this->removeProcessor->process($data, $operation, $uriVariables, $context);
            }
    
            $result = $this->persistProcessor->process($data, $operation, $uriVariables, $context);
            if ($data instanceof Tweet) {
                $this->asyncService->publishToExchange(AsyncService::PUBLISH_TWEET, $result->toAMPQMessage());
            }
            return $result;
        }
    }
    ```
2. В файл `config/services.yaml` добавляем новый декоратор
    ```yaml
    App\ApiPlatform\State\AsyncMessageTweetProcessorDecorator:
        bind:
            $persistProcessor: '@api_platform.doctrine.orm.state.persist_processor'
            $removeProcessor: '@api_platform.doctrine.orm.state.remove_processor'
    ```
3. В классе `App\Entity\Tweet`
   1. Импортируем класс `ApiPlatform\Metadata\Post`
   2. Исправляем атрибут класса `#[ApiResource]`
       ```php
       #[ApiResource(
           operations: [new Post(status: 202, processor: AsyncMessageTweetProcessorDecorator::class)], output: false
       )]
       ```
4. Проверяем по адресу `http://localhost:7777/api-platform`, что для твита остался только запрос на создание
5. Ещё раз выполняем запрос Post Tweet API Platform из Postman-коллекции v11. Видим, что сообщение материализовалось в
   ленты

## Добавляем работу с DTO

1. Добавляем класс `App\DTO\UserDTO`
    ```
    <?php
    
    namespace App\DTO;
    
    class UserDTO
    {
        public string $login;

        public string $email;

        public string $phone;

        public array $followers;

        public array $followed;
    }
    ```
2. Добавляем класс `App\ApiPlatform\State\UserProviderDecorator`
    ```php
    <?php
    
    namespace App\ApiPlatform\State;
    
    use ApiPlatform\Metadata\Operation;
    use ApiPlatform\State\ProviderInterface;
    use App\DTO\UserDTO;
    use App\Entity\Subscription;
    use App\Entity\User;
    
    class UserProviderDecorator implements ProviderInterface
    {
        public function __construct(private readonly ProviderInterface $itemProvider)
        {
        }
    
        public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
        {
            $item = $this->itemProvider->provide($operation, $uriVariables, $context);
    
            if ($item instanceof User) {
                $userDTO = new UserDTO();
                $userDTO->login = $item->getLogin();
                $userDTO->email = $item->getEmail();
                $userDTO->phone = $item->getPhone();
                $userDTO->followers = array_map(
                    static function (Subscription $subscription): string {
                        return $subscription->getFollower()->getLogin();
                    },
                    $item->getSubscriptionFollowers()
                );
                $userDTO->followed = array_map(
                    static function (Subscription $subscription): string {
                        return $subscription->getAuthor()->getLogin();
                    },
                    $item->getFollowed()
                );

                return $userDTO;
            }
       
            return $item;
        }
    }
    ```
3. В файл `config/services.yaml` добавляем новый декоратор
    ```yaml
    App\ApiPlatform\State\UserProviderDecorator:
        bind:
            $itemProvider: '@api_platform.doctrine.orm.state.item_provider'
    ```
4. В классе `App\Entity\User`
    1. добавляем новое поле и геттер
        ```php
        #[ORM\OneToMany(mappedBy: 'follower', targetEntity: 'Subscription')]
        private Collection $followed;
    
        /**
         * @return Subscription[]
         */
        public function getFollowed(): array
        {
            return $this->followed->getValues();
        }
        ```
    2. исправляем конструктор
        ```php
        public function __construct()
        {
            $this->tweets = new ArrayCollection();
            $this->authors = new ArrayCollection();
            $this->followers = new ArrayCollection();
            $this->followed = new ArrayCollection();
            $this->subscriptionAuthors = new ArrayCollection();
            $this->subscriptionFollowers = new ArrayCollection();
        }
        ```
    3. исправляем атрибут `#[ApiResource]` класса
        ```php
        #[ApiResource(
            output: UserDTO::class,
            graphQlOperations: [
                new Query(),
                new QueryCollection(),
                new QueryCollection(
                    resolver: UserCollectionResolver::class,
                    name: 'collectionQuery',
                ),
                new Query(
                    resolver: UserResolver::class,
                    args: [
                        'id' => ['type' => 'Int'],
                        'login' => ['type' => 'String'],
                    ],
                    read: false,
                    name: 'itemQuery',
                )
            ],
            provider: UserProviderDecorator::class,
        )]
        ```
5. Выполняем команду `php bin/console doctrine:cache:clear-metadata`   
6. Выполняем запрос Get user API Platform из Postman-коллекции v11. Видим результат в виде DTO.
7. Проверяем по адресу `http://localhost:7777/api-platform`, что API-документация для получения пользователя тоже 
изменилась

## Пробуем получить JSON Schema

1. Добавляем класс `App\Controller\Api\v1\JSONSchemaController`
    ```php
    <?php
    
    namespace App\Controller\Api\v1;
    
    use ApiPlatform\Hydra\JsonSchema\SchemaFactory;
    use FOS\RestBundle\Controller\AbstractFOSRestController;
    use FOS\RestBundle\Controller\Annotations\QueryParam;
    use FOS\RestBundle\View\View;
    use FOS\RestBundle\Controller\Annotations as Rest;
    
    #[Rest\Route(path: 'api/v1/json-schema')]
    class JSONSchemaController extends AbstractFOSRestController
    {
        private SchemaFactory $jsonSchemaFactory;
    
        public function __construct(SchemaFactory $jsonSchemaFactory)
        {
            $this->jsonSchemaFactory = $jsonSchemaFactory;
        }
    
        #[Rest\Get('')]
        #[QueryParam(name:'resource')]
        public function getJSONSchemaAction(string $resource): View
        {
            $className = 'App\\Entity\\'.ucfirst($resource);
            $schema = $this->jsonSchemaFactory->buildSchema($className);
            $arraySchema = json_decode(json_encode($schema), true);
            return View::create($arraySchema);
        }
    }
    ```
2. В файле `config/services.yaml` добавляем новый сервис
    ```yaml
    App\Controller\Api\v1\JSONSchemaController:
        arguments:
            - '@api_platform.json_schema.schema_factory'
    ```
3. Выполняем запрос Get JSON Schema из Postman-коллекции v11
4. Заходим по адресу `https://rjsf-team.github.io/react-jsonschema-form/` и вставляем в поле JSONSchema результат
запроса, видим сгенерированную динамическую форму

## Убираем лишние поля из JSON Schema

1. В классе `App\Controller\Api\v1\JSONSchemaController` исправляем метод `getJSONSchemaAction`
    ```php
    #[Rest\Get('')]
    #[QueryParam(name:'resource')]
    public function getJSONSchemaAction(string $resource): View
    {
        $className = 'App\\Entity\\'.ucfirst($resource);
        $schema = $this->jsonSchemaFactory->buildSchema($className);
        $arraySchema = json_decode(json_encode($schema), true);
        $entityKey = array_key_first($arraySchema['definitions']);
        $unnecessaryPropertyKeys = array_filter(
            array_keys($arraySchema['definitions'][$entityKey]['properties']),
            static function (string $key) {
                return $key[0] === '@';
            }
        );
        foreach ($unnecessaryPropertyKeys as $key) {
            unset($arraySchema['definitions'][$entityKey]['properties'][$key]);
        }

        return View::create($arraySchema);
    }
    ```
2. Ещё раз выполняем запрос Get JSON Schema из Postman-коллекции v11. Вставляем в поле JSONSchema результат запроса,
   видим, что лишние поля из формы ушли

## Добавляем аутентификацию с помощью JWT

1. В файле `config/packages/security.yaml`
    1. в секцию `providers` добавляем новый провайдер
        ```yaml
        app_user_provider:
            entity:
                class: App\Entity\User
                property: login
        ```
    2. изменяем секцию `firewalls.main`
        ```yaml
        main:
            stateless: true
            provider: app_user_provider
            jwt: ~
            json_login:
                check_path: /authentication_token
                username_path: login
                password_path: password
                success_handler: lexik_jwt_authentication.handler.authentication_success
                failure_handler: lexik_jwt_authentication.handler.authentication_failure
        ```
    3. изменяем секцию `access_control`
        ```yaml
        - { path: ^/authentication_token, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }
        ```
2. В файле `config/packages/fos_rest.yaml` добавляем в секцию `format_listener.rules`
    ```yaml
    - { path: ^/authentication_token, prefer_extension: true, fallback_format: json, priorities: [ json, html ] }
    ```
3. В файле `config/routes.yaml` добавляем endpoint для получения токена
    ```yaml
    authentication_token:
      path: /authentication_token
      methods: ['POST']
    ```
4. В файле `config/api-platform.yaml` добавляем секцию `swagger`
    ```yaml
    swagger:
        api_keys:
            JWT:
                name: Authorization
                type: header

    ```
5. В классе `App\Doctrine\AbstractStringType` исправляем метод `convertToDatabaseValue`
    ```php
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }
   
        if (is_string($value)) {
            return $value;
        }    

        if ($value instanceof AbstractValueObjectString) {
            return $value->getValue();
        }

        /** @psalm-suppress MixedArgument */
        throw ConversionException::conversionFailed($value, $this->getName());
    }
    ```
6. Выполняем запрос Get token API Platform из Postman-коллекции v11.
7. Выполняем запрос Get JSON Schema из Postman-коллекции v11, видим ошибку 401
8. Подставляем токен в заголовок запроса Get JSON Schema из Postman-коллекции v11 и проверяем, что всё работает
