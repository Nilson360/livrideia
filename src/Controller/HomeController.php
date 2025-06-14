<?php

namespace App\Controller;

use App\Entity\Contact;
use App\Entity\Post;
use App\Form\ContactType;
use App\Form\PostFormType;
use App\Repository\FriendRepository;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Service\DeviceDetectorService;
use App\Service\EmailService;
use App\Service\FileUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class HomeController extends AbstractController
{
    public function __construct(
        private readonly DeviceDetectorService  $deviceDetector,
        private readonly EntityManagerInterface $em,
        private readonly FileUploader           $uploader,
        private readonly EmailService           $emailService,
        private readonly UserRepository         $userRepository,
        private readonly PostRepository         $postRepository,
        private readonly FriendRepository       $friendRepository,
    )
    {

    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {

        $user = $this->getUser();

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
                    $newFilename = $this->uploader->upload($imageFile, 'posts');
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
        $posts = $this->postRepository->findBy([], ['createdAt' => 'DESC']);

        // Utilisation d'une méthode dédiée dans le repository pour les suggestions d'amis
        $suggestedUsers = $this->userRepository->getSugeredUsers($user->getId());

        // Récupérer les demandes d'amitié en attente
        $friendRequests = $this->friendRepository->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ]);

        $templateData = [
            'postForm' => $form->createView(),
            'posts' => $posts,
            'suggestedUsers' => $suggestedUsers,
            'friendRequests' => $friendRequests,
            'form' => $form->createView()
        ];

        if ($this->deviceDetector->isMobile()) {
            return $this->render('home/mobile/index.html.twig', $templateData);
        }

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
    public function search(Request $request): Response
    {
        $query = $request->query->get('q');
        $users = [];
        $posts = [];

        $hasQuery = !empty($query);
        $isQueryTooShort = $hasQuery && strlen(trim($query)) < 2;
        $isValidQuery = $hasQuery && !$isQueryTooShort;

        if ($isValidQuery) {
            $users = $this->userRepository->searchByNameOrUsername($query);
            $posts = $this->postRepository->searchByContent($query);
        }

        $templateData = [
            'query' => $query,
            'users' => $users,
            'posts' => $posts,
            'hasQuery' => $hasQuery,
            'isQueryTooShort' => $isQueryTooShort,
            'isValidQuery' => $isValidQuery,
            'totalResults' => count($users) + count($posts)
        ];

        if ($this->deviceDetector->isMobile()) {
            return $this->render('search/result_mobile.html.twig', $templateData);
        }

        return $this->render('search/results.html.twig', $templateData);
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
