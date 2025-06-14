<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * Chercher des posts par contenu
     */
    public function searchByContent(?string $query): array
    {
        if (empty($query) || trim($query) === '') {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->where('LOWER(p.content) LIKE :query')
            ->setParameter('query', '%' . strtolower(trim($query)) . '%')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les posts de découverte (utilisateurs non-amis)
     */
    public function getDiscoverPosts(int $currentUserId, int $limit, int $page): array
    {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->where('u.id != :currentUserId')
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(f.receiver) FROM App\Entity\Friend f 
                WHERE f.sender = :currentUserId AND f.status = :accepted
            )')
            ->andWhere('u.id NOT IN (
                SELECT IDENTITY(f2.sender) FROM App\Entity\Friend f2 
                WHERE f2.receiver = :currentUserId AND f2.status = :accepted
            )')
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('accepted', 'accepted')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupérer les posts populaires (plus de likes récents)
     */
    public function getPopularPosts(int $currentUserId, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->select('p, COUNT(l.id) as likesCount')
            ->leftJoin('p.likes', 'l', 'WITH', 'l.createdAt > :lastWeek')
            ->join('p.user', 'u')
            ->where('u.id != :currentUserId')
            ->andWhere('p.createdAt > :lastMonth')
            ->groupBy('p.id')
            ->orderBy('likesCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('lastWeek', new \DateTimeImmutable('-1 week'))
            ->setParameter('lastMonth', new \DateTimeImmutable('-1 month'))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Chercher des posts par query
     */
    public function searchPosts(string $query, int $currentUserId, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->where('LOWER(p.content) LIKE :query')
            ->andWhere('u.id != :currentUserId')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setParameter('currentUserId', $currentUserId)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtenir les livres tendance basés sur les posts
     */
    public function getTrendingBooks(int $limit = 5): array
    {
        $posts = $this->createQueryBuilder('p')
            ->where('p.content LIKE :book1 OR p.content LIKE :book2 OR p.content LIKE :livre')
            ->andWhere('p.createdAt > :lastMonth')
            ->setParameter('book1', '%#livre%')
            ->setParameter('book2', '%#book%')
            ->setParameter('livre', '%livre%')
            ->setParameter('lastMonth', new \DateTimeImmutable('-1 month'))
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        // Extraire informations de livres des posts
        $books = [];
        foreach ($posts as $post) {
            // Chercher titres entre guillemets
            if (preg_match('/"([^"]+)"/', $post->getContent(), $matches)) {
                $title = $matches[1];
                if (!isset($books[$title])) {
                    $books[$title] = [
                        'title' => $title,
                        'mentions' => 0,
                        'lastPost' => $post
                    ];
                }
                $books[$title]['mentions']++;
            }
        }

        // Trier par mentions et limiter
        uasort($books, function($a, $b) {
            return $b['mentions'] <=> $a['mentions'];
        });

        return array_slice($books, 0, $limit);
    }

    /**
     * Récupérer les hashtags populaires
     */
    public function getPopularHashtags(int $limit = 10): array
    {
        $posts = $this->createQueryBuilder('p')
            ->where('p.content LIKE :hashtag')
            ->andWhere('p.createdAt > :lastWeek')
            ->setParameter('hashtag', '%#%')
            ->setParameter('lastWeek', new \DateTimeImmutable('-1 week'))
            ->getQuery()
            ->getResult();

        $hashtags = [];
        foreach ($posts as $post) {
            // Extraire hashtags du contenu
            preg_match_all('/#([a-zA-Z0-9_àáâãäéêëíîïóôõöúûüç]+)/', $post->getContent(), $matches);
            foreach ($matches[1] as $hashtag) {
                $tag = strtolower($hashtag);
                if (!isset($hashtags[$tag])) {
                    $hashtags[$tag] = 0;
                }
                $hashtags[$tag]++;
            }
        }

        // Trier par popularité
        arsort($hashtags);

        return array_slice($hashtags, 0, $limit, true);
    }

    /**
     * Récupérer les posts récents publics
     */
    public function getRecentPublicPosts(int $currentUserId, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->where('u.id != :currentUserId')
            ->andWhere('p.createdAt > :lastDay')
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('lastDay', new \DateTimeImmutable('-1 day'))
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compter les posts par utilisateur dans une période
     */
    public function countUserPostsInPeriod(User $user, \DateTimeImmutable $since): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.user = :user')
            ->andWhere('p.createdAt > :since')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupérer les posts avec le plus de commentaires récents
     */
    public function getMostCommentedPosts(int $currentUserId, int $limit): array
    {
        return $this->createQueryBuilder('p')
            ->select('p, COUNT(c.id) as commentsCount')
            ->leftJoin('p.comments', 'c', 'WITH', 'c.createdAt > :lastWeek')
            ->join('p.user', 'u')
            ->where('u.id != :currentUserId')
            ->andWhere('p.createdAt > :lastMonth')
            ->groupBy('p.id')
            ->orderBy('commentsCount', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setParameter('currentUserId', $currentUserId)
            ->setParameter('lastWeek', new \DateTimeImmutable('-1 week'))
            ->setParameter('lastMonth', new \DateTimeImmutable('-1 month'))
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Formater les posts pour la recherche
     */
    public function formatPostsForSearch(array $posts): array
    {
        return array_map(function($post) {
            return [
                'id' => $post->getId(),
                'content' => substr($post->getContent(), 0, 150) . (strlen($post->getContent()) > 150 ? '...' : ''),
                'createdAt' => $post->getCreatedAt()->format('Y-m-d H:i:s'),
                'user' => [
                    'id' => $post->getUser()->getId(),
                    'fullName' => $post->getUser()->getFullName(),
                    'username' => $post->getUser()->getUsername(),
                    'avatarPath' => $post->getUser()->getAvatarPath()
                ],
                'likesCount' => $post->getLikes()->count(),
                'commentsCount' => $post->getComments()->count()
            ];
        }, $posts);
    }

    /**
     * Récupérer les posts recommandés basés sur l'activité de l'utilisateur
     */
    public function getRecommendedPosts(User $user, int $limit): array
    {
        // Posts similaires aux posts que l'utilisateur a aimés
        $likedPosts = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(l.post)')
            ->from('App\Entity\Like', 'l')
            ->where('l.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($likedPosts)) {
            return $this->getRecentPublicPosts($user->getId(), $limit);
        }

        // Récupérer hashtags des posts aimés
        $hashtags = [];
        $likedPostsEntities = $this->findBy(['id' => $likedPosts]);

        foreach ($likedPostsEntities as $post) {
            preg_match_all('/#([a-zA-Z0-9_àáâãäéêëíîïóôõöúûüç]+)/', $post->getContent(), $matches);
            $hashtags = array_merge($hashtags, $matches[1]);
        }

        if (empty($hashtags)) {
            return $this->getPopularPosts($user->getId(), $limit);
        }

        // Chercher posts avec hashtags similaires
        $qb = $this->createQueryBuilder('p')
            ->join('p.user', 'u')
            ->where('u.id != :userId')
            ->andWhere('p.id NOT IN (:likedPosts)')
            ->setParameter('userId', $user->getId())
            ->setParameter('likedPosts', $likedPosts);

        // Ajouter conditions pour hashtags
        $hashtagConditions = [];
        foreach (array_unique($hashtags) as $index => $hashtag) {
            $hashtagConditions[] = "p.content LIKE :hashtag{$index}";
            $qb->setParameter("hashtag{$index}", "%#{$hashtag}%");
        }

        if (!empty($hashtagConditions)) {
            $qb->andWhere('(' . implode(' OR ', $hashtagConditions) . ')');
        }

        return $qb->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}