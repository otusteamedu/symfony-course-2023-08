<?php

namespace App\Manager;

use App\Domain\ValueObject\UserLogin;
use App\DTO\ManageUserDTO;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Elastica\Aggregation\Terms;
use Elastica\Query;
use Elastica\Query\QueryString;
use FOS\ElasticaBundle\Finder\PaginatedFinderInterface;
use FOS\ElasticaBundle\Paginator\FantaPaginatorAdapter;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly PaginatedFinderInterface $finder,
    ) {
    }

    public function createByLogin(string $login): User
    {
        $user = new User();
        $user->setLogin($login);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    public function saveUser(User $user): void
    {
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }

    public function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }

    public function findUser(int $id): ?User
    {
        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->find($id);

        return $user instanceof User ? $user : null;
    }

    public function subscribeUser(User $author, User $follower): void
    {
        $author->addFollower($follower);
        $follower->addAuthor($author);
        $this->entityManager->flush();
    }

    /**
     * @return User[]
     */
    public function findUsersByLogin(string $name): array
    {
        return $this->entityManager->getRepository(User::class)->findBy(['login' => $name]);
    }

    /**
     * @return User[]
     */
    public function findUsersByCriteria(string $login): array
    {
        $criteria = Criteria::create();
        $criteria->andWhere(Criteria::expr()?->eq('login', $login));
        /** @var EntityRepository $repository */
        $repository = $this->entityManager->getRepository(User::class);

        return $repository->matching($criteria)->toArray();
    }

    public function updateUserLogin(int $userId, string $login): ?User
    {
        $user = $this->findUser($userId);
        if (!($user instanceof User)) {
            return null;
        }
        $user->setLogin($login);
        $this->entityManager->flush();

        return $user;
    }

    public function findUsersWithQueryBuilder(string $login): array
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        // SELECT u.* FROM `user` u WHERE u.login LIKE :userLogin
        $queryBuilder->select('u')
            ->from(User::class, 'u')
            ->andWhere($queryBuilder->expr()->like('u.login',':userLogin'))
            ->setParameter('userLogin', "%$login%");

        return $queryBuilder->getQuery()->getResult();
    }

    public function updateUserLoginWithQueryBuilder(int $userId, string $login): void
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->update(User::class,'u')
            ->set('u.login', ':userLogin')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId)
            ->setParameter('userLogin', $login);

        $queryBuilder->getQuery()->execute();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function updateUserLoginWithDBALQueryBuilder(int $userId, string $login): void
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();
        $queryBuilder->update('"user"','u')
            ->set('login', ':userLogin')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId)
            ->setParameter('userLogin', $login);

        $queryBuilder->executeStatement();
    }

    /**
     * @throws NonUniqueResultException
     */
    public function findUserWithTweetsWithQueryBuilder(int $userId): ?User
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('u', 't')
            ->from(User::class, 'u')
            ->leftJoin('u.tweets', 't')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId);

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function findUserWithTweetsWithDBALQueryBuilder(int $userId): array
    {
        $queryBuilder = $this->entityManager->getConnection()->createQueryBuilder();
        $queryBuilder->select('u', 't')
            ->from('"user"', 'u')
            ->leftJoin('u', 'tweet', 't', 'u.id = t.author_id')
            ->where($queryBuilder->expr()->eq('u.id', ':userId'))
            ->setParameter('userId', $userId);

        return $queryBuilder->executeQuery()->fetchAllNumeric();
    }

    /**
     * @return User[]
     */
    public function getUsers(int $page, int $perPage): array
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);

        return $userRepository->getUsers($page, $perPage);
    }

    public function deleteUser(User $user): bool
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();

        return true;
    }

    public function deleteUserById(int $userId): bool
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User $user */
        $user = $userRepository->find($userId);
        if ($user === null) {
            return false;
        }
        return $this->deleteUser($user);
    }

    /**
     * @return User[]
     */
    public function getUserList(): array
    {
        return [
            new User('Иван', 'Сергеевич', 'Сапогов', '+71112223344'),
            new User('Фёдор', 'Викторович', 'Лаптев', '+72223334455'),
            new User('Пётр', 'Михайлович', 'Стеклов', '+73334445566'),
            new User('Игнат', 'Глебович', 'Лопухов', '+74445556677'),
        ];
    }

    public function getUsersListVue(): array
    {
        return array_map(
            static fn(User $user) => $user->toArray(),
            $this->getUserList()
        );
    }

    public function saveUserFromDTO(User $user, ManageUserDTO $manageUserDTO): ?int
    {
        $user->setLogin(UserLogin::fromString($manageUserDTO->login));
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $manageUserDTO->password));
        $user->setAge($manageUserDTO->age);
        $user->setIsActive($manageUserDTO->isActive);
        $user->setRoles($manageUserDTO->roles);
        $user->setPhone($manageUserDTO->phone);
        $user->setEmail($manageUserDTO->email);
        $user->setPreferred($manageUserDTO->preferred);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user->getId();
    }

    public function findUserByLogin(string $login): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['login' => $login]);

        return $user;
    }

    public function updateUserToken(string $login): ?string
    {
        $user = $this->findUserByLogin($login);
        if ($user === null) {
            return false;
        }
        $token = base64_encode(random_bytes(20));
        $user->setToken($token);
        $this->entityManager->flush();

        return $token;
    }

    public function findUserByToken(string $token): ?User
    {
        /** @var UserRepository $userRepository */
        $userRepository = $this->entityManager->getRepository(User::class);
        /** @var User|null $user */
        $user = $userRepository->findOneBy(['token' => $token]);

        return $user;
    }

    /**
     * @return User[]
     */
    public function findUserByQuery(string $query, int $perPage, int $page): array
    {
        $paginatedResult = $this->finder->findPaginated($query);
        $paginatedResult->setMaxPerPage($perPage);
        $paginatedResult->setCurrentPage($page);
        $result = [];
        array_push($result, ...$paginatedResult->getCurrentPageResults());

        return $result;
    }

    /**
     * @return User[]
     */
    public function findUserWithAggregation(string $field): array
    {
        $aggregation = new Terms('notifications');
        $aggregation->setField($field);
        $query = new Query();
        $query->addAggregation($aggregation);
        $paginatedResult = $this->finder->findPaginated($query);
        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $paginatedResult->getAdapter();

        return $adapter->getAggregations();
    }

    /**
     * @return User[]
     */
    public function findUserByQueryWithAggregation(string $queryString, string $field): array
    {
        $aggregation = new Terms('notifications');
        $aggregation->setField($field);
        $query = new Query(new QueryString($queryString));
        $query->addAggregation($aggregation);
        $paginatedResult = $this->finder->findPaginated($query);
        /** @var FantaPaginatorAdapter $adapter */
        $adapter = $paginatedResult->getAdapter();

        return $adapter->getAggregations();
    }
}
