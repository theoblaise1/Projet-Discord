<?php

namespace App\Security\Voter;

use App\Entity\Message;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un message : il peut être supprimé par son auteur, par le
 * créateur du salon (modération) ou par un administrateur.
 *
 * @extends Voter<string, Message>
 */
final class MessageVoter extends Voter
{
    public const DELETE = 'MESSAGE_DELETE';

    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DELETE === $attribute && $subject instanceof Message;
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

        /** @var Message $subject */
        return $subject->isAuthoredBy($user)
            || true === $subject->getChannel()?->isOwnedBy($user);
    }
}
