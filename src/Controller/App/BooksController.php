<?php

namespace App\Controller\App;

use App\Entity\Book;
use App\Repository\BookRepository;
use App\Service\BookService;
use App\Service\DeviceDetectorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class BooksController extends AbstractController
{
    public function __construct(
        private readonly DeviceDetectorService $deviceDetector,
        private readonly BookService           $bookService,
        private readonly BookRepository        $bookRepository
    )
    {
    }

    #[Route('/livres/{section}', name: 'app_books_section', requirements: ['section' => 'semaine|newsletter-history|suggestions|sorties'])]
    public function booksSection(string $section): Response
    {
        $templateData = $this->bookService->getSectionData($section);

        if (!$templateData['config']) {
            throw $this->createNotFoundException('Section non trouvée.');
        }

        if ($this->deviceDetector->isMobile()) {
            return $this->render('home/mobile/books/section.html.twig', $templateData);
        }

        return $this->render('home/desktop/books/section.html.twig', $templateData);
    }

    #[Route('/livre/{id}', name: 'app_book_detail')]
    public function bookDetail(Book $book): Response
    {
        if (!$book->isAvailable()) {
            throw $this->createNotFoundException('Livre non disponible.');
        }

        // Récupération des livres similaires
        $similarBooks = $this->bookRepository->findSimilarBooks($book);

        $templateData = [
            'book' => $book,
            'similarBooks' => $similarBooks
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('home/mobile/books/detail.html.twig', $templateData);
        }

        return $this->render('home/desktop/books/detail.html.twig', $templateData);
    }

    /**
     * API pour obtenir les sections de livres (pour le home mobile)
     */
    #[Route('/api/books/sections', name: 'api_books_sections', methods: ['GET'])]
    public function getSections(): Response
    {
        $sectionsConfig = $this->bookService->getSectionsConfig();

        // Simplification pour l'API mobile
        $apiSections = [];
        foreach ($sectionsConfig as $key => $config) {
            $apiSections[$key] = [
                'title' => $config['title'],
                'subtitle' => $config['subtitle'],
                'icon' => $config['icon'],
                'color' => $config['color']
            ];
        }

        return $this->json($apiSections);
    }

    /**
     * API pour obtenir les livres d'une section
     */
    #[Route('/api/books/section/{section}', name: 'api_books_section', methods: ['GET'])]
    public function getBooksBySection(string $section): Response
    {
        $books = $this->bookService->getBooksForSection($section);
        $config = $this->bookService->getSectionConfig($section);

        if (!$config) {
            return $this->json(['error' => 'Section non trouvée'], 404);
        }

        return $this->json([
            'section' => $section,
            'config' => $config,
            'books' => array_map(function (Book $book) {
                return [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'author' => $book->getAuthor(),
                    'description' => $book->getDescription(),
                    'coverUrl' => $book->getCoverUrl(),
                    'rating' => $book->getRating(),
                    'price' => $book->getPrice(),
                    'category' => $book->getCategory()->getName(),
                    'publicationDate' => $book->getPublicationDate()?->format('Y-m-d')
                ];
            }, $books),
            'count' => count($books)
        ]);
    }

    /**
     * API pour la recherche de livres
     */
    #[Route('/api/books/search', name: 'api_books_search', methods: ['GET'])]
    public function searchBooks(Request $request): Response
    {
        $searchTerm = $request->query->get('q', '');
        $limit = min($request->query->getInt('limit', 20), 50);

        if (strlen($searchTerm) < 2) {
            return $this->json(['error' => 'Le terme de recherche doit contenir au moins 2 caractères'], 400);
        }

        $books = $this->bookRepository->searchBooks($searchTerm, $limit);

        return $this->json([
            'query' => $searchTerm,
            'results' => array_map(function (Book $book) {
                return [
                    'id' => $book->getId(),
                    'title' => $book->getTitle(),
                    'author' => $book->getAuthor(),
                    'coverUrl' => $book->getCoverUrl(),
                    'rating' => $book->getRating(),
                    'price' => $book->getPrice(),
                    'category' => $book->getCategory()->getName()
                ];
            }, $books),
            'count' => count($books)
        ]);
    }

    /**
     * API pour obtenir les données de la sidebar
     */
    #[Route('/api/books/sidebar', name: 'api_books_sidebar', methods: ['GET'])]
    public function getSidebarData(): Response
    {
        $sidebarData = $this->bookService->getSidebarData();

        // Formatage pour l'API
        $formattedData = [];
        foreach ($sidebarData as $section => $data) {
            $formattedData[$section] = [
                'config' => $data['config'],
                'books' => array_map(function (Book $book) {
                    return [
                        'id' => $book->getId(),
                        'title' => $book->getTitle(),
                        'author' => $book->getAuthor(),
                        'coverUrl' => $book->getCoverUrl(),
                        'rating' => $book->getRating()
                    ];
                }, $data['books'])
            ];
        }

        return $this->json($formattedData);
    }
}