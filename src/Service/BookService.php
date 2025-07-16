<?php

namespace App\Service;

use App\Repository\BookRepository;

class BookService
{
    public function __construct(
        private readonly BookRepository $bookRepository
    )
    {
    }

    /**
     * Récupère les livres pour la sidebar selon la section
     */
    public function getBooksForSidebar(string $section, int $limit = 2): array
    {
        return match ($section) {
            'semaine' => $this->bookRepository->findBy(
                ['isWeeklySelection' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC'],
                $limit
            ),
            'newsletter-history' => $this->bookRepository->findBy(
                ['isNewsletter' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC'],
                $limit
            ),
            'suggestions' => $this->bookRepository->findBy(
                ['isSuggestion' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC'],
                $limit
            ),
            'sorties' => $this->bookRepository->findBy(
                ['isNewRelease' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'publicationDate' => 'DESC'],
                $limit
            ),
            default => []
        };
    }

    /**
     * Récupère tous les livres d'une section pour la page dédiée
     */
    public function getBooksForSection(string $section): array
    {
        return match ($section) {
            'semaine' => $this->bookRepository->findBy(
                ['isWeeklySelection' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC']
            ),
            'newsletter-history' => $this->bookRepository->findBy(
                ['isNewsletter' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC']
            ),
            'suggestions' => $this->bookRepository->findBy(
                ['isSuggestion' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'createdAt' => 'DESC']
            ),
            'sorties' => $this->bookRepository->findBy(
                ['isNewRelease' => true, 'available' => true],
                ['sortOrder' => 'ASC', 'publicationDate' => 'DESC']
            ),
            default => []
        };
    }

    /**
     * Configuration des sections
     */
    public function getSectionsConfig(): array
    {
        return [
            'semaine' => [
                'title' => 'Livres de la semaine',
                'subtitle' => 'Découvrez notre sélection hebdomadaire',
                'icon' => 'calendar',
                'description' => 'Une sélection soigneusement choisie de livres pour enrichir votre semaine de lecture.',
                'color' => 'bg-blue-500',
                'gradient' => 'from-[#FF8A00] to-[#e67300]',
                'text_color' => 'text-[#FF8A00]',
                'hover_color' => 'hover:text-[#e67300]'
            ],
            'newsletter-history' => [
                'title' => 'Historique Newsletter',
                'subtitle' => 'Retrouvez toutes nos anciennes recommandations',
                'icon' => 'mail',
                'description' => 'Explorez l\'archive complète de nos recommandations littéraires envoyées par newsletter.',
                'color' => 'bg-purple-700',
                'gradient' => 'from-emerald-500 to-emerald-600',
                'text_color' => 'text-emerald-600',
                'hover_color' => 'hover:text-emerald-700'
            ],
            'suggestions' => [
                'title' => 'Suggestions personnalisées',
                'subtitle' => 'Des livres choisis spécialement pour vous',
                'icon' => 'lightbulb',
                'description' => 'Basées sur vos préférences et votre historique de lecture, voici nos suggestions.',
                'color' => 'bg-green-600',
                'gradient' => 'from-purple-500 to-purple-600',
                'text_color' => 'text-purple-600',
                'hover_color' => 'hover:text-purple-700'
            ],
            'sorties' => [
                'title' => 'Dernières sorties',
                'subtitle' => 'Les nouveautés littéraires du moment',
                'icon' => 'sparkles',
                'description' => 'Découvrez les dernières parutions qui font l\'actualité littéraire.',
                'color' => 'bg-orange-500',
                'gradient' => 'from-blue-500 to-blue-600',
                'text_color' => 'text-blue-600',
                'hover_color' => 'hover:text-blue-700'
            ]
        ];
    }

    /**
     * Récupère la configuration d'une section spécifique
     */
    public function getSectionConfig(string $section): ?array
    {
        $config = $this->getSectionsConfig();
        return $config[$section] ?? null;
    }

    /**
     * Récupère tous les livres d'une section avec leur configuration
     */
    public function getSectionData(string $section): array
    {
        return [
            'section' => $section,
            'config' => $this->getSectionConfig($section),
            'books' => $this->getBooksForSection($section),
            'allSections' => $this->getSectionsConfig()
        ];
    }

    /**
     * Récupère les données pour la sidebar
     */
    public function getSidebarData(): array
    {
        $sections = ['semaine', 'newsletter-history', 'suggestions', 'sorties'];
        $sidebarData = [];

        foreach ($sections as $section) {
            $sidebarData[$section] = [
                'config' => $this->getSectionConfig($section),
                'books' => $this->getBooksForSidebar($section)
            ];
        }

        return $sidebarData;
    }
}