<?php

namespace App\Controller;

use App\Entity\Friend;
use App\Entity\User;
use App\Entity\Post;
use App\Entity\Like;
use App\Entity\Comment;
use App\Repository\UserRepository;
use App\Repository\PostRepository;
use App\Service\NotificationService;
use App\Service\DeviceDetectorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DiscoverController extends AbstractController
{
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private PostRepository $postRepository;
    private DeviceDetectorService $deviceDetector;

    public function __construct(
        NotificationService $notificationService,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        PostRepository $postRepository,
        DeviceDetectorService $deviceDetector
    ) {
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
        $this->userRepository = $userRepository;
        $this->postRepository = $postRepository;
        $this->deviceDetector = $deviceDetector;
    }

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

        // Mettre à jour la dernière atividade do usuário
        $user->updateLastActivity();
        $this->entityManager->flush();

        // Détecter le type d'appareil
        if ($this->deviceDetector->isMobile()) {
            return $this->discoverMobile($request);
        } else {
            return $this->discoverDesktop($request);
        }
    }

    /**
     * Page découverte mobile avec suggestions d'amis
     */
    #[Route('/discover/mobile', name: 'app_discover_mobile', methods: ['GET'])]
    public function discoverMobile(Request $request): Response
    {
        $user = $this->getUser();

        // Pagination pour les suggestions
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10; // 10 suggestions par page sur mobile

        // Obtenir les suggestions d'amis
        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), $limit, $page);
        $totalSuggestions = $this->userRepository->countSuggestedUsers($user->getId());
        $totalPages = ceil($totalSuggestions / $limit);

        // Récupérer les demandes d'amitié en attente
        $friendRequests = $this->entityManager->getRepository(Friend::class)->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ], ['createdAt' => 'DESC']);

        // Ajouter informations sur les amis en commun
        foreach ($suggestedUsers as $suggestedUser) {
            $suggestedUser->mutualFriends = $this->getMutualFriends($user, $suggestedUser);
        }

        return $this->render('home/mobile/discover/index.html.twig', [
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
     * Page découverte desktop avec feed et suggestions
     */
    #[Route('/discover/desktop', name: 'app_discover_desktop', methods: ['GET'])]
    public function discoverDesktop(Request $request): Response
    {
        $user = $this->getUser();

        // Pagination pour le feed
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 15;

        // Posts découverte - de personnes qui ne sont pas amies
        $discoverPosts = $this->postRepository->getDiscoverPosts($user->getId(), $limit, $page);

        // Suggestions d'amis limitées pour la sidebar
        $suggestedUsers = $this->userRepository->getSuggestedUsersWithPagination($user->getId(), 5, 1);

        // Demandes d'amitié
        $friendRequests = $this->entityManager->getRepository(Friend::class)->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ], ['createdAt' => 'DESC'], 3); // Limiter à 3 pour la sidebar

        // Tendances et stats
        $trendingBooks = $this->getTrendingBooks();
        $activeUsers = $this->getActiveUsers(5);

        return $this->render('home/desktop/discover/index.html.twig', [
            'discoverPosts' => $discoverPosts,
            'suggestedUsers' => $suggestedUsers,
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

        // Ajouter les amis en commun
        $usersData = [];
        foreach ($suggestedUsers as $suggestedUser) {
            $mutualFriends = $this->getMutualFriends($user, $suggestedUser);

            $usersData[] = [
                'id' => $suggestedUser->getId(),
                'username' => $suggestedUser->getUsername(),
                'fullName' => $suggestedUser->getFullName(),
                'avatarPath' => $suggestedUser->getAvatarPath(),
                'bio' => $suggestedUser->getBio(),
                'mutualFriendsCount' => count($mutualFriends),
                'isOnline' => $this->isUserOnline($suggestedUser)
            ];
        }

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

        // Vérifier si une demande existe déjà
        $existingRequest = $this->entityManager->getRepository(Friend::class)->findOneBy([
            'sender' => $sender,
            'receiver' => $receiver,
        ]);

        if ($existingRequest) {
            return $this->json(['error' => 'Une demande d\'amitié existe déjà.'], 400);
        }

        // Vérifier si ils sont déjà amis (relation inverse)
        $existingFriendship = $this->entityManager->getRepository(Friend::class)->findOneBy([
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
            $sender,
            'Nouvelle demande d\'amitié de ' . $sender->getFullName()
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
        $friend->setAcceptedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        // Notification d'acceptation
        $this->notificationService->sendNotification(
            $friend->getSender(),
            'friend_accept',
            $user,
            $user->getFullName() . ' a accepté votre demande d\'amitié!'
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

        $existingLike = $this->entityManager->getRepository(Like::class)->findOneBy([
            'user' => $user,
            'post' => $post
        ]);

        if ($existingLike) {
            // Unlike
            $this->entityManager->remove($existingLike);
            $isLiked = false;
        } else {
            // Like
            $like = new Like();
            $like->setUser($user);
            $like->setPost($post);
            $like->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($like);
            $isLiked = true;

            // Notification au propriétaire du post (sauf si c'est le même utilisateur)
            if ($post->getUser()->getId() !== $user->getId()) {
                $this->notificationService->sendNotification(
                    $post->getUser(),
                    'post_like',
                    $user,
                    $user->getFullName() . ' a aimé votre publication'
                );
            }
        }

        $this->entityManager->flush();

        // Recompter les likes
        $likesCount = $this->entityManager->getRepository(Like::class)->count(['post' => $post]);

        return $this->json([
            'isLiked' => $isLiked,
            'likesCount' => $likesCount,
            'message' => $isLiked ? 'Publication aimée!' : 'Like retiré'
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
                $user,
                $user->getFullName() . ' a commenté votre publication'
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
        $type = $request->query->get('type', 'all'); // 'users', 'posts', 'all'

        if (strlen($query) < 2) {
            return $this->json(['error' => 'La recherche doit contenir au moins 2 caractères.'], 400);
        }

        $results = [];

        if ($type === 'users' || $type === 'all') {
            $users = $this->userRepository->searchUsers($query, $user->getId(), 10);
            $results['users'] = array_map(function($u) use ($user) {
                return [
                    'id' => $u->getId(),
                    'fullName' => $u->getFullName(),
                    'username' => $u->getUsername(),
                    'avatarPath' => $u->getAvatarPath(),
                    'bio' => $u->getBio(),
                    'mutualFriendsCount' => count($this->getMutualFriends($user, $u)),
                    'isFriend' => $user->isFriendsWith($u)
                ];
            }, $users);
        }

        if ($type === 'posts' || $type === 'all') {
            $posts = $this->postRepository->searchPosts($query, $user->getId(), 10);
            $results['posts'] = array_map(function($p) {
                return [
                    'id' => $p->getId(),
                    'content' => substr($p->getContent(), 0, 150) . '...',
                    'createdAt' => $p->getCreatedAt()->format('Y-m-d H:i:s'),
                    'user' => [
                        'fullName' => $p->getUser()->getFullName(),
                        'username' => $p->getUser()->getUsername(),
                        'avatarPath' => $p->getUser()->getAvatarPath()
                    ]
                ];
            }, $posts);
        }

        return $this->json($results);
    }

    // ====== MÉTODOS AUXILIARES ======

    /**
     * Obter amigos em comum entre dois usuários
     */
    private function getMutualFriends(User $user1, User $user2): array
    {
        return $this->userRepository->getMutualFriends($user1->getId(), $user2->getId());
    }

    /**
     * Verificar se usuário está online (você pode implementar sua própria lógica)
     */
    private function isUserOnline(User $user): bool
    {
        // Implementar lógica de usuário online
        // Por exemplo, verificar última atividade nos últimos 5 minutos
        return $user->getLastActivity() &&
            $user->getLastActivity() > new \DateTimeImmutable('-5 minutes');
    }

    /**
     * Obter livros em tendência
     */
    private function getTrendingBooks(): array
    {
        // Implementação simples baseada em posts que mencionam livros
        // Você pode melhorar isso baseado em sua estrutura de dados de livros

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(Post::class, 'p')
            ->where('p.content LIKE :book1 OR p.content LIKE :book2 OR p.content LIKE :livre')
            ->andWhere('p.createdAt > :lastMonth')
            ->andWhere('p.visibility = :public OR p.visibility IS NULL')
            ->setParameter('book1', '%#livre%')
            ->setParameter('book2', '%#book%')
            ->setParameter('livre', '%livre%')
            ->setParameter('lastMonth', new \DateTimeImmutable('-1 month'))
            ->setParameter('public', 'public')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(5);

        $posts = $qb->getQuery()->getResult();

        // Extrair informações de livros dos posts (placeholder)
        $books = [];
        foreach ($posts as $post) {
            // Lógica simples para extrair nome de livros
            if (preg_match('/"([^"]+)"/', $post->getContent(), $matches)) {
                $books[] = [
                    'title' => $matches[1],
                    'mentions' => 1, // Você pode implementar contagem real
                    'post' => $post
                ];
            }
        }

        return array_slice($books, 0, 5);
    }

    /**
     * Obter usuários mais ativos
     */
    private function getActiveUsers(int $limit = 5): array
    {
        return $this->userRepository->getMostActiveUsers($limit);
    }
}