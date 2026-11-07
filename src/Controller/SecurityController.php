<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion. Sa soumission est traitée par
     * Symfony (form_login dans config/packages/security.yaml).
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            // Dernier identifiant saisi, pour pré-remplir le champ après une erreur
            'last_username' => $authenticationUtils->getLastUsername(),
            // Erreur de connexion éventuelle (identifiants invalides, jeton CSRF…)
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Cette méthode n\'est jamais exécutée : la déconnexion est interceptée par le pare-feu (clé "logout" de security.yaml).');
    }
}
