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

    public function getSugeredUsers($userId): array
    {
        $qb = $this->createQueryBuilder('u')
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

        return $qb;
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

    public function searchByNameOrUsername(string $query): array
    {
        return $this->createQueryBuilder('u')
            ->where('LOWER(u.username) LIKE :query')
            ->orWhere('LOWER(u.fullName) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->orderBy('u.username', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
