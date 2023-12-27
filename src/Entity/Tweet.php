<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use App\ApiPlatform\State\AsyncMessageTweetProcessorDecorator;
use App\Repository\TweetRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;
use JetBrains\PhpStorm\ArrayShape;
use Gedmo\Mapping\Annotation as Gedmo;
use JsonException;
use JMS\Serializer\Annotation as JMS;

#[ORM\Table(name: 'tweet')]
#[ORM\Index(columns: ['author_id'], name: 'tweet__author_id__ind')]
#[ORM\Entity(repositoryClass: TweetRepository::class)]
#[ApiResource(
    operations: [new Post(status: 202, processor: AsyncMessageTweetProcessorDecorator::class)], output: false
)]
class Tweet
{
    #[ORM\Column(name: 'id', type: 'bigint', unique: true)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: 'User', inversedBy: 'tweets')]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id')]
    #[JMS\Groups(['elastica'])]
    private User $author;

    #[ORM\Column(type: 'string', length: 140, nullable: false)]
    #[JMS\Groups(['elastica'])]
    private string $text;

    #[ORM\Column(name: 'created_at', type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'create')]
    private DateTime $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: false)]
    #[Gedmo\Timestampable(on: 'update')]
    private DateTime $updatedAt;

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getAuthor(): User
    {
        return $this->author;
    }

    public function setAuthor(User $author): void
    {
        $this->author = $author;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function setText(string $text): void
    {
        $this->text = $text;
    }

    public function getCreatedAt(): DateTime {
        return $this->createdAt;
    }

    public function setCreatedAt(): void {
        $this->createdAt = DateTime::createFromFormat('U', (string)time());
    }

    public function getUpdatedAt(): DateTime {
        return $this->updatedAt;
    }

    public function setUpdatedAt(): void {
        $this->updatedAt = new DateTime();
    }

    #[ArrayShape(['id' => 'int|null', 'login' => 'string', 'createdAt' => 'string', 'updatedAt' => 'string'])]
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'login' => $this->author->getLogin(),
            'text' => $this->text,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public function toFeed(): array
    {
        return [
            'id' => $this->id,
            'author' => isset($this->author) ? $this->author->getLogin() : null,
            'text' => $this->text,
            'createdAt' => isset($this->createdAt) ? $this->createdAt->format('Y-m-d h:i:s') : '',
        ];
    }

    /**
     * @throws JsonException
     */
    public function toAMPQMessage(): string
    {
        return json_encode(['tweetId' => $this->id], JSON_THROW_ON_ERROR);
    }
}
