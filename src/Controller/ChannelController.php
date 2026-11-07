<?php

namespace App\Controller;

use App\Entity\Channel;
use App\Entity\Message;
use App\Entity\User;
use App\Form\ChannelType;
use App\Form\MessageType;
use App\Repository\MessageRepository;
use App\Security\Voter\ChannelVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Salons : création, affichage (avec envoi de messages), modification et suppression.
 */
#[Route('/channels')]
#[IsGranted('ROLE_USER')]
final class ChannelController extends AbstractController
{
    /** Nombre de messages affichés à l'ouverture d'un salon (les plus anciens se chargent à la demande). */
    public const MESSAGES_PER_PAGE = 50;

    #[Route('/new', name: 'app_channel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, #[CurrentUser] User $user, EntityManagerInterface $entityManager): Response
    {
        $channel = (new Channel())->setOwner($user);
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($channel);
            $entityManager->flush();

            $this->addFlash('success', \sprintf('Le salon #%s a été créé.', $channel->getName()));

            return $this->redirectToRoute('app_channel_show', ['id' => $channel->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('channel/new.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Affiche un salon et ses derniers messages, et traite l'envoi d'un nouveau message.
     *
     * Le formulaire fonctionne sans JavaScript (redirection après envoi). Avec
     * JavaScript (assets/chat.js), il est envoyé en AJAX : la réponse est alors en JSON.
     */
    #[Route('/{id}', name: 'app_channel_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(
        Request $request,
        Channel $channel,
        #[CurrentUser] User $user,
        MessageRepository $messageRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $message = (new Message())
            ->setChannel($channel)
            ->setAuthor($user);

        $form = $this->createForm(MessageType::class, $message, [
            'action' => $this->generateUrl('app_channel_show', ['id' => $channel->getId()]),
        ]);
        $form->handleRequest($request);
        $wantsJson = 'json' === $request->getPreferredFormat();

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($message);
            $entityManager->flush();

            if ($wantsJson) {
                return $this->json(['id' => $message->getId()], Response::HTTP_CREATED);
            }

            return $this->redirectToRoute('app_channel_show', ['id' => $channel->getId()], Response::HTTP_SEE_OTHER);
        }

        if ($form->isSubmitted() && $wantsJson) {
            return $this->json(['errors' => $this->getErrorMessages($form)], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $messages = $messageRepository->findLatestInChannel($channel, self::MESSAGES_PER_PAGE);

        return $this->render('channel/show.html.twig', [
            'channel' => $channel,
            'messages' => $messages,
            'has_older_messages' => [] !== $messages
                && $messageRepository->hasMessagesBefore($channel, (int) $messages[0]->getId()),
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_channel_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ChannelVoter::EDIT, subject: 'channel')]
    public function edit(Request $request, Channel $channel, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ChannelType::class, $channel);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $entityManager->flush();

                $this->addFlash('success', \sprintf('Le salon #%s a été modifié.', $channel->getName()));

                return $this->redirectToRoute('app_channel_show', ['id' => $channel->getId()], Response::HTTP_SEE_OTHER);
            }

            // Le formulaire a modifié l'objet en mémoire avec des valeurs invalides :
            // on le recharge depuis la base pour que le reste de la page (liste des
            // salons, en-tête) affiche toujours le vrai nom. Le formulaire, lui,
            // conserve les valeurs saisies et les messages d'erreur.
            $entityManager->refresh($channel);
        }

        return $this->render('channel/edit.html.twig', [
            'channel' => $channel,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_channel_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ChannelVoter::DELETE, subject: 'channel')]
    public function delete(Request $request, Channel $channel, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-channel-'.$channel->getId(), $request->getPayload()->getString('_token'))) {
            $this->addFlash('error', 'Le jeton de sécurité est invalide, le salon n\'a pas été supprimé. Veuillez réessayer.');

            return $this->redirectToRoute('app_channel_edit', ['id' => $channel->getId()], Response::HTTP_SEE_OTHER);
        }

        $name = $channel->getName();

        // Les messages du salon sont supprimés en cascade par la base de données.
        $entityManager->remove($channel);
        $entityManager->flush();

        $this->addFlash('success', \sprintf('Le salon #%s et ses messages ont été supprimés.', $name));

        return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * @return list<string>
     */
    private function getErrorMessages(FormInterface $form): array
    {
        $messages = [];

        foreach ($form->getErrors(true) as $error) {
            if ($error instanceof FormError) {
                $messages[] = $error->getMessage();
            }
        }

        return $messages;
    }
}
