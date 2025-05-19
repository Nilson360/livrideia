<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Service\DeviceDetectorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    private DeviceDetectorService $deviceDetector;

    public function __construct(DeviceDetectorService $deviceDetector)
    {
        $this->deviceDetector = $deviceDetector;
    }

    #[Route('/chat', name: 'app_chat')]
    public function chat(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $friends = $em->getRepository(User::class)->findFriends($user);
        $messageRepo = $em->getRepository(Message::class);

        // Créer un tableau enrichi avec les derniers messages et la dernière activité
        $enrichedFriends = [];
        foreach ($friends as $friend) {
            $lastMessage = $messageRepo->createQueryBuilder('m')
                ->where('(m.sender = :u1 AND m.receiver = :u2) OR (m.sender = :u2 AND m.receiver = :u1)')
                ->setParameter('u1', $user)
                ->setParameter('u2', $friend)
                ->orderBy('m.createdAt', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $enrichedFriends[] = [
                'id' => $friend->getId(),
                'fullName' => $friend->getFullName(),
                'username' => $friend->getUsername(),
                'avatarUrl' => $friend->getAvatarPath() ?? null,
                'lastMessage' => $lastMessage ? $lastMessage->getContent() : 'Aucun message',
                'lastActive' => $lastMessage ? $lastMessage->getCreatedAt()->format('H:i') : '',
            ];
        }

        // Vérifier si un ami spécifique est demandé pour la conversation
        $selectedFriendId = $request->query->get('user');
        $selectedFriend = null;

        if ($selectedFriendId) {
            $selectedFriend = $em->getRepository(User::class)->find($selectedFriendId);

            // Vérifier si c'est bien un ami
            if ($selectedFriend && !in_array($selectedFriend->getId(), array_column($enrichedFriends, 'id'))) {
                $selectedFriend = null;
            }
        }

        $templateData = [
            'friends' => $enrichedFriends,
            'selectedFriend' => $selectedFriend
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('chat/mobile/index.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('chat/index.html.twig', $templateData);
    }

    #[Route('/chat/messages/{userId}', name: 'app_chat_messages')]
    public function getMessages(int $userId, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $friend = $em->getRepository(User::class)->find($userId);
        if (!$friend) {
            return $this->json(['error' => 'User not found'], 404);
        }

        $messages = $em->getRepository(Message::class)
            ->createQueryBuilder('m')
            ->where('(m.sender = :u1 AND m.receiver = :u2) OR (m.sender = :u2 AND m.receiver = :u1)')
            ->setParameter('u1', $user)
            ->setParameter('u2', $friend)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => $message->getSender()->getId(),
                'receiver' => $message->getReceiver()->getId(),
                'createdAt' => $message->getCreatedAt()->format('H:i')
            ];
        }

        return $this->json([
            'messages' => $formattedMessages
        ]);
    }

    #[Route('/chat/conversation/{userId}', name: 'app_chat_conversation')]
    public function viewConversation(int $userId, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $friend = $em->getRepository(User::class)->find($userId);
        if (!$friend) {
            return $this->redirectToRoute('app_chat');
        }

        // Vérifier si c'est un ami
        $friends = $em->getRepository(User::class)->findFriends($user);
        $isFriend = false;
        foreach ($friends as $f) {
            if ($f->getId() === $friend->getId()) {
                $isFriend = true;
                break;
            }
        }

        if (!$isFriend) {
            return $this->redirectToRoute('app_chat');
        }

        $templateData = [
            'friend' => $friend
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('chat/mobile/conversation.html.twig', $templateData);
        }

        // Version desktop - rediriger vers la page principale avec l'ami sélectionné
        return $this->redirectToRoute('app_chat', ['user' => $userId]);
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendMessage(Request $request, EntityManagerInterface $em, HubInterface $hub): JsonResponse
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

        $receiver = $em->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return $this->json(['error' => 'Receiver not found'], 404);
        }

        $message = new Message();
        $message->setSender($user);
        $message->setReceiver($receiver);
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());

        $em->persist($message);
        $em->flush();

        // Publication via Mercure
        $update = new Update(
            "chat_user_" . $receiverId,
            json_encode([
                'senderId' => $user->getId(),
                'content' => $message->getContent(),
                'timestamp' => $message->getCreatedAt()->format('H:i')
            ])
        );
        $hub->publish($update);

        return $this->json([
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => $message->getSender()->getId(),
                'receiver' => $message->getReceiver()->getId(),
                'createdAt' => $message->getCreatedAt()->format('H:i')
            ]
        ]);
    }
}