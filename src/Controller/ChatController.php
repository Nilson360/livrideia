<?php

namespace App\Controller;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    public function chat(EntityManagerInterface $em): Response
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
                'avatarUrl' => null, // à adapter si tu gères des avatars
                'lastMessage' => $lastMessage ? $lastMessage->getContent() : 'Aucun message',
                'lastActive' => $lastMessage ? $lastMessage->getCreatedAt()->format('H:i') : '',
            ];
        }

        return $this->render('chat/index.html.twig', [
            'friends' => $enrichedFriends
        ]);
    }

}