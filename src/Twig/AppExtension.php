<?php

namespace App\Twig;

use App\Entity\Channel;
use App\Repository\ChannelRepository;
use Twig\Attribute\AsTwigFilter;
use Twig\Attribute\AsTwigFunction;

/**
 * Fonctions et filtres Twig propres à l'application
 * (enregistrés automatiquement grâce aux attributs #[AsTwig…]).
 */
final class AppExtension
{
    /** Couleurs des avatars, inspirées de celles de Discord. */
    private const AVATAR_COLORS = ['#5865f2', '#3ba55c', '#faa61a', '#ed4245', '#eb459e', '#00a8fc', '#9b59b6', '#1abc9c'];

    public function __construct(
        private readonly ChannelRepository $channelRepository,
    ) {
    }

    /**
     * Liste des salons affichée dans la barre latérale de toutes les pages.
     *
     * @return list<Channel>
     */
    #[AsTwigFunction('sidebar_channels')]
    public function sidebarChannels(): array
    {
        return $this->channelRepository->findAllOrdered();
    }

    /**
     * Couleur d'avatar stable pour un nom d'utilisateur donné.
     */
    #[AsTwigFilter('avatar_color')]
    public function avatarColor(?string $username): string
    {
        // "& 0x7FFFFFFF" garantit un entier positif, y compris sur un PHP 32 bits.
        $hash = crc32((string) $username) & 0x7FFFFFFF;

        return self::AVATAR_COLORS[$hash % \count(self::AVATAR_COLORS)];
    }

    /**
     * Initiale affichée dans l'avatar.
     */
    #[AsTwigFilter('initial')]
    public function initial(?string $username): string
    {
        $initial = mb_strtoupper(mb_substr((string) $username, 0, 1));

        return '' === $initial ? '?' : $initial;
    }
}
