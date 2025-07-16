<?php

namespace App\Controller\App;

use App\Entity\Comment;
use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Repository\FriendRepository;
use App\Repository\LikeRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\DeviceDetectorService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DiscoverController extends AbstractController
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly PostRepository $postRepository,
        private readonly FriendRepository $friendRepository,
        private readonly LikeRepository $likeRepository,
        private readonly DeviceDetectorService $deviceDetector
    ) {}

    /**
     * Page principale de découverte - Auto-détection mobile/desktop
     */
    #[Route('/discover', name: 'app_discover', methods: ['GET'])]
    public function discover(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        // Mettre à jour la dernière activité
        $user->updateLastActivity();
        $this->entityManager->flush();

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->discoverMobile($request);
        }

        return $this->discoverDesktop($request);
    }

    /**
     * Page découverte mobile avec suggestions d'amis
     */
    #[Route('/discover/mobile', name: 'app_discover_mobile', methods: ['GET'])]
    public function discoverMobile(Request $request): Response
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), $limit, ($page - 1) * $limit);
        $totalSuggestions = $this->userRepository->countSuggestedUsers($user->getId());
        $totalPages = ceil($totalSuggestions / $limit);

        $friendRequests = $this->friendRepository->getPendingRequests($user);

        // Enrichir avec amis en commun
        $enrichedUsers = $this->userRepository->enrichUsersWithMutualFriends($user, $suggestedUsers);

        return $this->render('home/mobile/discover/index.html.twig', [
            'enrichedUsers' => $enrichedUsers,
            'friendRequests' => $friendRequests,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalSuggestions' => $totalSuggestions,
            'hasNextPage' => $page < $totalPages,
            'hasPrevPage' => $page > 1
        ]);
    }

    /**
     * Page découverte desktop avec feed et suggestions
     */
    #[Route('/discover/desktop', name: 'app_discover_desktop', methods: ['GET'])]
    public function discoverDesktop(Request $request): Response
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15;

        $discoverPosts = $this->postRepository->getDiscoverPosts($user->getId(), $limit, $page);
        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), 5, 0);
        $totalSuggestions = $this->userRepository->countSuggestedUsers($user->getId());
        $friendRequests = $this->friendRepository->getPendingRequestsLimited($user, 3);

        $trendingBooks = $this->postRepository->getTrendingBooks();
        $activeUsers = $this->userRepository->getMostActiveUsers(5);

        return $this->render('home/desktop/discover/index.html.twig', [
            'discoverPosts' => $discoverPosts,
            'suggestedUsers' => $suggestedUsers,
            'totalSuggestions' => $totalSuggestions,
            'friendRequests' => $friendRequests,
            'trendingBooks' => $trendingBooks,
            'activeUsers' => $activeUsers,
            'currentPage' => $page,
            'hasNextPage' => count($discoverPosts) === $limit
        ]);
    }

    /**
     * API pour charger plus de suggestions (AJAX)
     */
    #[Route('/api/discover/suggestions', name: 'api_discover_suggestions', methods: ['GET'])]
    public function loadMoreSuggestions(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = $request->query->getInt('limit', 10);

        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), $limit, $page);
        $usersData = $this->userRepository->formatUsersForApi($user, $suggestedUsers);

        return $this->json([
            'users' => $usersData,
            'hasNext' => count($suggestedUsers) === $limit
        ]);
    }

    /**
     * Envoi d'une demande d'amitié
     */
    #[Route('/api/discover/friend/add/{id}', name: 'api_discover_friend_add', methods: ['POST'])]
    public function sendFriendRequest(User $receiver): JsonResponse
    {
        $sender = $this->getUser();

        if ($sender->getId() === $receiver->getId()) {
            return $this->json(['error' => 'Vous ne pouvez pas vous ajouter vous-même.'], 400);
        }

        // Vérifier si demande existe déjà (même logique que FriendController)
        $existingRequest = $this->friendRepository->findOneBy([
            'sender' => $sender,
            'receiver' => $receiver,
        ]);

        if ($existingRequest) {
            return $this->json(['error' => 'Une demande d\'amitié existe déjà.'], 400);
        }

        // Vérifier si ils sont déjà amis (relation inverse)
        $existingFriendship = $this->friendRepository->findOneBy([
            'sender' => $receiver,
            'receiver' => $sender,
            'status' => 'accepted'
        ]);

        if ($existingFriendship) {
            return $this->json(['error' => 'Vous êtes déjà amis.'], 400);
        }

        $friend = new Friend();
        $friend->setSender($sender);
        $friend->setReceiver($receiver);
        $friend->setStatus('pending');
        $friend->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($friend);
        $this->entityManager->flush();

        // Envoyer notification
        $this->notificationService->sendNotification(
            $receiver,
            'friend_request',
            $sender
        );

        return $this->json([
            'status' => 'pending',
            'message' => 'Demande d\'amitié envoyée avec succès!',
            'friendRequestId' => $friend->getId(),
        ]);
    }

    /**
     * Accepter une demande d'amitié
     */
    #[Route('/api/discover/friend/accept/{id}', name: 'api_discover_friend_accept', methods: ['POST'])]
    public function acceptFriendRequest(Friend $friend): JsonResponse
    {
        $user = $this->getUser();

        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($friend->getStatus() !== 'pending') {
            return $this->json(['error' => 'Cette demande n\'est plus valide.'], 400);
        }

        $friend->setStatus('accepted');
        $friend->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Notification d'acceptation
        $this->notificationService->sendNotification(
            $friend->getSender(),
            'friend_accept',
            $user
        );

        return $this->json([
            'status' => 'accepted',
            'message' => 'Demande d\'amitié acceptée!',
            'newFriend' => [
                'id' => $friend->getSender()->getId(),
                'fullName' => $friend->getSender()->getFullName(),
                'username' => $friend->getSender()->getUsername(),
                'avatarPath' => $friend->getSender()->getAvatarPath()
            ]
        ]);
    }

    /**
     * Refuser une demande d'amitié
     */
    #[Route('/api/discover/friend/decline/{id}', name: 'api_discover_friend_decline', methods: ['POST'])]
    public function declineFriendRequest(Friend $friend): JsonResponse
    {
        $user = $this->getUser();

        if ($friend->getReceiver()->getId() !== $user->getId()) {
            return $this->json(['error' => 'Accès refusé.'], 403);
        }

        if ($friend->getStatus() !== 'pending') {
            return $this->json(['error' => 'Cette demande n\'est plus valide.'], 400);
        }

        $this->entityManager->remove($friend);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'declined',
            'message' => 'Demande d\'amitié refusée.',
        ]);
    }

    /**
     * Like/Unlike d'un post découvert
     */
    #[Route('/api/discover/post/{id}/like', name: 'api_discover_post_like', methods: ['POST'])]
    public function togglePostLike(Post $post): JsonResponse
    {
        $user = $this->getUser();

        $result = $this->likeRepository->toggleLike($user, $post);

        // Notification si nouveau like et pas le propriétaire
        if ($result['isLiked'] && $post->getUser()->getId() !== $user->getId()) {
            $this->notificationService->sendNotification(
                $post->getUser(),
                'post_like',
                $user,
                $user->getFullName() . ' a aimé votre publication'
            );
        }

        return $this->json([
            'isLiked' => $result['isLiked'],
            'likesCount' => $result['likesCount'],
            'message' => $result['isLiked'] ? 'Publication aimée!' : 'Like retiré'
        ]);
    }

    /**
     * Ajouter un commentaire à un post découvert
     */
    #[Route('/api/discover/post/{id}/comment', name: 'api_discover_post_comment', methods: ['POST'])]
    public function addComment(Post $post, Request $request): JsonResponse
    {
        $user = $this->getUser();
        $content = trim($request->request->get('content', ''));

        if (empty($content)) {
            return $this->json(['error' => 'Le commentaire ne peut pas être vide.'], 400);
        }

        if (strlen($content) > 500) {
            return $this->json(['error' => 'Le commentaire est trop long (500 caractères maximum).'], 400);
        }

        $comment = new Comment();
        $comment->setUser($user);
        $comment->setPost($post);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        // Notification au propriétaire du post
        if ($post->getUser()->getId() !== $user->getId()) {
            $this->notificationService->sendNotification(
                $post->getUser(),
                'post_comment',
                $user
            );
        }

        return $this->json([
            'success' => true,
            'message' => 'Commentaire ajouté!',
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $user->getId(),
                    'fullName' => $user->getFullName(),
                    'username' => $user->getUsername(),
                    'avatarPath' => $user->getAvatarPath()
                ]
            ]
        ]);
    }

    /**
     * Recherche dans la découverte
     */
    #[Route('/api/discover/search', name: 'api_discover_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $query = trim($request->query->get('q', ''));
        $type = $request->query->get('type', 'all');

        if (strlen($query) < 2) {
            return $this->json(['error' => 'La recherche doit contenir au moins 2 caractères.'], 400);
        }

        $results = [];

        if ($type === 'users' || $type === 'all') {
            $users = $this->userRepository->searchUsers($query, $user->getId(), 10);
            $results['users'] = $this->userRepository->formatUsersForSearch($user, $users);
        }

        if ($type === 'posts' || $type === 'all') {
            $posts = $this->postRepository->searchPosts($query, $user->getId(), 10);
            $results['posts'] = $this->postRepository->formatPostsForSearch($posts);
        }

        return $this->json($results);
    }
}