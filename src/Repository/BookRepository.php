<?php

namespace App\Repository;

use App\Entity\Book;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Book>
 *
 * @method Book|null find($id, $lockMode = null, $lockVersion = null)
 * @method Book|null findOneBy(array $criteria, array $orderBy = null)
 * @method Book[]    findAll()
 * @method Book[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    /**
     * Trouve les livres récents (utile pour les nouvelles sorties)
     */
    public function findRecentBooks(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.available = :available')
            ->andWhere('b.publicationDate IS NOT NULL')
            ->andWhere('b.publicationDate >= :dateLimit')
            ->setParameter('available', true)
            ->setParameter('dateLimit', new \DateTime('-3 months'))
            ->orderBy('b.publicationDate', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les livres par catégorie avec filtres
     */
    public function findByCategory(int $categoryId, array $filters = []): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.category = :categoryId')
            ->andWhere('b.available = :available')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('available', true);

        if (isset($filters['minRating'])) {
            $qb->andWhere('b.rating >= :minRating')
                ->setParameter('minRating', $filters['minRating']);
        }

        if (isset($filters['maxPrice'])) {
            $qb->andWhere('b.price <= :maxPrice')
                ->setParameter('maxPrice', $filters['maxPrice']);
        }

        return $qb->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de livres par titre ou auteur
     */
    public function searchBooks(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.available = :available')
            ->andWhere('b.title LIKE :searchTerm OR b.author LIKE :searchTerm')
            ->setParameter('available', true)
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->orderBy('b.title', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les livres les mieux notés
     */
    public function findTopRatedBooks(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.available = :available')
            ->andWhere('b.rating IS NOT NULL')
            ->setParameter('available', true)
            ->orderBy('b.rating', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les livres par section
     */
    public function countBySection(string $section): int
    {
        $field = match ($section) {
            'semaine' => 'isWeeklySelection',
            'newsletter-history' => 'isNewsletter',
            'suggestions' => 'isSuggestion',
            'sorties' => 'isNewRelease',
            default => null
        };

        if (!$field) {
            return 0;
        }

        return $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere("b.{$field} = :value")
            ->andWhere('b.available = :available')
            ->setParameter('value', true)
            ->setParameter('available', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les livres similaires (même catégorie, excluant le livre actuel)
     */
    public function findSimilarBooks(Book $book, int $limit = 5): array
    {
        return $this->createQueryBuilder('b')
            ->andWhere('b.category = :category')
            ->andWhere('b.id != :bookId')
            ->andWhere('b.available = :available')
            ->setParameter('category', $book->getCategory())
            ->setParameter('bookId', $book->getId())
            ->setParameter('available', true)
            ->orderBy('b.rating', 'DESC')
            ->addOrderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}