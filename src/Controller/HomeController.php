<?php

namespace App\Controller;

use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Form\PostFormType;
use App\Service\DeviceDetectorService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly DeviceDetectorService $deviceDetector
    )
    {

    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request, EntityManagerInterface $em, FileUploader $uploader): Response
    {
        // Vérification de l'authentification
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Veuillez vous connecter pour accéder à cette page.');
            return $this->redirectToRoute('app_login');
        }

        // Création du post
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $post->setUser($user);
            $post->setCreatedAt(new \DateTimeImmutable('now'));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $newFilename = $uploader->upload($imageFile);
                    $post->setImagePath($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                }
            }
            $em->persist($post);
            $em->flush();

            $this->addFlash('success', 'Votre publication a été ajoutée.');
            return $this->redirectToRoute('app_home');
        }

        // Récupération des posts triés par date de création décroissante
        $posts = $em->getRepository(Post::class)->findBy([], ['created_at' => 'DESC']);

        // Utilisation d'une méthode dédiée dans le repository pour les suggestions d'amis
        $suggestedUsers = $em->getRepository(User::class)->getSugeredUsers($user->getId());

        // Récupérer les demandes d'amitié en attente
        $friendRequests = $em->getRepository(Friend::class)->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ]);

        // Données communes aux deux types de templates
        $templateData = [
            'postForm' => $form->createView(),
            'posts' => $posts,
            'suggestedUsers' => $suggestedUsers,
            'friendRequests' => $friendRequests,
        ];

        // Détection de l'appareil et chargement du template correspondant
        if ($this->deviceDetector->isMobile()) {
            // Vous pouvez ici ajouter des données spécifiques à la version mobile si nécessaire
            return $this->render('home/mobile/index.html.twig', $templateData);
        }

        // Version desktop (par défaut)
        return $this->render('home/desktop/index.html.twig', $templateData);
    }

    #[Route('/menu', name: 'app_menu')]
    public function menu(Request $request, EntityManagerInterface $em): Response
    {
        return $this->json(['error' => 'Not implemented yet.']);
    }
    #[Route('/conditions-utilisation', name: 'app_terms_of_service')]
    public function termsOfService(): Response
    {
        return $this->render('home/terms_of_service.html.twig');
    }

    #[Route('/politique-confidentialite', name: 'app_privacy_policy')]
    public function privacyPolicy(): Response
    {
        return $this->render('home/privacy_policy.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('home/contact.html.twig');
    }

    #[Route('/a-propos', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('home/about.html.twig');
    }

    #[Route('/livres/semaine', name: 'app_books_week')]
    public function booksOfTheWeek(): Response
    {
        return $this->render('books/week.html.twig', [
            'books' => []
        ]);
    }
    #[Route('/livres/newsletter-history', name: 'app_newslatter_history')]
    public function newsletterHistory(): Response
    {
        return $this->render('books/newsletter_history.html.twig', [
            'books' => []
        ]);
    }

    #[Route('/livres/suggestions', name: 'app_books_suggestions')]
    public function suggestedBooks(): Response
    {
        return $this->render('books/suggestions.html.twig', [
            'books' => []
        ]);
    }

    #[Route('/livres/sorties', name: 'app_books_latest')]
    public function latestBooks(): Response
    {
        return $this->render('books/latest.html.twig', [
            'books' => []
        ]);
    }
    #[Route('/recherche', name: 'app_search')]
    public function search(Request $request, EntityManagerInterface $em): Response
    {
        $query = $request->query->get('q');

        $users = $em->getRepository(User::class)->searchByNameOrUsername($query);
        $posts = $em->getRepository(Post::class)->searchByContent($query);

        return $this->render('results.html.twig', [
            'query' => $query,
            'users' => $users,
            'posts' => $posts,
        ]);
    }

}
