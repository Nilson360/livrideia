<?php

namespace App\Controller\DashboardUser;

use App\Repository\FriendRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\DeviceDetectorService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard-user')]
#[IsGranted('ROLE_USER')]
class UserOtherProfileController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DeviceDetectorService  $deviceDetector,
        private readonly FileUploader           $fileUploader,
        private readonly PostRepository         $postRepository,
        private readonly UserRepository         $userRepository,
        private readonly FriendRepository       $friendRepository,
        private readonly RequestStack           $requestStack,
    )

    {
    }

    #[Route('/utilisateur/{username}', name: 'dashboard_user_profile_other')]
    public function userProfileOther(string $username): Response
    {
        // Trouver l'utilisateur par son nom d'utilisateur
        $user = $this->userRepository->findOneBy(['username' => $username]);

        if (!$user) {
            throw $this->createNotFoundException('Utilisateur non trouvé');
        }

        // Vérifier si l'utilisateur connecté consulte son propre profil
        $currentUser = $this->getUser();
        if ($currentUser && $currentUser->getId() === $user->getId()) {
            return $this->redirectToRoute('dashboard_user_profile');
        }

        $templateData = [
            'user' => $user,
            'posts' => $user->getPosts(),
        ];

        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/profileOther/profile_other.html.twig', $templateData);
        }

        return $this->render('dashboard_user/desktop/profile/profileOther/profile_other.html.twig', $templateData);
    }
}