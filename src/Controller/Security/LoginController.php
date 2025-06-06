<?php

namespace App\Controller\Security;

use App\Service\DeviceDetectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    private DeviceDetectorService $deviceDetector;

    public function __construct(DeviceDetectorService $deviceDetector)
    {
        $this->deviceDetector = $deviceDetector;
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $templateData = [
            'last_username' => $lastUsername,
            'error' => $error,
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('auth/mobile/login.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('auth/desktop/login/login.html.twig', $templateData);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}