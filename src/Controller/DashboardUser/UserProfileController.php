<?php

namespace App\Controller\DashboardUser;

use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\ProfileType;
use App\Service\DeviceDetectorService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard-user')]
class UserProfileController extends AbstractController
{
    private DeviceDetectorService $deviceDetector;

    public function __construct(DeviceDetectorService $deviceDetector)
    {
        $this->deviceDetector = $deviceDetector;
    }

    #[Route('/profile', name: 'dashboard_user_profile', methods: ['GET'])]
    public function profile(EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }
        $user = $em->getRepository(User::class)->find($user->getId());
        $posts = $em->getRepository(Post::class)->findBy(['user' => $user]);
        // Récupération des relations d'amitié acceptées pour l'utilisateur connecté
        $friendRepo = $em->getRepository(Friend::class);
        $sentFriends = $friendRepo->findBy(['sender' => $user, 'status' => 'accepted']);
        $receivedFriends = $friendRepo->findBy(['receiver' => $user, 'status' => 'accepted']);

        // Fusionner les relations
        $friendRelationships = array_merge($sentFriends, $receivedFriends);

        $templateData = [
            'user' => $user,
            'posts' => $posts,
            'friends' => $friendRelationships,
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/index.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/profile.html.twig', $templateData);
    }

    #[Route('/profile/edit', name: 'dashboard_user_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(
        Request                $request,
        EntityManagerInterface $em,
        FileUploader           $fileUploader
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'avatar
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $avatarFileName = $fileUploader->upload($avatarFile, 'avatars');
                $user->setAvatarPath('uploads/avatars/' . $avatarFileName);
            }

            // Gestion de la photo de couverture
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $coverFileName = $fileUploader->upload($coverFile, 'covers');
                $user->setCoverPath('uploads/covers/' . $coverFileName);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('dashboard_user_profile');
        }

        $templateData = [
            'form' => $form->createView(),
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/edit_profile.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/edit_profile.html.twig', $templateData);
    }

    #[Route('/profile/change-password', name: 'dashboard_user_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface      $em
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Formulaire dédié au changement de mot de passe
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $oldPassword = $data['oldPassword'];
            $newPassword = $data['newPassword'];

            // Vérifier l'ancien mot de passe
            if ($passwordHasher->isPasswordValid($user, $oldPassword)) {
                // On hache et on met à jour le nouveau mot de passe
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);

                $em->persist($user);
                $em->flush();

                $this->addFlash('success', 'Mot de passe mis à jour avec succès !');
                return $this->redirectToRoute('dashboard_user_profile');
            } else {
                $this->addFlash('error', 'Ancien mot de passe incorrect.');
            }
        }

        $templateData = [
            'form' => $form->createView(),
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/change_password.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/change_password.html.twig', $templateData);
    }

    #[Route('/profile/{id}/friends', name: 'app_profile_friends', methods: ['GET'])]
    public function friends(User $user, EntityManagerInterface $em): Response
    {
        // Récupérer les relations d'amitié acceptées pour l'utilisateur
        $friendRepo = $em->getRepository(Friend::class);
        $sentFriends = $friendRepo->findBy(['sender' => $user, 'status' => 'accepted']);
        $receivedFriends = $friendRepo->findBy(['receiver' => $user, 'status' => 'accepted']);

        // Fusionner les amis (pour chaque relation, on affiche l'autre utilisateur)
        $friends = [];
        foreach ($sentFriends as $friend) {
            $friends[] = $friend->getReceiver();
        }
        foreach ($receivedFriends as $friend) {
            $friends[] = $friend->getSender();
        }

        $templateData = [
            'friends' => $friends,
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile() && $this->getRequest()->isXmlHttpRequest()) {
            return $this->render('mobile/profile/friends_list.html.twig', $templateData);
        } elseif ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/friends.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/friends.html.twig', $templateData);
    }

    #[Route('/utilisateur/{username}', name: 'dashboard_user_profile_other')]
    public function userProfileOther(string $username, EntityManagerInterface $em): Response
    {
        // Trouver l'utilisateur par son nom d'utilisateur
        $user = $em->getRepository(User::class)->findOneBy(['username' => $username]);

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

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/profile_other.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/profile_other.html.twig', $templateData);
    }

    #[Route('/profile/avatar-upload', name: 'dashboard_user_avatar_upload', methods: ['POST'])]
    public function uploadAvatar(
        Request                $request,
        EntityManagerInterface $em,
        FileUploader           $fileUploader
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $avatarFile = $request->files->get('avatarFile');
        if ($avatarFile) {
            $avatarFileName = $fileUploader->upload($avatarFile, 'avatars');
            $user->setAvatarPath('uploads/avatars/' . $avatarFileName);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Photo de profil mise à jour avec succès !');
        } else {
            $this->addFlash('error', 'Veuillez sélectionner une image.');
        }

        return $this->redirectToRoute('dashboard_user_profile');
    }

    #[Route('/profile/cover-upload', name: 'dashboard_user_cover_upload', methods: ['POST'])]
    public function uploadCover(
        Request                $request,
        EntityManagerInterface $em,
        FileUploader           $fileUploader
    ): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $coverFile = $request->files->get('coverFile');
        if ($coverFile) {
            $coverFileName = $fileUploader->upload($coverFile, 'covers');
            $user->setCoverPath('uploads/covers/' . $coverFileName);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Photo de couverture mise à jour avec succès !');
        } else {
            $this->addFlash('error', 'Veuillez sélectionner une image.');
        }

        return $this->redirectToRoute('dashboard_user_profile');
    }

    private function getRequest()
    {
        return $this->container->get('request_stack')->getCurrentRequest();
    }
}