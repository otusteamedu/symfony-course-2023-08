# Компонент HttpFoundation

Запускаем контейнеры командой `docker-compose up -d`

## Добавляем загрузку файлов

1. Создаём класс `App\Controller\Api\v1\UploadController`
    ```php
    <?php
    
    namespace App\Controller\Api\v1;
    
    use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
    use Symfony\Component\HttpFoundation\File\UploadedFile;
    use Symfony\Component\HttpFoundation\JsonResponse;
    use Symfony\Component\HttpFoundation\Request;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\Routing\Annotation\Route;
    
    class UploadController extends AbstractController
    {
        #[Route(path: 'api/v1/upload', methods: ['POST'])]
        public function uploadFileAction(Request $request): Response
        {
            /** @var UploadedFile $file */
            $file = $request->files->get('image');
            $file->move('upload', sprintf('%s.%s', uniqid('image', true), $file->getClientOriginalExtension()));
    
            return new JsonResponse(['filename' => $file->getRealPath()]);
        }
    }
    ```
2. Выполняем запрос Upload file из Postman-коллекции v2, видим исходный путь к файлу в каталоге tmp
3. Заходим в контейнер `php` командой `docker exec -it php sh`. Дальнейшие команды выполняются из контейнера
4. Проверяем, что файл появился в каталоге `public/upload` с новым именем и исходным расширением

## Исправим получение пути

1. В классе `App\Controller\Api\v1\UploadController` исправим метод `uploadFileAction`
    ```php
    #[Route(path: 'api/v1/upload', methods: ['POST'])]
    public function uploadFileAction(Request $request): Response
    {
        /** @var UploadedFile $file */
        $file = $request->files->get('image');
        $realFile = $file->move('upload', sprintf('%s.%s', uniqid('image', true), $file->getClientOriginalExtension()));

        return new JsonResponse(['filename' => $realFile->getRealPath()]);
    }
    ```
2. Ещё раз выполняем запрос Upload file из Postman-коллекции v2, видим корректный путь к файлу

## Добавляем ExceptionListener

1. Добавляем класс `App\Exception\DeprecatedApiException`
    ```php
    <?php
    
    namespace App\Exception;
   
    use Exception;
    
    class DeprecatedApiException extends Exception
    {
    }
    ```
2. В классе `App\Controller\Api\v1\UserController` исправляем метод `saveUserAction`
    ```php
    #[Route(path: '', methods: ['POST'])]
    public function saveUserAction(Request $request): Response
    {
        throw new DeprecatedApiException('This API method is deprecated');

        $login = $request->request->get('login');
        $userId = $this->userManager->saveUser($login);
        [$data, $code] = $userId === null ?
            [['success' => false], Response::HTTP_BAD_REQUEST] :
            [['success' => true, 'userId' => $userId], Response::HTTP_OK];

        return new JsonResponse($data, $code);
    }
    ```
3. Добавляем класс `App\EventListener\DeprecatedApiExceptionListener`
    ```php
    <?php
    
    namespace App\EventListener;
    
    use App\Exception\DeprecatedApiException;
    use Symfony\Component\HttpFoundation\Response;
    use Symfony\Component\HttpKernel\Event\ExceptionEvent;
    
    class DeprecatedApiExceptionListener
    {
        public function onKernelException(ExceptionEvent $event): void
        {
            $exception = $event->getThrowable();
    
            if ($exception instanceof DeprecatedApiException) {
                $response = new Response();
                $response->setContent($exception->getMessage());
                $response->setStatusCode(Response::HTTP_GONE);
                $event->setResponse($response);
            }
        }
    }
    ```
4. В файл `config/services.yaml` добавляем
    ```yaml
    App\EventListener\DeprecatedApiExceptionListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
    ```
5. Выполняем запрос Add user из Postman-коллекции v2, видим код ответа 410 и наше сообщение об ошибке

## Добавляем работу с EventDispatcher

1. Добавляем класс `App\Event\CreateUserEvent`
    ```php
    <?php
    
    namespace App\Event;
    
    class CreateUserEvent
    {
        private string $login;
    
        public function __construct(string $login)
        {
            $this->login = $login;
        }
    
        public function getLogin(): string
        {
            return $this->login;
        }
    }
    ```
2. Добавляем класс `App\EventSubscriber\CreateUserEventSubscriber`
    ```php
    <?php
    
    namespace App\EventSubscriber;
    
    use App\Event\CreateUserEvent;
    use App\Manager\UserManager;
    use Symfony\Component\EventDispatcher\EventSubscriberInterface;
    
    class CreateUserEventSubscriber implements EventSubscriberInterface
    {
        public function __construct(private readonly UserManager $userManager)
        {
        }
    
        public static function getSubscribedEvents(): array
        {
            return [
                CreateUserEvent::class => 'onCreateUser'
            ];
        }
    
        public function onCreateUser(CreateUserEvent $event): void
        {
            $this->userManager->saveUser($event->getLogin());
        }
    }
    ```
3. В классе `App\Controller\Api\v1\UserController`
    1. Добавляем зависимость от `Psr\EventDispatcher\EventDispatcherInterface`
        ```php
        public function __construct(
            private readonly UserManager $userManager,
            private readonly EventDispatcherInterface $eventDispatcher,
        ) {
        }
        ```
    2. Добавляем метод `saveUserAsyncAction`
        ```php
        #[Route(path: '/async', methods: ['POST'])]
        public function saveUserAsyncAction(Request $request): Response
        {
            $this->eventDispatcher->dispatch(new CreateUserEvent($request->request->get('login')));
   
            return new JsonResponse(['success' => true], Response::HTTP_ACCEPTED);
        }
        ```
4. Выполняем запрос Add user async из Postman-коллекции v2, видим код ответа 202
5. Проверяем, что в БД появился новый пользователь
