<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Membre de l'application.
 *
 * L'utilisateur se connecte avec son adresse e-mail OU son nom d'utilisateur
 * (voir UserRepository::loadUserByIdentifier()).
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'UNIQ_APP_USER_EMAIL', columns: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_APP_USER_USERNAME', columns: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe déjà avec cette adresse e-mail.')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'Veuillez saisir une adresse e-mail.')]
    #[Assert\Email(message: 'L\'adresse e-mail {{ value }} n\'est pas valide.')]
    #[Assert\Length(max: 180, maxMessage: 'L\'adresse e-mail ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $email = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank(message: 'Veuillez choisir un nom d\'utilisateur.')]
    #[Assert\Length(
        min: 2,
        max: 32,
        minMessage: 'Le nom d\'utilisateur doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom d\'utilisateur ne peut pas dépasser {{ limit }} caractères.',
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_.-]+$/',
        message: 'Le nom d\'utilisateur ne peut contenir que des lettres non accentuées, des chiffres, des points, des tirets et des underscores.',
    )]
    private ?string $username = null;

    /**
     * @var list<string> Les rôles de l'utilisateur (ROLE_USER est toujours ajouté)
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string|null Le mot de passe haché (jamais le mot de passe en clair)
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        // Les adresses e-mail sont stockées en minuscules : l'unicité et la
        // connexion ne dépendent donc pas de la casse.
        $this->email = null === $email ? null : mb_strtolower(trim($email));

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = null === $username ? null : trim($username);

        return $this;
    }

    /**
     * Identifiant "technique" utilisé par le composant Security (session, remember me…).
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Tout utilisateur connecté possède au minimum ROLE_USER.
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function isAdmin(): bool
    {
        return \in_array('ROLE_ADMIN', $this->getRoles(), true);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Évite de stocker le vrai hash du mot de passe dans la session :
     * seule une empreinte CRC32C est sérialisée (comportement Symfony 7.3+).
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', (string) $this->password);

        return $data;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, à supprimer lors du passage à Symfony 8
    }
}
