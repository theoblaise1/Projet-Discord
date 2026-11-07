<?php

namespace App\Controller;

use App\Repository\ChannelRepository;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    /**
     * Page d'accueil (réservée aux membres connectés) : liste des salons.
     */
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        ChannelRepository $channelRepository,
        MessageRepository $messageRepository,
        UserRepository $userRepository,
    ): Response {
        return $this->render('home/index.html.twig', [
            'channels' => $channelRepository->findAllOrdered(),
            'message_counts' => $messageRepository->countByChannel(),
            'member_count' => $userRepository->count(),
        ]);
    }
}
