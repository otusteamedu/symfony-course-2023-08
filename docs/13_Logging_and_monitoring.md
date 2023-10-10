# Логирование и пониторинг

Запускаем контейнеры командой `docker-compose up -d`

## Логирование с помощью Monolog

### Добавляем monolog-bundle и логируем сообщения

1. Входим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
2. Устанавливаем пакет `symfony/monolog-bundle`
3. В файле `config/packages/security.yaml` в секцию `firewalls.main` добавляем параметр `security: false`
4. В классе `App\Controller\Api\CreateUser\v5\CreateUserManager`
    1. Добавляем инъекцию `LoggerInterface`
    ```php
        public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly SerializerInterface $serializer,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
            private readonly LoggerInterface $logger,
        ) {
        }
    ```
    2. В начало метода `saveUser` добавляем
        ```php
        $this->logger->debug('This is debug message');
        $this->logger->info('This is info message');
        $this->logger->notice('This is notice message');
        $this->logger->warning('This is warning message');
        $this->logger->error('This is error message');
        $this->logger->critical('This is critical message');
        $this->logger->alert('This is alert message');
        $this->logger->emergency('This is emergency message');
        ```
5. Выполняем запрос Add user v5 из Postman-коллекции v5 и проверяем, что логи попадают в файл `var/log/dev.log`

### Настраиваем уровень логирования

1. Заменяем в `config/packages/monolog.yaml` значение в секции `when@dev.monolog.handlers.main.level` на `critical`
2. Выполняем запрос Add user v5 из Postman-коллекции v5 и проверяем, что в файл `var/log/dev.log` попадают только
   сообщения с уровнями `critical`, `alert` и `emergency`

### Настраиваем режим fingers crossed

1. В файле `config/packages/monolog.yaml`
    1. Заменяем содержимое секции `when@dev.monolog.handlers.main`
        ```yaml
        type: fingers_crossed
        action_level: error
        handler: nested
        buffer_size: 3
        ```
    2. Добавляем в секцию `when@dev.monolog.handlers`
        ```yaml
        nested:
            type: stream
            path: "%kernel.logs_dir%/%kernel.environment%.log"
            level: debug
        ```
2. Выполняем запрос Add user v5 из Postman-коллекции v5 и проверяем, что в файл `var/log/dev.log` дополнительно попадают
   сообщение с уровнем `error` и два предыдущих сообщения с уровнем ниже

### Добавляем форматирование

1. Добавляем в `config/services.yaml`
    ```yaml
    monolog.formatter.app_formatter:
        class: Monolog\Formatter\LineFormatter
        arguments:
            - "[%%level_name%%]: [%%datetime%%] %%message%%\n"
    ```
2. Добавляем в `config/packages/monolog.yaml` в секцию `when@dev.monolog.handlers.main` форматтер
    ```yaml
    formatter: monolog.formatter.app_formatter
    ```
3. Выполняем запрос Add user v5 из Postman-коллекции v5 и проверяем, что в файл `var/log/dev.log` новые сообщения
   попадают с новом формате

## Grafana для сбора метрик, интеграция с Graphite

### Устанавливаем Grafana и Graphite

1. Входим в контейнер командой `docker exec -it php sh` и устанавливаем пакет `slickdeals/statsd`
2. Добавляем сервисы Graphite и Grafana в `docker-compose.yml`
    ```yaml
    graphite:
        image: graphiteapp/graphite-statsd
        container_name: 'graphite'
        restart: always
        ports:
          - 8000:80
          - 2003:2003
          - 2004:2004
          - 2023:2023
          - 2024:2024
          - 8125:8125/udp
          - 8126:8126

    grafana:
        image: grafana/grafana
        container_name: 'grafana'
        restart: always
        ports:
          - 3000:3000
    ```
3. Выходим из контейнера `php` и перезапускаем контейнеры
    ```shell
    docker-compose stop
    docker-compose up -d
    ```
4. Проверяем, что можем зайти в интерфейс Graphite по адресу `localhost:8000`
5. Проверяем, что можем зайти в интерфейс Grafana по адресу `localhost:3000`, логин / пароль - `admin` / `admin`
6. Добавляем класс `App\Client\StatsdAPIClient`
    ```php
    <?php
    
    namespace App\Client;
    
    use Domnikl\Statsd\Client;
    use Domnikl\Statsd\Connection\UdpSocket;
    
    class StatsdAPIClient
    {
        private const DEFAULT_SAMPLE_RATE = 1.0;
        
        private Client $client;
    
        public function __construct(string $host, int $port, string $namespace)
        {
            $connection = new UdpSocket($host, $port);
            $this->client = new Client($connection, $namespace);
        }
    
        public function increment(string $key, ?float $sampleRate = null, ?array $tags = null): void
        {
            $this->client->increment($key, $sampleRate ?? self::DEFAULT_SAMPLE_RATE, $tags ?? []);
        }
    }
    ```
7. В файле `config/services.yaml` добавляем описание сервиса statsd API-клиента
    ```yaml
    App\Client\StatsdAPIClient:
        arguments: 
            - graphite
            - 8125
            - my_app
    ```
