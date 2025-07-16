<?php

namespace App\Twig;

use App\Service\BookService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class BookExtension extends AbstractExtension
{
    public function __construct(
        private readonly BookService $bookService
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_sidebar_books', [$this, 'getSidebarBooks']),
            new TwigFunction('get_books_for_section', [$this, 'getBooksForSection']),
            new TwigFunction('get_section_config', [$this, 'getSectionConfig']),
        ];
    }

    public function getSidebarBooks(): array
    {
        return $this->bookService->getSidebarData();
    }

    public function getBooksForSection(string $section, int $limit = null): array
    {
        if ($limit) {
            return $this->bookService->getBooksForSidebar($section, $limit);
        }

        return $this->bookService->getBooksForSection($section);
    }

    public function getSectionConfig(string $section): ?array
    {
        return $this->bookService->getSectionConfig($section);
    }
}