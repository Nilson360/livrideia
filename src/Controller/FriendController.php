<?php

namespace App\Controller;

use App\Entity\Friend;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class FriendController extends AbstractController
{
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
    #[Route('/friend/remove/{id}', name: 'app_friend_remove', methods: ['POST'])]
    public function removeFriend(Friend $friend, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        // L'expéditeur ou le destinataire peuvent supprimer la relation
        if ($friend->getSender()->getId() !== $user->getId() &&
            $friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($friend);
        $em->flush();

        return $this->json([
            'status' => 'removed',
            'message' => 'Relation d\'amitié supprimée.',
        ]);
    }
}
