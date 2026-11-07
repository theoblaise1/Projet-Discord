<?php

namespace App\Tests;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Classe de base des tests fonctionnels : un navigateur de test ($this->client),
 * une base de données vidée avant chaque test et des raccourcis pour créer des données.
 */
abstract class AppWebTestCase extends WebTestCase
{
    protected const PASSWORD = 'password';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        (new ORMPurger($this->entityManager()))->purge();
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @param list<string> $roles
     */
    protected function createUser(string $username, array $roles = []): User
    {
        $user = (new User())
            ->setUsername($username)
            ->setEmail($username.'@example.com')
            ->setRoles($roles);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }

    protected function createChannel(User $owner, string $name = 'general', ?string $description = null): Channel
    {
        $channel = (new Channel())
            ->setOwner($owner)
            ->setName($name)
            ->setDescription($description);

        $this->entityManager()->persist($channel);
        $this->entityManager()->flush();

        return $channel;
    }

    protected function createMessage(Channel $channel, User $author, string $content, ?\DateTimeImmutable $createdAt = null): Message
    {
        $message = (new Message())
            ->setChannel($channel)
            ->setAuthor($author)
            ->setContent($content);

        if (null !== $createdAt) {
            $message->setCreatedAt($createdAt);
        }

        $this->entityManager()->persist($message);
        $this->entityManager()->flush();

        return $message;
    }

    /**
     * Recharge une entité depuis la base (ou null si elle a été supprimée).
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T|null
     */
    protected function reload(string $class, ?int $id): ?object
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();

        return null === $id ? null : $entityManager->find($class, $id);
    }

    /**
     * @return array<string, mixed>
     */
    protected function jsonResponse(): array
    {
        $content = (string) $this->client->getResponse()->getContent();
        self::assertJson($content);

        return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
    }
}
