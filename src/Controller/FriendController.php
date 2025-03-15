<?php

namespace App\Controller;

use App\Entity\Friend;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FriendController extends AbstractController
{
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;
    public function __construct(NotificationService $notificationService, EntityManagerInterface $entityManager)
    {
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
    }
    /**
     * Envoie une demande d'amitié à un utilisateur donné.
     */
    #[Route('/friend/add/{id}', name: 'app_friend_add', methods: ['POST'])]
    public function addFriend(User $receiver, EntityManagerInterface $em): Response
    {
        $sender = $this->getUser();
        if (!$sender) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        if ($sender->getId() === $receiver->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas vous ajouter vous-même.'], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier si une demande existe déjà (qu'elle soit en attente ou acceptée)
        $friendRepo = $em->getRepository(Friend::class);
        $existingRequest = $friendRepo->findOneBy([
            'sender'   => $sender,
            'receiver' => $receiver,
        ]);
        if ($existingRequest) {
            return $this->json(['error' => 'Une demande d\'amitié existe déjà.'], Response::HTTP_BAD_REQUEST);
        }

        $friend = new Friend();
        $friend->setSender($sender);
        $friend->setReceiver($receiver);
        $friend->setStatus('pending');
        $friend->setCreatedAt(new \DateTimeImmutable());

        $em->persist($friend);
        $em->flush();
        $this->notificationService->sendNotification($receiver,'friend_request', $sender);
        return $this->json([
            'status' => 'pending',
            'message' => 'Demande d\'amitié envoyée.',
            'friendRequestId' => $friend->getId(),
        ]);
    }

    /**
     * Accepte une demande d'amitié.
     */
    #[Route('/friend/accept/{id}', name: 'app_friend_accept', methods: ['POST'])]
    public function acceptFriend(Friend $friend, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        // Seul le destinataire peut accepter la demande
        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $friend->setStatus('accepted');
        $em->flush();
        $this->notificationService->sendNotification($friend->getSender(), 'friend_accept', $user);

        return $this->json([
            'status' => 'accepted',
            'message' => 'Demande d\'amitié acceptée.',
        ]);
    }

    /**
     * Refuse (supprime) une demande d'amitié.
     */
    #[Route('/friend/decline/{id}', name: 'app_friend_decline', methods: ['POST'])]
    public function declineFriend(Friend $friend, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        // Seul le destinataire peut refuser la demande
        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($friend);
        $em->flush();

        return $this->json([
            'status' => 'declined',
            'message' => 'Demande d\'amitié refusée.',
        ]);
    }

    /**
     * Supprime une relation d'amitié existante.
     */
    #[Route('/friend/remove/{friendUserId}', name: 'app_friend_remove', methods: ['POST'])]
    public function removeFriend($friendUserId, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        // Récupérer l'utilisateur ami via son ID
        $friendUser = $em->getRepository(User::class)->find($friendUserId);
        if (!$friendUser) {
            return $this->json(['error' => 'Utilisateur ami introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'amitié existe grâce à la méthode isFriendsWith
        if (!$user->isFriendsWith($friendUser)) {
            return $this->json(['error' => 'Aucune relation d\'amitié trouvée.'], Response::HTTP_BAD_REQUEST);
        }

        // Rechercher la relation d'amitié dans laquelle le user est soit sender soit receiver
        $friendRepo = $em->getRepository(Friend::class);
        $friend = $friendRepo->findOneBy([
            'sender'   => $user,
            'receiver' => $friendUser,
            'status'   => 'accepted'
        ]);
        if (!$friend) {
            $friend = $friendRepo->findOneBy([
                'sender'   => $friendUser,
                'receiver' => $user,
                'status'   => 'accepted'
            ]);
        }
        if (!$friend) {
            return $this->json(['error' => 'Relation d\'amitié introuvée.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($friend);
        $em->flush();

        return $this->json([
            'status'  => 'removed',
            'message' => 'Relation d\'amitié supprimée.'
        ]);
    }

}
