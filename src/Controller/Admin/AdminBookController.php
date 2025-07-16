<?php

namespace App\Controller\Admin;

use App\Entity\Book;
use App\Form\BookType;
use App\Repository\BookRepository;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/books')]
#[IsGranted('ROLE_USER')]
class AdminBookController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader           $fileUploader
    )
    {
    }

    #[Route('/', name: 'admin_book_index', methods: ['GET'])]
    public function index(BookRepository $bookRepository): Response
    {
        return $this->render('admin/book/index.html.twig', [
            'books' => $bookRepository->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'admin_book_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $book = new Book();
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload de fichier
            /** @var UploadedFile $coverFile */
            $coverFile = $form->get('coverFile')->getData();

            if ($coverFile) {
                try {
                    $coverFilename = $this->fileUploader->upload($coverFile, 'books');
                    $book->setCoverFilename($coverFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image: ' . $e->getMessage());
                    return $this->render('admin/book/new.html.twig', [
                        'book' => $book,
                        'form' => $form,
                    ]);
                }
            }

            $this->entityManager->persist($book);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le livre a été créé avec succès.');

            return $this->redirectToRoute('admin_book_index');
        }

        return $this->render('admin/book/new.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_book_show', methods: ['GET'])]
    public function show(Book $book): Response
    {
        return $this->render('admin/book/show.html.twig', [
            'book' => $book,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_book_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Book $book): Response
    {
        $form = $this->createForm(BookType::class, $book);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Gestion de l'upload de fichier
            /** @var UploadedFile $coverFile */
            $coverFile = $form->get('coverFile')->getData();

            if ($coverFile) {
                try {
                    // Supprimer l'ancienne image si elle existe
                    if ($book->getCoverFilename()) {
                        $oldImagePath = $this->getParameter('app.upload_directory') . '/books/' . $book->getCoverFilename();
                        if (file_exists($oldImagePath) && $book->getCoverFilename() !== 'default.png') {
                            unlink($oldImagePath);
                        }
                    }

                    $coverFilename = $this->fileUploader->upload($coverFile, 'books');
                    $book->setCoverFilename($coverFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image: ' . $e->getMessage());
                    return $this->render('admin/book/edit.html.twig', [
                        'book' => $book,
                        'form' => $form,
                    ]);
                }
            }

            $this->entityManager->flush();

            $this->addFlash('success', 'Le livre a été modifié avec succès.');

            return $this->redirectToRoute('admin_book_index');
        }

        return $this->render('admin/book/edit.html.twig', [
            'book' => $book,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'admin_book_delete', methods: ['POST'])]
    public function delete(Request $request, Book $book): Response
    {
        if ($this->isCsrfTokenValid('delete' . $book->getId(), $request->request->get('_token'))) {
            // Supprimer l'image associée
            if ($book->getCoverFilename() && $book->getCoverFilename() !== 'default.png') {
                $imagePath = $this->getParameter('app.upload_directory') . '/books/' . $book->getCoverFilename();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
            }

            $this->entityManager->remove($book);
            $this->entityManager->flush();

            $this->addFlash('success', 'Le livre a été supprimé avec succès.');
        }

        return $this->redirectToRoute('admin_book_index');
    }

    #[Route('/{id}/toggle-section/{section}', name: 'admin_book_toggle_section', methods: ['POST'])]
    public function toggleSection(Book $book, string $section): Response
    {
        switch ($section) {
            case 'weekly':
                $book->setIsWeeklySelection(!$book->isWeeklySelection());
                break;
            case 'newsletter':
                $book->setIsNewsletter(!$book->isNewsletter());
                break;
            case 'suggestion':
                $book->setIsSuggestion(!$book->isSuggestion());
                break;
            case 'release':
                $book->setIsNewRelease(!$book->isNewRelease());
                break;
            default:
                $this->addFlash('error', 'Section invalide.');
                return $this->redirectToRoute('admin_book_index');
        }

        $this->entityManager->flush();
        $this->addFlash('success', 'Section mise à jour avec succès.');

        return $this->redirectToRoute('admin_book_index');
    }

    #[Route('/{id}/remove-cover', name: 'admin_book_remove_cover', methods: ['POST'])]
    public function removeCover(Request $request, Book $book): Response
    {
        if ($this->isCsrfTokenValid('remove-cover' . $book->getId(), $request->request->get('_token'))) {
            if ($book->getCoverFilename() && $book->getCoverFilename() !== 'default.png') {
                $imagePath = $this->getParameter('app.upload_directory') . '/books/' . $book->getCoverFilename();
                if (file_exists($imagePath)) {
                    unlink($imagePath);
                }
                $book->setCoverFilename(null);
                $this->entityManager->flush();

                $this->addFlash('success', 'L\'image de couverture a été supprimée.');
            }
        }

        return $this->redirectToRoute('admin_book_edit', ['id' => $book->getId()]);
    }
}