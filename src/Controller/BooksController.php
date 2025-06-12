<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class BooksController extends AbstractController
{
    #[Route('/livres/{section}', name: 'app_books_section', requirements: ['section' => 'semaine|newsletter-history|suggestions|sorties'])]
    public function booksSection(string $section): Response
    {
        // Configuration des sections
        $sectionsConfig = [
            'semaine' => [
                'title' => 'Livres de la semaine',
                'subtitle' => 'Découvrez notre sélection hebdomadaire',
                'icon' => 'calendar',
                'description' => 'Une sélection soigneusement choisie de livres pour enrichir votre semaine de lecture.'
            ],
            'newsletter-history' => [
                'title' => 'Historique Newsletter',
                'subtitle' => 'Retrouvez toutes nos anciennes recommandations',
                'icon' => 'mail',
                'description' => 'Explorez l\'archive complète de nos recommandations littéraires envoyées par newsletter.'
            ],
            'suggestions' => [
                'title' => 'Suggestions personnalisées',
                'subtitle' => 'Des livres choisis spécialement pour vous',
                'icon' => 'lightbulb',
                'description' => 'Basées sur vos préférences et votre historique de lecture, voici nos suggestions.'
            ],
            'sorties' => [
                'title' => 'Dernières sorties',
                'subtitle' => 'Les nouveautés littéraires du moment',
                'icon' => 'sparkles',
                'description' => 'Découvrez les dernières parutions qui font l\'actualité littéraire.'
            ]
        ];

        // Données factices (à remplacer par vos vraies données)
        $books = $this->generateMockBooks($section);

        return $this->render('home/desktop/books/section.html.twig', [
            'section' => $section,
            'config' => $sectionsConfig[$section],
            'books' => $books
        ]);
    }

    #[Route('/livre/{id}', name: 'app_book_detail')]
    public function bookDetail(int $id): Response
    {
        // Données factices pour le détail (à remplacer par vos vraies données)
        $book = $this->generateMockBookDetail($id);

        if (!$book) {
            throw $this->createNotFoundException('Livre non trouvé.');
        }

        return $this->render('home/desktop/books/detail.html.twig', [
            'book' => $book
        ]);
    }

    private function generateMockBooks(string $section): array
    {
        $baseBooks = [
            [
                'id' => 1,
                'title' => 'L\'Étranger',
                'author' => 'Albert Camus',
                'description' => 'Un chef-d\'œuvre de la littérature française qui explore l\'absurdité de l\'existence.',
                'cover' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=300&h=400&fit=crop',
                'rating' => 4.5,
                'price' => 8.90,
                'category' => 'Classique',
                'publication_date' => '2024-01-15'
            ],
            [
                'id' => 2,
                'title' => 'Dune',
                'author' => 'Frank Herbert',
                'description' => 'Une épopée de science-fiction dans un univers désertique fascinant.',
                'cover' => 'https://images.unsplash.com/photo-1481627834876-b7833e8f5570?w=300&h=400&fit=crop',
                'rating' => 4.8,
                'price' => 12.50,
                'category' => 'Science-Fiction',
                'publication_date' => '2024-02-20'
            ],
            [
                'id' => 3,
                'title' => 'Le Petit Prince',
                'author' => 'Antoine de Saint-Exupéry',
                'description' => 'Un conte poétique et philosophique intemporel.',
                'cover' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=300&h=400&fit=crop',
                'rating' => 4.7,
                'price' => 6.90,
                'category' => 'Conte',
                'publication_date' => '2024-01-10'
            ],
            [
                'id' => 4,
                'title' => 'Sapiens',
                'author' => 'Yuval Noah Harari',
                'description' => 'Une brève histoire de l\'humanité qui bouleverse notre vision du monde.',
                'cover' => 'https://images.unsplash.com/photo-1519682337058-a94d519337bc?w=300&h=400&fit=crop',
                'rating' => 4.6,
                'price' => 15.90,
                'category' => 'Essai',
                'publication_date' => '2024-03-05'
            ]
        ];

        return array_slice($baseBooks, 0, rand(2, 4));
    }

    private function generateMockBookDetail(int $id): ?array
    {
        $books = [
            1 => [
                'id' => 1,
                'title' => 'L\'Étranger',
                'author' => 'Albert Camus',
                'description' => 'Un chef-d\'œuvre de la littérature française qui explore l\'absurdité de l\'existence humaine à travers l\'histoire de Meursault, un homme indifférent qui tue un Arabe sur une plage d\'Alger.',
                'long_description' => 'Publié en 1942, "L\'Étranger" est le premier roman d\'Albert Camus et l\'une des œuvres les plus importantes de la littérature du XXe siècle. À travers le personnage de Meursault, Camus explore les thèmes de l\'absurde, de l\'aliénation et de la condition humaine moderne.',
                'cover' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?w=400&h=600&fit=crop',
                'rating' => 4.5,
                'price' => 8.90,
                'category' => 'Classique',
                'publication_date' => '1942-05-01',
                'pages' => 159,
                'isbn' => '978-2070360024',
                'publisher' => 'Gallimard',
                'language' => 'Français',
                'available' => true
            ]
        ];

        return $books[$id] ?? null;
    }
}