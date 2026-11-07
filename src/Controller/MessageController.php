<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Repository\MessageRepository;
use App\Security\Voter\MessageVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Messages : chargement en AJAX (nouveaux messages / historique) et suppression.
 * L'envoi d'un message est traité par ChannelController::show().
 */
#[IsGranted('ROLE_USER')]
final class MessageController extends AbstractController
{
    /** Nombre maximum de nouveaux messages renvoyés par un rafraîchissement. */
    private const MAX_NEW_MESSAGES = 100;

    /**
     * Renvoie en JSON le HTML des messages d'un salon :
     *  - ?after=ID  : les messages publiés après le message n°ID (rafraîchissement automatique) ;
     *  - ?before=ID : les messages qui précèdent le message n°ID (« Charger les messages précédents »).
     */
    #[Route('/channels/{id}/messages', name: 'app_message_list', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function list(Request $request, Channel $channel, MessageRepository $messageRepository): JsonResponse
    {
        $before = $request->query->getInt('before');

        if ($before > 0) {
            $messages = $messageRepository->findBeforeInChannel($channel, $before, ChannelController::MESSAGES_PER_PAGE);

            return $this->json([
                'html' => $this->renderView('message/_list.html.twig', [
                    'messages' => $messages,
                    'previous' => null,
                ]),
                'hasMore' => [] !== $messages
                    && $messageRepository->hasMessagesBefore($channel, (int) $messages[0]->getId()),
            ]);
        }

        $after = max(0, $request->query->getInt('after'));
        $messages = $messageRepository->findAfterInChannel($channel, $after, self::MAX_NEW_MESSAGES);

        if ([] === $messages) {
            return $this->json(['html' => '', 'lastId' => $after]);
        }

        return $this->json([
            'html' => $this->renderView('message/_list.html.twig', [
                'messages' => $messages,
                // Le dernier message déjà affiché permet de regrouper les messages
                // consécutifs d'un même auteur, comme sur Discord.
                'previous' => $after > 0 ? $messageRepository->findOneBy(['id' => $after, 'channel' => $channel]) : null,
            ]),
            'lastId' => end($messages)->getId(),
        ]);
    }

    #[Route('/messages/{id}/delete', name: 'app_message_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(MessageVoter::DELETE, subject: 'message')]
    public function delete(Request $request, Message $message, EntityManagerInterface $entityManager): Response
    {
        $channelId = $message->getChannel()?->getId();
        $wantsJson = 'json' === $request->getPreferredFormat();

        if (!$this->isCsrfTokenValid('delete-message', $request->getPayload()->getString('_token'))) {
            $error = 'Le jeton de sécurité est invalide, le message n\'a pas été supprimé. Veuillez recharger la page.';

            if ($wantsJson) {
                return $this->json(['errors' => [$error]], Response::HTTP_FORBIDDEN);
            }

            $this->addFlash('error', $error);

            return $this->redirectToRoute('app_channel_show', ['id' => $channelId], Response::HTTP_SEE_OTHER);
        }

        $messageId = $message->getId();
        $entityManager->remove($message);
        $entityManager->flush();

        if ($wantsJson) {
            return $this->json(['id' => $messageId]);
        }

        $this->addFlash('success', 'Le message a été supprimé.');

        return $this->redirectToRoute('app_channel_show', ['id' => $channelId], Response::HTTP_SEE_OTHER);
    }
}
