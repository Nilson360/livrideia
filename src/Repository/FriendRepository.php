<?php

namespace App\Repository;

use App\Entity\Friend;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Friend>
 */
class FriendRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Friend::class);
    }

    /**
     * Récupérer les demandes d'amitié en attente pour un utilisateur
     */
    public function getPendingRequests(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.receiver = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les demandes d'amitié en attente limitées
     */
    public function getPendingRequestsLimited(User $user, int $limit): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.receiver = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifier si une demande d'amitié existe déjà
     */
    public function requestExists(User $sender, User $receiver): bool
    {
        $count = $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('((f.sender = :sender AND f.receiver = :receiver) OR (f.sender = :receiver AND f.receiver = :sender))')
            ->setParameter('sender', $sender)
            ->setParameter('receiver', $receiver)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Créer une nouvelle demande d'amitié
     */
    public function createFriendRequest(User $sender, User $receiver): Friend
    {
        $friend = new Friend();
        $friend->setSender($sender);
        $friend->setReceiver($receiver);
        $friend->setStatus('pending');
        $friend->setCreatedAt(new \DateTimeImmutable());

        $this->getEntityManager()->persist($friend);
        $this->getEntityManager()->flush();

        return $friend;
    }

    /**
     * Accepter une demande d'amitié
     */
    public function acceptFriendRequest(Friend $friend): void
    {
        $friend->setStatus('accepted');
        $friend->setUpdatedAt(new \DateTimeImmutable());
        $this->getEntityManager()->flush();
    }

    /**
     * Refuser une demande d'amitié
     */
    public function declineFriendRequest(Friend $friend): void
    {
        $this->getEntityManager()->remove($friend);
        $this->getEntityManager()->flush();
    }

    /**
     * Récupérer toutes les amitiés acceptées d'un utilisateur
     */
    public function getAcceptedFriends(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('(f.sender = :user OR f.receiver = :user)')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->orderBy('f.acceptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les demandes d'amitié en attente pour un utilisateur
     */
    public function countPendingRequests(User $user): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('f.receiver = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupérer les demandes envoyées par un utilisateur
     */
    public function getSentRequests(User $user): array
    {
        return $this->createQueryBuilder('f')
            ->where('f.sender = :user')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Annuler une demande d'amitié envoyée
     */
    public function cancelSentRequest(User $sender, User $receiver): bool
    {
        $friend = $this->createQueryBuilder('f')
            ->where('f.sender = :sender')
            ->andWhere('f.receiver = :receiver')
            ->andWhere('f.status = :status')
            ->setParameter('sender', $sender)
            ->setParameter('receiver', $receiver)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getOneOrNullResult();

        if ($friend) {
            $this->getEntityManager()->remove($friend);
            $this->getEntityManager()->flush();
            return true;
        }

        return false;
    }

    /**
     * Supprimer une amitié (unfriend)
     */
    public function removeFriendship(User $user1, User $user2): bool
    {
        $friendship = $this->createQueryBuilder('f')
            ->where('((f.sender = :u1 AND f.receiver = :u2) OR (f.sender = :u2 AND f.receiver = :u1))')
            ->andWhere('f.status = :status')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getOneOrNullResult();

        if ($friendship) {
            $this->getEntityManager()->remove($friendship);
            $this->getEntityManager()->flush();
            return true;
        }

        return false;
    }

    /**
     * Obtenir le statut d'amitié entre deux utilisateurs
     */
    public function getFriendshipStatus(User $user1, User $user2): ?string
    {
        $friendship = $this->createQueryBuilder('f')
            ->where('((f.sender = :u1 AND f.receiver = :u2) OR (f.sender = :u2 AND f.receiver = :u1))')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->getQuery()
            ->getOneOrNullResult();

        return $friendship ? $friendship->getStatus() : null;
    }

    /**
     * Récupérer les amitiés récentes (acceptées récemment)
     */
    public function getRecentFriendships(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('f')
            ->where('(f.sender = :user OR f.receiver = :user)')
            ->andWhere('f.status = :status')
            ->andWhere('f.updatedAt IS NOT NULL')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->orderBy('f.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le nombre total d'amis d'un utilisateur
     */
    public function countFriends(User $user): int
    {
        return $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->where('(f.sender = :user OR f.receiver = :user)')
            ->andWhere('f.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouver des amitiés par période
     */
    public function getFriendshipsByPeriod(User $user, \DateTimeImmutable $since): array
    {
        return $this->createQueryBuilder('f')
            ->where('(f.sender = :user OR f.receiver = :user)')
            ->andWhere('f.status = :status')
            ->andWhere('f.updatedAt > :since')
            ->setParameter('user', $user)
            ->setParameter('status', 'accepted')
            ->setParameter('since', $since)
            ->orderBy('f.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}