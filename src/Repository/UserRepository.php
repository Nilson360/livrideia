<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Obtenir les utilisateurs suggérés pour ajouter comme amis
     */
    public function getSuggestedUsers(int $userId): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.id != :userId')
            ->setParameter('userId', $userId)
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fr.sender) FROM App\Entity\Friend fr WHERE fr.receiver = :userId AND fr.status IN (:statuses)
            )')
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fs.receiver) FROM App\Entity\Friend fs WHERE fs.sender = :userId AND fs.status IN (:statuses)
            )')
            ->setParameter('statuses', ['pending', 'accepted'])
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère tous les amis d'un utilisateur donné
     */
    public function findFriends(User $user): array
    {
        $entityManager = $this->getEntityManager();
        $query = $entityManager->createQuery(
            'SELECT u FROM App\Entity\User u
            WHERE u.id IN (
                SELECT CASE 
                    WHEN f.sender = :user THEN IDENTITY(f.receiver) 
                    ELSE IDENTITY(f.sender) 
                END
                FROM App\Entity\Friend f
                WHERE (f.sender = :user OR f.receiver = :user) AND f.status = :status
            )'
        )->setParameter('user', $user)
            ->setParameter('status', 'accepted');

        return $query->getResult();
    }

    /**
     * Vérifier si deux utilisateurs sont amis
     */
    public function areFriends(User $user1, User $user2): bool
    {
        $count = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(f.id)')
            ->from('App\Entity\Friend', 'f')
            ->where('((f.sender = :u1 AND f.receiver = :u2) OR (f.sender = :u2 AND f.receiver = :u1))')
            ->andWhere('f.status = :status')
            ->setParameter('u1', $user1)
            ->setParameter('u2', $user2)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Chercher les utilisateurs par nom ou username
     */
    public function searchByNameOrUsername(?string $query): array
    {
        // Si la query est null ou vide, retourner un tableau vide
        if (empty($query) || trim($query) === '') {
            return [];
        }

        // Nettoyer et valider la query
        $cleanQuery = trim($query);

        // Si la query est trop courte, retourner un tableau vide
        if (strlen($cleanQuery) < 2) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) LIKE :query')
            ->orWhere('LOWER(u.fullName) LIKE :query')
            ->setParameter('query', '%' . strtolower($cleanQuery) . '%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les suggestions d'utilisateurs avec pagination
     */
    public function getSuggestedUsersWithPagination(int $userId, int $limit, int $offset): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.id != :userId')
            ->setParameter('userId', $userId)
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fr.sender) FROM App\Entity\Friend fr WHERE fr.receiver = :userId AND fr.status IN (:statuses)
            )')
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fs.receiver) FROM App\Entity\Friend fs WHERE fs.sender = :userId AND fs.status IN (:statuses)
            )')
            ->setParameter('statuses', ['pending', 'accepted'])
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter le total de suggestions disponibles
     */
    public function countSuggestedUsers(int $userId): int
    {
        return (int)$this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.id != :userId')
            ->setParameter('userId', $userId)
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fr.sender) FROM App\Entity\Friend fr WHERE fr.receiver = :userId AND fr.status IN (:statuses)
            )')
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(fs.receiver) FROM App\Entity\Friend fs WHERE fs.sender = :userId AND fs.status IN (:statuses)
            )')
            ->setParameter('statuses', ['pending', 'accepted'])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Chercher les utilisateurs par nom/username
     */
    public function searchUsers(string $query, int $currentUserId, int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.id != :currentUserId')
            ->andWhere('(u.fullName LIKE :query OR u.username LIKE :query)')
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('u.fullName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les amis communs entre deux utilisateurs
     */
    public function getMutualFriends(int $userId1, int $userId2): array
    {
        // Amis de l'utilisateur 1
        $friends1Subquery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(f1.receiver)')
            ->from('App\Entity\Friend', 'f1')
            ->where('f1.sender = :userId1 AND f1.status = :accepted')
            ->andWhere('f1.receiver != :userId2')
            ->getDQL();

        $friends1ReverseSubquery = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(f1r.sender)')
            ->from('App\Entity\Friend', 'f1r')
            ->where('f1r.receiver = :userId1 AND f1r.status = :accepted')
            ->andWhere('f1r.sender != :userId2')
            ->getDQL();

        // Amis communs
        return $this->createQueryBuilder('u')
            ->where("u.id IN ($friends1Subquery) OR u.id IN ($friends1ReverseSubquery)")
            ->andWhere('u.id IN (
                SELECT IDENTITY(f2.receiver) FROM App\Entity\Friend f2 
                WHERE f2.sender = :userId2 AND f2.status = :accepted
            ) OR u.id IN (
                SELECT IDENTITY(f2r.sender) FROM App\Entity\Friend f2r 
                WHERE f2r.receiver = :userId2 AND f2r.status = :accepted
            )')
            ->setParameter('userId1', $userId1)
            ->setParameter('userId2', $userId2)
            ->setParameter('accepted', 'accepted')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les utilisateurs les plus actifs (basé sur les posts récents)
     */
    public function getMostActiveUsers(int $limit = 5): array
    {
        $qb = $this->createQueryBuilder('u')
            ->select('u, COUNT(p.id) as postCount')
            ->leftJoin('u.posts', 'p', 'WITH', 'p.createdAt > :lastWeek')
            ->where('u.lastActivity > :lastDay')
            ->groupBy('u.id')
            ->orderBy('postCount', 'DESC')
            ->addOrderBy('u.lastActivity', 'DESC')
            ->setParameter('lastWeek', new \DateTimeImmutable('-1 week'))
            ->setParameter('lastDay', new \DateTimeImmutable('-1 day'))
            ->setMaxResults($limit);

        return array_map(function($result) {
            return $result[0]; // Retourner seulement l'utilisateur, pas le count
        }, $qb->getQuery()->getResult());
    }

    /**
     * Enrichir les utilisateurs avec les amis en commun
     */
    public function enrichUsersWithMutualFriends(User $currentUser, array $users): array
    {
        $enrichedUsers = [];

        foreach ($users as $user) {
            $mutualFriends = $this->getMutualFriends($currentUser->getId(), $user->getId());

            $enrichedUsers[] = [
                'user' => $user,
                'mutualFriends' => $mutualFriends,
                'mutualFriendsCount' => count($mutualFriends),
                'isOnline' => $this->isUserOnline($user)
            ];
        }

        return $enrichedUsers;
    }

    /**
     * Formater les utilisateurs pour l'API
     */
    public function formatUsersForApi(User $currentUser, array $users): array
    {
        $usersData = [];

        foreach ($users as $user) {
            $mutualFriends = $this->getMutualFriends($currentUser->getId(), $user->getId());

            $usersData[] = [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'fullName' => $user->getFullName(),
                'avatarPath' => $user->getAvatarPath(),
                'mutualFriendsCount' => count($mutualFriends),
                'isOnline' => $this->isUserOnline($user)
            ];
        }

        return $usersData;
    }

    /**
     * Formater les utilisateurs pour la recherche
     */
    public function formatUsersForSearch(User $currentUser, array $users): array
    {
        return array_map(function($user) use ($currentUser) {
            return [
                'id' => $user->getId(),
                'fullName' => $user->getFullName(),
                'username' => $user->getUsername(),
                'avatarPath' => $user->getAvatarPath(),
                'mutualFriendsCount' => count($this->getMutualFriends($currentUser->getId(), $user->getId())),
                'isFriend' => $currentUser->isFriendsWith($user),
                'isOnline' => $this->isUserOnline($user)
            ];
        }, $users);
    }

    /**
     * Vérifier si l'utilisateur est en ligne
     */
    private function isUserOnline(User $user): bool
    {
        // Vérifier si l'utilisateur était actif dans les 5 dernières minutes
        return $user->getLastActivity() &&
            $user->getLastActivity() > new \DateTimeImmutable('-5 minutes');
    }
}