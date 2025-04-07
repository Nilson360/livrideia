<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use App\Repository\MessageRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;

class MessageController extends AbstractController
{
    private MessageRepository $messageRepository;
    private EntityManagerInterface $em;
    private NotificationService $notificationService;
    private HubInterface $hub;

    public function __construct(MessageRepository $messageRepository, EntityManagerInterface $entityManager, NotificationService $notificationService, HubInterface $hub)
    {
        $this->messageRepository = $messageRepository;
        $this->em = $entityManager;
        $this->notificationService = $notificationService;
        $this->hub = $hub;
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    public function sendMessage(Request $request): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $receiverId = $request->request->get('receiver_id');
        $content = trim($request->request->get('content'));

        if (empty($content)) {
            return $this->json(['error' => 'Le message ne peut être vide.'], Response::HTTP_BAD_REQUEST);
        }

        $receiver = $this->em->getRepository(User::class)->find($receiverId);
        if (!$receiver) {
            return $this->json(['error' => 'Destinataire introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Vérifie que les utilisateurs sont amis
        if (!$user->isFriendsWith($receiver)) {
            return $this->json(['error' => 'Vous devez être amis pour discuter.'], Response::HTTP_FORBIDDEN);
        }

        $message = new Message();
        $message->setSender($user);
        $message->setReceiver($receiver);
        $message->setContent($content);

        $this->em->persist($message);
        $this->em->flush();
        $update = new Update(
            'chat_user_' . $receiver->getId(),
            json_encode([
                'messageId' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => $user->getUsername(),
                'createdAt' => $message->getCreatedAt()->format('d/m/Y H:i')
            ]),
            true
        );
        $this->hub->publish($update);

        return $this->json([
            'status' => 'Message envoyé',
            'message' => [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => $user->getUsername(),
                'createdAt' => $message->getCreatedAt()->format('d/m/Y H:i')
            ]
        ]);
    }

    #[Route('/chat/messages/{friendId}', name: 'app_chat_messages', methods: ['GET'])]
    public function getMessages(int $friendId): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $friend = $this->em->getRepository(User::class)->find($friendId);
        if (!$friend) {
            return $this->json(['error' => 'Utilisateur introuvable.'], Response::HTTP_NOT_FOUND);
        }

        if (!$user->isFriendsWith($friend)) {
            return $this->json(['error' => 'Vous devez être amis pour voir ces messages.'], Response::HTTP_FORBIDDEN);
        }

        $query = $this->em->createQuery(
            'SELECT m FROM App\Entity\Message m
         WHERE (m.sender = :user AND m.receiver = :friend)
            OR (m.sender = :friend AND m.receiver = :user)
         ORDER BY m.createdAt ASC'
        );
        $query->setParameters([
            'user' => $user,
            'friend' => $friend,
        ]);

        $messages = $query->getResult();

        return $this->json([
            'messages' => array_map(fn($msg) => [
                'id' => $msg->getId(),
                'content' => $msg->getContent(),
                'sender' => $msg->getSender()->getId(),
                'createdAt' => $msg->getCreatedAt()->format('d/m/Y H:i'),
            ], $messages)
        ]);
    }

}