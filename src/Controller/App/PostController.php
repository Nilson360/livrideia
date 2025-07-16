<?php

namespace App\Controller\App;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Post;
use App\Form\PostFormType;
use App\Service\DeviceDetectorService;
use App\Service\FileUploader;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly NotificationService    $notificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileUploader           $fileUploader,
        private readonly DeviceDetectorService  $deviceDetectorService,
    )
    {
    }

    #[Route('/post/create', name: 'app_post_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        $post = new Post();
        $form = $this->createForm(PostFormType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $post->setUser($this->getUser());
            $post->setCreatedAt(new \DateTimeImmutable());

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                try {
                    $newFilename = $this->fileUploader->upload($imageFile);
                    $post->setImagePath($newFilename);
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image');
                }
            }

            $this->entityManager->persist($post);
            $this->entityManager->flush();

            $this->addFlash('success', 'Votre publication a été créée avec succès !');

            return $this->redirectToRoute('app_home');
        }

        $userAgent = $request->headers->get('User-Agent', '');
        $isMobile = preg_match('/(android|iphone|ipad|mobile)/i', $userAgent);

        $template = $isMobile ? 'home/mobile/create_post.html.twig' : 'home/desktop/create_post.html.twig';

        return $this->render($template, [
            'form' => $form,
            'post' => $post
        ]);
    }

    #[Route('/post/add', name: 'app_post_add', methods: ['POST'])]
    public function addPost(Request $request): Response
    {
        return $this->redirectToRoute('app_post_create');
    }

    #[Route('/post/story', name: 'app_create_story', methods: ['POST'])]
    public function postStory(Request $request): Response
    {
        return $this->json(['error' => 'Not implemented yet.']);
    }

    #[Route('/post/{id}/like', name: 'app_post_like', methods: ['POST'])]
    public function likePost(Post $post, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $likeRepository = $this->entityManager->getRepository(Like::class);
        $existingLike = $likeRepository->findOneBy([
            'user' => $user,
            'post' => $post
        ]);

        if ($existingLike) {
            $this->entityManager->remove($existingLike);
            $this->entityManager->flush();
            $status = 'unliked';
        } else {
            $like = new Like();
            $like->setUser($user);
            $like->setPost($post);
            $like->setCreatedAt(new \DateTimeImmutable());
            $this->entityManager->persist($like);
            $this->entityManager->flush();
            $status = 'liked';
        }

        try {
            if ($status === 'liked' && $post->getUser() !== $user) {
                $this->notificationService->sendNotification($post->getUser(), 'post_like', $user, $post);
            }
        } catch (\Exception $exception) {
            error_log('Erreur lors de l\'envoi de notification: ' . $exception->getMessage());
        }

        return $this->json([
            'status' => $status,
            'likesCount' => count($post->getLikes())
        ]);
    }

    #[Route('/post/{id}', name: 'app_post_show', methods: ['GET'])]
    public function show(Post $post): Response
    {
        if ($this->deviceDetectorService->isMobile()) {
            return $this->render('post/show_mobile.html.twig', [
                'post' => $post,
            ]);
        }
        return $this->render('post/show.html.twig', [
            'post' => $post,
        ]);
    }

    #[Route('/post/{id}/comment', name: 'app_post_comment', methods: ['POST'])]
    public function commentPost(Post $post, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $content = $request->request->get('content');
        if (empty(trim($content))) {
            return $this->json(['error' => 'Le contenu ne peut être vide.'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent(trim($content));
        $comment->setCreatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        try {
            if ($post->getUser() !== $user) {
                $this->notificationService->sendNotification($post->getUser(), 'post_comment', $user, $post);
            }
        } catch (\Exception $exception) {
            error_log('Erreur lors de l\'envoi de notification: ' . $exception->getMessage());
        }

        return $this->json([
            'status' => 'commented',
            'commentsCount' => count($post->getComments()),
            'comment' => [
                'id' => $comment->getId(),
                'user' => $user->getFullName(),
                'avatar' => $user->getAvatarPath() ?? '/uploads/avatars/user_default.png',
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('d/m/Y H:i')
            ]
        ]);
    }

    #[Route('/comment/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    public function editComment(Comment $comment, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        if ($comment->getUser() !== $user) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $content = $request->request->get('content');
        if (empty(trim($content))) {
            return $this->json(['error' => 'Le contenu ne peut être vide.'], Response::HTTP_BAD_REQUEST);
        }

        $comment->setContent(trim($content));
        $comment->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json([
            'status' => 'updated',
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'updatedAt' => $comment->getUpdatedAt()->format('d/m/Y H:i')
            ]
        ]);
    }

    #[Route('/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($comment->getUser() !== $user) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $post = $comment->getPost();
        $this->entityManager->remove($comment);
        $this->entityManager->flush();

        return $this->json([
            'status' => 'deleted',
            'commentsCount' => count($post->getComments())
        ]);
    }
}