<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Friend;
use App\Entity\Post;
use App\Entity\User;
use App\Form\ContactType;
use App\Form\PostFormType;
use App\Service\DeviceDetectorService;
use App\Service\EmailService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private readonly DeviceDetectorService $deviceDetector,
        private readonly EntityManagerInterface $em,
        private readonly FileUploader $uploader,
        private readonly EmailService $emailService
    )
    {

    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
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
                    $newFilename = $this->uploader->upload($imageFile);
                    $post->setImagePath($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors du téléchargement de l\'image.');
                }
            }
            $this->em->persist($post);
            $this->em->flush();

            $this->addFlash('success', 'Votre publication a été ajoutée.');
            return $this->redirectToRoute('app_home');
        }

        // Récupération des posts triés par date de création décroissante
        $posts = $this->em->getRepository(Post::class)->findBy([], ['created_at' => 'DESC']);

        // Utilisation d'une méthode dédiée dans le repository pour les suggestions d'amis
        $suggestedUsers = $this->em->getRepository(User::class)->getSugeredUsers($user->getId());

        // Récupérer les demandes d'amitié en attente
        $friendRequests = $this->em->getRepository(Friend::class)->findBy([
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
    public function menu(Request $request): Response
    {
        return $this->json(['error' => 'Not implemented yet.']);
    }

    #[Route('/contact', name: 'app_contact', methods: ['GET', 'POST'])]
    public function contact(Request $request): Response
    {
        $contact = new Contact();
        $form = $this->createForm(ContactType::class, $contact);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($contact);
            $this->em->flush();

            $emailSent = $this->emailService->sendContactEmail($contact);
            $confirmationSent = $this->emailService->sendContactConfirmation($contact);

            if ($emailSent) {
                $this->addFlash('success', 'Votre message a bien été envoyé. Nous vous répondrons dans les plus brefs délais.');

                if ($confirmationSent) {
                    $this->addFlash('info', 'Un email de confirmation vous a été envoyé.');
                }
            } else {
                $this->addFlash('success', 'Votre message a bien été enregistré. Nous vous répondrons dans les plus brefs délais.');
                $this->addFlash('warning', 'Une erreur s\'est produite lors de l\'envoi de l\'email, mais votre message a été sauvegardé.');
            }

            return $this->redirectToRoute('app_contact');
        }

        return $this->render('contact/contact.html.twig', [
            'form' => $form,
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

    #[Route('/{page}', name: 'app_static_page', requirements: [
        'page' => 'politique-confidentialite|conditions-utilisation|a-propos'
    ])]
    public function staticPage(string $page): Response
    {
        $template = match ($page) {
            'politique-confidentialite' => 'home/desktop/legales/privacy_policy.html.twig',
            'conditions-utilisation' => 'home/desktop/legales/terms_of_service.html.twig',
            'a-propos' => 'home/desktop/legales/about.html.twig',
            default => throw $this->createNotFoundException('Page inconnue.'),
        };

        return $this->render($template);
    }
}
