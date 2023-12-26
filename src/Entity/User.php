<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GraphQl\DeleteMutation;
use ApiPlatform\Metadata\GraphQl\Query;
use ApiPlatform\Metadata\GraphQl\QueryCollection;
use App\ApiPlatform\State\UserProviderDecorator;
use App\Domain\ValueObject\UserLogin;
use App\DTO\UserDTO;
use App\Repository\UserRepository;
use App\Resolver\UserCollectionResolver;
use App\Resolver\UserResolver;
use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Gedmo\Mapping\Annotation as Gedmo;
use JMS\Serializer\Annotation as JMS;

#[ORM\Table(name: '`user`')]
#[ORM\Entity(repositoryClass: UserRepository::class)]
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
    provider: UserProviderDecorator::class
)]
#[ApiFilter(SearchFilter::class, properties: ['login' => 'partial'])]
#[ApiFilter(OrderFilter::class, properties: ['login'])]
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

    #[ORM\Column(type: 'integer', nullable: false)]
    #[JMS\Groups(['video-user-info', 'elastica'])]
    private int $age;

    #[ORM\Column(type: 'string', length: 11, nullable: true)]
    #[JMS\Groups(['elastica'])]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 128, nullable: true)]
    #[JMS\Groups(['elastica'])]
    private ?string $email = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[JMS\Groups(['elastica'])]
    private ?string $preferred = null;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
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

    #[ORM\Column(type: 'boolean', nullable: false)]
    #[JMS\Groups(['video-user-info'])]
    #[JMS\SerializedName('isActive')]
    private bool $isActive;

    #[ORM\Column(type: 'json', length: 1024, nullable: false)]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 32, unique: true, nullable: true)]
    private ?string $token = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $isProtected;

    #[ORM\OneToMany(mappedBy: 'follower', targetEntity: 'Subscription')]
    private Collection $followed;


    public function __construct()
    {
        $this->tweets = new ArrayCollection();
        $this->authors = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->followed = new ArrayCollection();
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

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(): void
    {
        $this->createdAt = new DateTime();
    }

    public function getUpdatedAt(): DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): void
    {
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

    public function eraseCredentials(): void
    {
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

    /**
     * @return Subscription[]
     */
    public function getSubscriptionFollowers(): array
    {
        return $this->subscriptionFollowers->toArray();
    }

    public function isProtected(): bool
    {
        return $this->isProtected ?? false;
    }

    public function setIsProtected(bool $isProtected): void
    {
        $this->isProtected = $isProtected;
    }

    /**
     * @return Subscription[]
     */
    public function getFollowed(): array
    {
        return $this->followed->getValues();
    }
}
