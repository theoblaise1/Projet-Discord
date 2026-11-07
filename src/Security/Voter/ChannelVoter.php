<?php

namespace App\Security\Voter;

use App\Entity\Channel;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un salon : seul son créateur ou un administrateur peut le
 * modifier ou le supprimer.
 *
 * Utilisation : #[IsGranted(ChannelVoter::EDIT, subject: 'channel')] dans un
 * contrôleur, ou {% if is_granted('CHANNEL_EDIT', channel) %} dans Twig.
 *
 * @extends Voter<string, Channel>
 */
final class ChannelVoter extends Voter
{
    public const EDIT = 'CHANNEL_EDIT';
    public const DELETE = 'CHANNEL_DELETE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDIT, self::DELETE], true)
            && $subject instanceof Channel;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->accessDecisionManager->decide($token, ['ROLE_ADMIN'])) {
            return true;
        }

        /** @var Channel $subject */
        return $subject->isOwnedBy($user);
    }
}
