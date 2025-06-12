<?php

namespace App\Controller\DashboardUser;

use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\PostFormType;
use App\Form\ProfileType;
use App\Repository\FriendRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\DeviceDetectorService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/dashboard-user')]
#[IsGranted('ROLE_USER')]
class UserProfileController extends AbstractController
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

    #[Route('/profile', name: 'dashboard_user_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $posts = $this->postRepository->findBy(['user' => $user], ['createdAt' => 'DESC']);

        // Récupération des relations d'amitié acceptées pour l'utilisateur connecté
        $friendRepo = $this->em->getRepository(Friend::class);
        $sentFriends = $friendRepo->findBy(['sender' => $user, 'status' => 'accepted']);
        $receivedFriends = $friendRepo->findBy(['receiver' => $user, 'status' => 'accepted']);

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $postImagePath = $form->get('postFile')->getData();
            $postFileName = $this->fileUploader->upload($postImagePath, 'posts');
            $post->setUser($user);
            $post->setImagePath($postFileName);
            $this->em->persist($post);
            $this->em->flush();
            return $this->redirectToRoute('dashboard_user_profile');
        }

        // Fusionner les relations
        $friendRelationships = array_merge($sentFriends, $receivedFriends);

        $templateData = [
            'user' => $user,
            'posts' => $posts,
            'friends' => $friendRelationships,
            'form' => $form->createView(),
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/index.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/desktop/profile.html.twig', $templateData);
    }

    #[Route('/profile/edit', name: 'dashboard_user_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'avatar
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $avatarFileName = $this->fileUploader->upload($avatarFile, 'avatars');
                $user->setAvatarPath('uploads/avatars/' . $avatarFileName);
            }

            // Gestion de la photo de couverture
            $coverFile = $form->get('coverFile')->getData();
            if ($coverFile) {
                $coverFileName = $this->fileUploader->upload($coverFile, 'covers');
                $user->setCoverPath('uploads/covers/' . $coverFileName);
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $this->em->persist($user);
            $this->em->flush();

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
        return $this->render('dashboard_user/desktop/edit_profile.html.twig', $templateData);
    }

    #[Route('/profile/change-password', name: 'dashboard_user_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request                     $request,
        UserPasswordHasherInterface $passwordHasher,
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

                $this->em->persist($user);
                $this->em->flush();

                $this->addFlash('success', 'Mot de passe mis à jour avec succès !');
                return $this->redirectToRoute('dashboard_user_profile');
            } else {
                $this->addFlash('error', 'Ancien mot de passe incorrect.');
            }
        }

        $templateData = [
            'form' => $form->createView(),
        ];

        if ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/change_password.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/desktop/change_password.html.twig', $templateData);
    }

    #[Route('/profile/{id}/friends', name: 'app_profile_friends', methods: ['GET'])]
    public function friends(User $user): Response
    {
        // Récupérer les relations d'amitié acceptées pour l'utilisateur
        $friendRepo = $this->friendRepository;
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

        if ($this->deviceDetector->isMobile() && $this->requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            return $this->render('dashboard_user/mobile/profile/friends_list.html.twig', $templateData);
        } elseif ($this->deviceDetector->isMobile()) {
            return $this->render('dashboard_user/mobile/profile/friends.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('dashboard_user/desktop/friends.html.twig', $templateData);
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
            return $this->render('dashboard_user/mobile/profile/profile_other.html.twig', $templateData);
        }

        return $this->render('dashboard_user/desktop/profile_other.html.twig', $templateData);
    }

    #[Route('/profile/image-upload/{type}', name: 'dashboard_user_image_upload', requirements: ['type' => 'avatar|cover'], methods: ['POST'])]
    public function uploadImage(Request $request, string $type): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $fileField = $type === 'avatar' ? 'avatarFile' : 'coverFile';
        $folder = $type === 'avatar' ? 'avatars' : 'covers';
        $setter = $type === 'avatar' ? 'setAvatarPath' : 'setCoverPath';

        $file = $request->files->get($fileField);
        if ($file) {
            $fileName = $this->fileUploader->upload($file, $folder);
            $user->$setter('uploads/' . $folder . '/' . $fileName);
            $user->setUpdatedAt(new \DateTimeImmutable());

            $this->em->persist($user);
            $this->em->flush();

            $this->addFlash('success',
                $type === 'avatar'
                    ? 'Photo de profil mise à jour avec succès !'
                    : 'Photo de couverture mise à jour avec succès !'
            );
        } else {
            $this->addFlash('error', 'Veuillez sélectionner une image.');
        }

        return $this->redirectToRoute('dashboard_user_profile');
    }
}