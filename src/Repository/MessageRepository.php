<?php

namespace App\Repository;

use App\Entity\Message;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    /**
     * Compter les messages non lus pour un utilisateur
     */
    public function countUnreadMessages(User $user): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = :isRead OR m.isRead IS NULL')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marquer les messages comme lus entre deux utilisateurs
     */
    public function markMessagesAsRead(User $receiver, User $sender): void
    {
        $this->createQueryBuilder('m')
            ->update()
            ->set('m.isRead', ':isRead')
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.isRead = :notRead OR m.isRead IS NULL')
            ->setParameter('isRead', true)
            ->setParameter('receiver', $receiver)
            ->setParameter('sender', $sender)
            ->setParameter('notRead', false)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupérer le dernier message entre deux utilisateurs
     */
    public function getLastMessageBetweenUsers(User $user1, User $user2): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :u1 AND m.receiver = :u2) OR (m.sender = :u2 AND m.receiver = :u1)')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Compter les messages non lus d'un expéditeur spécifique
     */
    public function countUnreadMessagesFromSender(User $receiver, User $sender): int
    {
        return $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->where('m.receiver = :receiver')
            ->andWhere('m.sender = :sender')
            ->andWhere('m.isRead = :isRead OR m.isRead IS NULL')
            ->setParameter('receiver', $receiver)
            ->setParameter('sender', $sender)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupérer tous les messages d'une conversation entre deux utilisateurs
     */
    public function getConversationMessages(User $user1, User $user2): array
    {
        return $this->createQueryBuilder('m')
            ->where('(m.sender = :u1 AND m.receiver = :u2) OR (m.sender = :u2 AND m.receiver = :u1)')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Formater les messages pour la réponse JSON
     */
    public function formatMessagesForJson(array $messages): array
    {
        $formattedMessages = [];
        foreach ($messages as $message) {
            $formattedMessages[] = [
                'id' => (int)$message->getId(),
                'content' => $message->getContent(),
                'sender' => (int)$message->getSender()->getId(),
                'receiver' => (int)$message->getReceiver()->getId(),
                'createdAt' => $message->getCreatedAt()->format('H:i'),
                'isRead' => $message->isRead()
            ];
        }
        return $formattedMessages;
    }

    /**
     * Enrichir les données d'amis avec les informations de messages
     */
    public function getEnrichedFriendsData(User $user, array $friends): array
    {
        $enrichedFriends = [];

        foreach ($friends as $friend) {
            $lastMessage = $this->getLastMessageBetweenUsers($user, $friend);
            $unreadCount = $this->countUnreadMessagesFromSender($user, $friend);

            $enrichedFriends[] = [
                'id' => $friend->getId(),
                'fullName' => $friend->getFullName(),
                'username' => $friend->getUsername(),
                'avatarUrl' => $friend->getAvatarPath() ?? null,
                'lastMessage' => $lastMessage ? $lastMessage->getContent() : 'Aucun message',
                'lastActive' => $lastMessage ? $lastMessage->getCreatedAt()->format('H:i') : '',
                'unreadCount' => $unreadCount,
                'hasUnread' => $unreadCount > 0
            ];
        }

        return $enrichedFriends;
    }

    /**
     * Récupérer les conversations récentes d'un utilisateur
     */
    public function getRecentConversations(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('m')
            ->select('m, u')
            ->leftJoin('m.sender', 's')
            ->leftJoin('m.receiver', 'r')
            ->leftJoin('App\Entity\User', 'u', 'WITH', 'u.id = CASE WHEN m.sender = :user THEN m.receiver ELSE m.sender END')
            ->where('m.sender = :user OR m.receiver = :user')
            ->setParameter('user', $user)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les messages non lus les plus récents
     */
    public function getRecentUnreadMessages(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.receiver = :user')
            ->andWhere('m.isRead = :isRead OR m.isRead IS NULL')
            ->setParameter('user', $user)
            ->setParameter('isRead', false)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}