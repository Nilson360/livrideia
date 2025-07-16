<?php

namespace App\Controller\App;

use App\Entity\Friend;
use App\Entity\User;
use App\Repository\FriendRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class FriendController extends AbstractController
{
    public function __construct(
        private readonly NotificationService    $notificationService,
        private readonly EntityManagerInterface $em,
        private readonly UserRepository         $userRepository,
        private readonly FriendRepository       $friendRepository,
    )
    {

    }

    /**
     * Affiche toutes les suggestions d'amitié
     */
    #[Route('/friends/suggestions', name: 'app_friends_suggestions', methods: ['GET'])]
    public function suggestions(Request $request): Response
    {
        $user = $this->getUser();

        // Pagination
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12; // 12 suggestions par page

        // Utiliser la méthode existante du repository avec pagination
        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), $limit, $page);
        $totalSuggestions = $this->userRepository->countSuggestedUsers($user->getId());
        $totalPages = ceil($totalSuggestions / $limit);

        // Récupérer les demandes d'amitié en attente (pour la sidebar)
        $friendRequests = $this->em->getRepository(Friend::class)->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ]);

        return $this->render('home/desktop/friends/suggestions.html.twig', [
            'suggestedUsers' => $suggestedUsers,
            'friendRequests' => $friendRequests,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalSuggestions' => $totalSuggestions,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1
        ]);
    }

    /**
     * Envoie une demande d'amitié à un utilisateur donné.
     */
    #[Route('/friend/add/{id}', name: 'app_friend_add', methods: ['POST'])]
    public function addFriend(User $receiver): Response
    {
        $sender = $this->getUser();

        if ($sender->getId() === $receiver->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas vous ajouter vous-même.'], Response::HTTP_BAD_REQUEST);
        }

        $existingRequest = $this->friendRepository->findOneBy([
            'sender' => $sender,
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

        $this->em->persist($friend);
        $this->em->flush();

        $this->notificationService->sendNotification($receiver, 'friend_request', $sender);

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
    public function acceptFriend(Friend $friend): Response
    {
        $user = $this->getUser();

        // Seul le destinataire peut accepter la demande
        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $friend->setStatus('accepted');
        $this->em->flush();

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
    public function declineFriend(Friend $friend): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        // Seul le destinataire peut refuser la demande
        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $this->em->remove($friend);
        $this->em->flush();

        return $this->json([
            'status' => 'declined',
            'message' => 'Demande d\'amitié refusée.',
        ]);
    }

    /**
     * Supprime une relation d'amitié existante.
     */
    #[Route('/friend/remove/{friendUserId}', name: 'app_friend_remove', methods: ['POST'])]
    public function removeFriend($friendUserId): Response
    {
        $user = $this->getUser();

        // Récupérer l'utilisateur ami via son ID
        $friendUser = $this->userRepository->find($friendUserId);
        if (!$friendUser) {
            return $this->json(['error' => 'Utilisateur ami introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que l'amitié existe grâce à la méthode isFriendsWith
        if (!$user->isFriendsWith($friendUser)) {
            return $this->json(['error' => 'Aucune relation d\'amitié trouvée.'], Response::HTTP_BAD_REQUEST);
        }

        // Rechercher la relation d'amitié dans laquelle le user est soit sender soit receiver
        $friendRepo = $this->em->getRepository(Friend::class);
        $friend = $friendRepo->findOneBy([
            'sender' => $user,
            'receiver' => $friendUser,
            'status' => 'accepted'
        ]);

        if (!$friend) {
            $friend = $friendRepo->findOneBy([
                'sender' => $friendUser,
                'receiver' => $user,
                'status' => 'accepted'
            ]);
        }

        if (!$friend) {
            return $this->json(['error' => 'Relation d\'amitié introuvée.'], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($friend);
        $this->em->flush();

        return $this->json([
            'status' => 'removed',
            'message' => 'Relation d\'amitié supprimée.'
        ]);
    }

    #[Route('/friends/', name: 'app_friends', methods: ['GET'])]
    public function friends(EntityManagerInterface $em): Response
    {
        return $this->json(['error' => 'Not implemented yet.']);
    }
}