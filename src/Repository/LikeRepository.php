<?php

namespace App\Repository;

use App\Entity\Like;
use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Like>
 */
class LikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    /**
     * Alterner entre like/unlike d'un post
     */
    public function toggleLike(User $user, Post $post): array
    {
        $existingLike = $this->findOneBy([
            'user' => $user,
            'post' => $post
        ]);

        if ($existingLike) {
            // Unlike - supprimer le like
            $this->getEntityManager()->remove($existingLike);
            $isLiked = false;
        } else {
            // Like - créer nouveau like
            $like = new Like();
            $like->setUser($user);
            $like->setPost($post);
            $like->setCreatedAt(new \DateTimeImmutable());
            $this->getEntityManager()->persist($like);
            $isLiked = true;
        }

        $this->getEntityManager()->flush();

        // Recompter les likes
        $likesCount = $this->count(['post' => $post]);

        return [
            'isLiked' => $isLiked,
            'likesCount' => $likesCount
        ];
    }

    /**
     * Vérifier si un utilisateur a aimé un post
     */
    public function hasUserLikedPost(User $user, Post $post): bool
    {
        $like = $this->findOneBy([
            'user' => $user,
            'post' => $post
        ]);

        return $like !== null;
    }

    /**
     * Compter les likes d'un post
     */
    public function countPostLikes(Post $post): int
    {
        return $this->count(['post' => $post]);
    }

    /**
     * Récupérer les utilisateurs qui ont aimé un post
     */
    public function getUsersWhoLikedPost(Post $post, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.user')
            ->where('l.post = :post')
            ->setParameter('post', $post)
            ->orderBy('l.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupérer les posts aimés par un utilisateur
     */
    public function getPostsLikedByUser(User $user, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('l')
            ->select('l.post')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupérer les likes récents d'un utilisateur
     */
    public function getRecentLikes(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les likes totaux d'un utilisateur
     */
    public function countUserLikes(User $user): int
    {
        return $this->count(['user' => $user]);
    }

    /**
     * Récupérer les likes d'un post avec détails des utilisateurs
     */
    public function getPostLikesWithUsers(Post $post): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.user', 'u')
            ->where('l.post = :post')
            ->setParameter('post', $post)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les posts les plus aimés dans une période
     */
    public function getMostLikedPostsInPeriod(\DateTimeImmutable $since, int $limit = 10): array
    {
        return $this->createQueryBuilder('l')
            ->select('IDENTITY(l.post) as postId, COUNT(l.id) as likesCount')
            ->where('l.createdAt > :since')
            ->setParameter('since', $since)
            ->groupBy('l.post')
            ->orderBy('likesCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprimer tous les likes d'un post
     */
    public function removeAllPostLikes(Post $post): void
    {
        $this->createQueryBuilder('l')
            ->delete()
            ->where('l.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprimer tous les likes d'un utilisateur
     */
    public function removeAllUserLikes(User $user): void
    {
        $this->createQueryBuilder('l')
            ->delete()
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Récupérer les statistiques de likes pour un utilisateur
     */
    public function getUserLikeStats(User $user): array
    {
        // Likes donnés
        $likesGiven = $this->count(['user' => $user]);

        // Likes reçus sur ses posts
        $likesReceived = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->join('l.post', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        // Post le plus aimé
        $mostLikedPost = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.post) as postId, COUNT(l.id) as likesCount')
            ->join('l.post', 'p')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->groupBy('l.post')
            ->orderBy('likesCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'likesGiven' => $likesGiven,
            'likesReceived' => $likesReceived,
            'mostLikedPostId' => $mostLikedPost ? $mostLikedPost['postId'] : null,
            'mostLikedPostLikes' => $mostLikedPost ? $mostLikedPost['likesCount'] : 0
        ];
    }

    /**
     * Vérifier les likes multiples pour optimisation
     */
    public function checkMultipleLikes(User $user, array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        $likes = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.post) as postId')
            ->where('l.user = :user')
            ->andWhere('l.post IN (:postIds)')
            ->setParameter('user', $user)
            ->setParameter('postIds', $postIds)
            ->getQuery()
            ->getArrayResult();

        return array_column($likes, 'postId');
    }
}