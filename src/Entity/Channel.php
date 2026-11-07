<?php

namespace App\Entity;

use App\Repository\ChannelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Salon de discussion textuel.
 *
 * Tout membre connecté peut lire et écrire dans un salon ; seul son créateur
 * (ou un administrateur) peut le modifier ou le supprimer (voir ChannelVoter).
 */
#[ORM\Entity(repositoryClass: ChannelRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_CHANNEL_NAME', columns: ['name'])]
#[UniqueEntity(fields: ['name'], message: 'Un salon porte déjà ce nom.')]
class Channel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Veuillez donner un nom au salon.')]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: 'Le nom du salon doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom du salon ne peut pas dépasser {{ limit }} caractères.',
    )]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $owner = null;

    /**
     * Les messages sont supprimés par la base de données (ON DELETE CASCADE)
     * lorsque le salon est supprimé.
     *
     * @var Collection<int, Message>
     */
    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'channel', fetch: 'EXTRA_LAZY')]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = null === $name ? null : trim($name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null === $description ? null : trim($description);
        $this->description = '' === $description ? null : $description;

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

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function isOwnedBy(User $user): bool
    {
        return null !== $this->owner && $this->owner->getId() === $user->getId();
    }

    /**
     * @return Collection<int, Message>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(Message $message): static
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setChannel($this);
        }

        return $this;
    }

    public function removeMessage(Message $message): static
    {
        $this->messages->removeElement($message);

        return $this;
    }
}
