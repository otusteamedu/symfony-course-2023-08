# NelmioApiDocBundle и документация API

Запускаем контейнеры командой `docker-compose up -d`

## Установка NelmioApiDocBundle

1. Заходим в контейнер командой `docker exec -it php sh`. Дальнейшие команды выполняем из контейнера
2. Устанавливаем пакет `nelmio/api-doc-bundle`
3. Заходим по адресу `http://localhost:7777/api/doc.json`, видим JSON-описание нашего API
4. Заходим по адресу `http://localhost:7777/api/doc`, видим ошибку

_## Добавляем роутинг на UI_

1. Добавляем в файл `config/routes.yaml`
    ```yaml
    app.swagger_ui:
      path: /api/doc
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
2. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим описание API

## Добавляем авторизацию

1. В файле `config/packages/security.yaml` в секцию `access_control` добавляем строку
    ```
    - { path: ^/api/doc, roles: ROLE_ADMIN }
    ```
2. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим требование авторизоваться

##  Прячем служебный endpoint

1. Исправляем в файле `config/packages/nelmio_api_doc.yaml` секцию `areas.path_patterns`
    ```yaml
    path_patterns:
        - ^/api(?!/doc(.json)?$)
    ```
2. Ещё раз заходим по адресу `http://localhost:7777/api/doc`, видим, что служебный endpoint не отображается

## Выделяем зону

1. Исправляем в файле `config/packages/nelmio_api_doc.yaml` секцию `areas`
    ```yaml
    feed:
      path_patterns:
        - ^/api/v1/get-feed
    default:
      path_patterns:
        - ^/api(?!/doc(.json)?$)
    ```
2. В файл `config/routes.yaml` добавляем
    ```yaml
    app.swagger_ui_areas:
      path: /api/doc/{area}
      methods: GET
      defaults: { _controller: nelmio_api_doc.controller.swagger_ui }
    ```
3. Заходим по адресу `http://localhost:7777/api/doc/feed`, видим выделенный endpoint

## Добавляем описывающие аннотации

1. В классе `App\Controller\Api\GetFeed\v1\Controller`
   1. Добавляем импорт
       ```php
       use OpenApi\Attributes as OA;
       ```
   2. Убираем атрибуты с описанием параметров
   3. Добавляем аннотации к методу `getFeedAction`
       ```php
       #[OA\Tag(name: 'Лента')]
       #[OA\Parameter(name: 'userId', description: 'ID пользователя', in: 'query', example: '135')]
       #[OA\Parameter(name: 'count', description: 'Количество на странице', in: 'query', example: '1')]
       ```
2. Заходим по адресу `http://localhost:7777/api/doc`, видим, что endpoint выделен в отдельный тэг и обновлённое
   описание параметров

## Генерируем API-клиент

1. Выполняем команду `php bin/console nelmio:apidoc:dump --format=yaml >apidoc.yaml`, получаем соответствующий файл
   с описанием API
2. Добавляем новый сервис в `docker-compose.yml`
    ```yaml
    openapi-generator:
      image: openapitools/openapi-generator-cli:latest
      volumes:
        - ./:/local
      command: ["generate", "-i", "/local/apidoc.yaml", "-g", "php", "-o", "/local/api-client"]
    ```
3. Выходим из контейнера и выполняем команду `docker-compose up openapi-generator`, видим сгенерированный клиент, но в
   нём нет метода `getFeed`

## Исправляем аннотации для корректной генерации клиента

1. В классе `App\Controller\Api\GetFeed\v1\Controller` исправляем аннотации к методу `getFeedAction`
    ```php
    #[OA\Get(
        operationId: 'getFeed',
        tags: ['Лента'],
        parameters: [
            new OA\Parameter(
                name: 'userId',
                description: 'ID пользователя',
                in: 'query',
                example: '135',
            ),
            new OA\Parameter(
                name: 'count',
                description: 'Количество на странице',
                in: 'query',
                example: '1',
            ),
        ]
    )]
    ```
2. Опять заходим в контейнер командой `docker exec -it php sh` и выполняем команду
   `php bin/console nelmio:apidoc:dump --format=yaml --area=feed >apidoc.yaml`
3. Выходим из контейнера, удаляем каталог api-client и выполняем команду `docker-compose up openapi-generator`.
   Видим, что клиент сгенерировался и метод `getFeed` в нём присутствует

## Добавляем DTO в аннотации

1. В классе `App\Controller\Api\CreateUser\v5\CreateUserAction`
   1. Добавляем импорты
       ```php
       use App\Controller\Api\CreateUser\v5\Output\UserIsCreatedDTO;
       use OpenApi\Attributes as OA;
       use Nelmio\ApiDocBundle\Annotation\Model;
       ```
   2. Добавляем аннотации к методу `addUserAction`
       ```php
      #[OA\Post(
        operationId: 'addUser',
        requestBody: new OA\RequestBody(
            description: 'Input data format',
            content: new Model(type: CreateUserDTO::class),
        ),
        tags: ['Пользователи'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Success',
                content: new Model(type: UserIsCreatedDTO::class),
            )
        ]
      )]
       ```
2. Заходим по адресу `http://localhost:7777/api/doc`, видим описания DTO и в запросе `/api/v5/users` ссылки на
   них, но при этом описание `CreateUserDTO` не полное (нет ролей)

## Дополняем аннотации в DTO

1. Исправляем свойства класса `App\Controller\Api\CreateUser\v5\Input\CreateUserDTO`
    ```php
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 32)]
    #[OA\Property(example: 'my_user')]
    public string $login;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(max: 32)]
    #[OA\Property(example: 'pass')]
    public string $password;

    #[Assert\NotBlank]
    #[Assert\Type('array')]
    #[OA\Property(type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER'))]
    public array $roles;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    #[OA\Property(example: 12)]
    public int $age;

    #[Assert\NotBlank]
    #[Assert\Type('bool')]
    #[OA\Property]
    public bool $isActive;
    ```
2. Заходим по адресу `http://localhost:7777/api/doc`, видим исправленные описания DTO, нажимаем `Try it out`, шлём
   запрос и видим сохранённую запись в БД
