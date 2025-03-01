<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\User;
use App\Form\PostFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {

        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

            $user = $this->getUser();
            $post->setUser($user);
            $post->setCreatedAt(new \DateTimeImmutable('now'));

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $newFilename = uniqid().'.'.$imageFile->guessExtension();

                $imageFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
                $post->setImagePath($newFilename);
            }
            $em->persist($post);
            $em->flush();

            return $this->redirectToRoute('app_home');
        }
        //$posts = $em->getRepository(Post::class)->findBy(['user' => $this->getUser()], ['created_at' => 'DESC']);
        $posts = $em->getRepository(Post::class)->findAll();
        $suggestedUsers = $em->getRepository(User::class)->findAll();
        $friendRequests = $this->getUser()->getReceivedFriendRequests();
        return $this->render('home/index.html.twig', [
            'postForm' => $form->createView(),
            'posts' => $posts,
            'suggestedUsers' => $suggestedUsers,
            'friendRequests' => $friendRequests
        ]);
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
}
