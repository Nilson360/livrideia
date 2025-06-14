<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Repository\UserRepository;
use App\Service\DeviceDetectorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly DeviceDetectorService $deviceDetector,
        private readonly EntityManagerInterface $em,
        private readonly MessageRepository $messageRepository,
        private readonly UserRepository $userRepository,
        private readonly HubInterface $hub
    ) {}

    #[Route('/chat', name: 'app_chat')]
    public function chat(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $friends = $this->userRepository->findFriends($user);
        $enrichedFriends = $this->messageRepository->getEnrichedFriendsData($user, $friends);

        // Vérifier si un ami spécifique est demandé pour la conversation
        $selectedFriendId = $request->query->get('user');
        $selectedFriend = null;

        if ($selectedFriendId) {
            $selectedFriend = $this->userRepository->find($selectedFriendId);

            // Vérifier si c'est bien un ami
            if ($selectedFriend && !in_array($selectedFriend->getId(), array_column($enrichedFriends, 'id'))) {
                $selectedFriend = null;
            }
        }

        $totalUnreadCount = $this->messageRepository->countUnreadMessages($user);

        $templateData = [
            'friends' => $enrichedFriends,
            'selectedFriend' => $selectedFriend,
            'totalUnreadCount' => $totalUnreadCount
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('chat/mobile/index.html.twig', $templateData);
        }

        return $this->render('chat/desktop/index.html.twig', $templateData);
    }

    #[Route('/chat/messages/{userId}', name: 'app_chat_messages')]
    public function getMessages(int $userId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $friend = $this->userRepository->find($userId);
        if (!$friend) {
            return $this->json(['error' => 'User not found'], 404);
        }

        // Marcar mensagens como lidas
        $this->messageRepository->markMessagesAsRead($user, $friend);
        $this->em->flush();

        $messages = $this->messageRepository->getConversationMessages($user, $friend);
        $formattedMessages = $this->messageRepository->formatMessagesForJson($messages);

        // Nova contagem de mensagens não lidas após marcar como lidas
        $newUnreadCount = $this->messageRepository->countUnreadMessages($user);

        return $this->json([
            'messages' => $formattedMessages,
            'newUnreadCount' => $newUnreadCount
        ]);
    }

    #[Route('/chat/conversation/{userId}', name: 'app_chat_conversation')]
    public function viewConversation(int $userId): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $friend = $this->userRepository->find($userId);
        if (!$friend) {
            return $this->redirectToRoute('app_chat');
        }

        // Vérifier si c'est un ami
        if (!$this->userRepository->areFriends($user, $friend)) {
            return $this->redirectToRoute('app_chat');
        }

        // Marcar mensagens como lidas quando entrar na conversa
        $this->messageRepository->markMessagesAsRead($user, $friend);
        $this->em->flush();

        $templateData = [
            'friend' => $friend,
            'totalUnreadCount' => $this->messageRepository->countUnreadMessages($user)
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('chat/mobile/conversation.html.twig', $templateData);
        }

        // Version desktop - rediriger vers la page principale avec l'ami sélectionné
        return $this->redirectToRoute('app_chat', ['user' => $userId]);
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $content = $request->request->get('content');
        $receiverId = $request->request->get('receiver_id');

        if (!$content || !$receiverId) {
            return $this->json(['error' => 'Missing parameters'], 400);
        }

        $receiver = $this->userRepository->find($receiverId);
        if (!$receiver) {
            return $this->json(['error' => 'Receiver not found'], 404);
        }

        $message = new Message();
        $message->setSender($user);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setIsRead(false);

        $this->em->persist($message);
        $this->em->flush();

        // Nova contagem de mensagens não lidas para o destinatário
        $receiverUnreadCount = $this->messageRepository->countUnreadMessages($receiver);

        // Publication via Mercure
        $update = new Update(
            "chat_user_" . $receiverId,
            json_encode([
                'senderId' => $user->getId(),
                'senderName' => $user->getFullName(),
                'content' => $message->getContent(),
                'timestamp' => $message->getCreatedAt()->format('H:i'),
                'unreadCount' => $receiverUnreadCount
            ])
        );
        $this->hub->publish($update);

        return $this->json([
            'message' => [
                'id' => (int)$message->getId(),
                'content' => $message->getContent(),
                'sender' => (int)$message->getSender()->getId(),
                'receiver' => (int)$message->getReceiver()->getId(),
                'createdAt' => $message->getCreatedAt()->format('H:i')
            ]
        ]);
    }

    #[Route('/api/chat/unread-count', name: 'app_chat_unread_count', methods: ['GET'])]
    public function getUnreadCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $unreadCount = $this->messageRepository->countUnreadMessages($user);

        return $this->json([
            'unreadCount' => $unreadCount
        ]);
    }

    #[Route('/chat/current-user-id', name: 'app_chat_current_user_id', methods: ['GET'])]
    public function getCurrentUserId(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'userId' => $user->getId()
        ]);
    }
}