8. В классе `App\Controller\Api\CreateUser\v5\CreateUserManager`
    1. Добавляем инъекцию `StatsdAPIClient`
        ```php
         public function __construct(
            private readonly EntityManagerInterface $entityManager,
            private readonly SerializerInterface $serializer,
            private readonly UserPasswordHasherInterface $userPasswordHasher,
            private readonly LoggerInterface $logger,
            private readonly StatsdAPIClient $statsdAPIClient,
        ) {
        }
        ```
    2. В начале метода `saveUser` инкрементируем счётчик
        ```php
        $this->statsdAPIClient->increment('save_user_v5_attempt');
        ```
9. Выполняем несколько раз запрос Add user v5 из Postman-коллекции v5 и проверяем, что в Graphite появляются события
10. Настраиваем график в Grafana
    1. добавляем в Data source с типом Graphite и адресом graphite:80
    2. добавляем новый Dashboard
    3. на дашборде добавляем панель с запросом в Graphite счётчика `stats_counts.my_app.save_user_v5_attempt`
    4. видим график с запросами
11. Выполняем ещё несколько раз запрос Add user v5 из Postman-коллекции v5 и проверяем, что в Grafana обновились данные

## Логируем с соблюдением SOLID при помощи паттерна декоратор и евентов

1. Создаем интерфейс `App\Controller\Api\CreateUser\v5\CreateUserManagerInterface`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5;

    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
    
    interface CreateUserManagerInterface
    {
        public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO;
    }
    ```
2. Имплементируем его в `App\Controller\Api\CreateUser\v5\CreateUserManager` и удаляем все вызовы класса `LoggerInterface`
    ```php
    class CreateUserManager implements CreateUserManagerInterface
    ```
3. Создаем класс `App\Controller\Api\CreateUser\v5\CreateUserManagerLoggerDecorator`
    ```php
    <?php
    
    namespace App\Controller\Api\CreateUser\v5;
    
    use App\Controller\Api\CreateUser\v5\Input\CreateUserDTO;
    use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
    use Psr\Log\LoggerInterface;
    
    class CreateUserManagerLoggerDecorator implements CreateUserManagerInterface
    {
        public function __construct(
            private readonly CreateUserManagerInterface $manager,
            private readonly LoggerInterface $logger,
        ) {
        }
    
        public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
        {
            $this->logger->info('Creating new user');
    
            try {
                $result = $this->manager->saveUser($saveUserDTO);
            } catch (\Throwable $e) {
                $this->logger->error('Creation error');
                throw $e;
            }
    
            $this->logger->info('New user created');
    
            return $result;
        }
    }
    ```
4. В классе `App\Controller\Api\CreateUser\v5\CreateUserAction` заменяем `CreateUserManager` на `CreateUserManagerInterface`
    ```php
    public function __construct(private readonly CreateUserManagerInterface $saveUserManager)
    {
    }
    ```
5. В `config/services.yaml` добавляем
   ```yaml
    App\Controller\Api\CreateUser\v5\CreateUserAction:
        arguments: [ '@App\Controller\Api\CreateUser\v5\CreateUserManagerLoggerDecorator' ]

    App\Controller\Api\CreateUser\v5\CreateUserManagerLoggerDecorator:
        decorates: App\Controller\Api\CreateUser\v5\CreateUserManager
        arguments: [ '@.inner' ]
    ```
6. В файле `config/packages/monolog.yaml`
   1. заменяем содержимое секции `when@dev.monolog.handlers.main` на
       ```yaml
       type: stream
       path: "%kernel.logs_dir%/%kernel.environment%.log"
       level: info
       channels: ["!event"]
       ```
   2. Удаляем обработчик `when@dev.monolog.handlers.nested`
7. Делаем запрос и проверяем работоспособность

### Логируем с помощью событий

1. Изменяем класс `App\EventSubscriber\CreateUserEventSubscriber`
    ```php
    <?php
    
    namespace App\EventSubscriber;
    
    use App\Event\CreateUserEvent;
    use Psr\Log\LoggerInterface;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class CreateUserEventSubscriber implements EventSubscriberInterface
    {
        public function __construct(
            private readonly LoggerInterface $logger,
        ) {
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                CreateUserEvent::class => 'logCreateUser'
            ];
        }
    
        public function logCreateUser(CreateUserEvent $event): void
        {
            $this->logger->info('User created with login: ' . $event->getLogin());
        }
    }
    ```
2. В классе `App\Controller\Api\CreateUser\v5\CreateUserManager` исправляем метод `saveUser`
    ```php
    public function saveUser(CreateUserDTO $saveUserDTO): UserIsCreatedDTO
    {
        $this->statsdAPIClient->increment('save_user_v5_attempt');
        $user = new User();
        $user->setLogin($saveUserDTO->login);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $saveUserDTO->password));
        $user->setRoles($saveUserDTO->roles);
        $user->setAge($saveUserDTO->age);
        $user->setIsActive($saveUserDTO->isActive);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $this->eventDispatcher->dispatch(new CreateUserEvent($user->getLogin()));

        $result = new UserIsCreatedDTO();
        $context = (new SerializationContext())->setGroups(['video-user-info', 'user-id-list']);
        $result->loadFromJsonString($this->serializer->serialize($user, 'json', $context));

        return $result;
    }
    ```
3. Делаем запрос и проверяем работоспособность по логам
