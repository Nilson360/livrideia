<?php

namespace App\Controller\DashboardUser;

use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Form\ProfileType;
use App\Form\ChangePasswordType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/dashboard-user')]
class UserProfileController extends AbstractController
{
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
        return $this->render('dashboard_user/profile.html.twig', [
            'user' => $user,
            'posts' => $posts,
            'friends' => $friendRelationships,
        ]);
    }

    #[Route('/profile/edit', name: 'dashboard_user_profile_edit', methods: ['GET', 'POST'])]
    public function editProfile(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();

            $this->addFlash('success', 'Profil mis à jour avec succès !');
            return $this->redirectToRoute('dashboard-user-profile');
        }

        return $this->render('dashboard_user/edit_profile.html.twig', [
            'form' => $form->createView(),
        ]);
    }
    #[Route('/profile/change-password', name: 'dashboard_user_profile_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $em
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Formulaire dédié au changement de mot de passe
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data       = $form->getData();
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
                return $this->redirectToRoute('dashboard-user-profile');
            } else {
                $this->addFlash('error', 'Ancien mot de passe incorrect.');
            }
        }

        return $this->render('dashboard_user/change_password.html.twig', [
            'form' => $form->createView(),
        ]);
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

        return $this->render('dashboard_user/friends.html.twig', [
            'friends' => $friends,
        ]);
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

        return $this->render('dashboard_user/profile_other.html.twig', [
            'user' => $user,
            'posts' => $user->getPosts(),
        ]);
    }
}
