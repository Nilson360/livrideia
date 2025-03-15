<?php

// src/Controller/PostController.php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Like;
use App\Entity\Post;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
class PostController extends AbstractController
{
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;
    public function __construct(NotificationService $notificationService, EntityManagerInterface $entityManager)
    {
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
    }
    #[Route('/post/{id}/like', name: 'app_post_like', methods: ['POST'])]
    public function likePost(Post $post, EntityManagerInterface $em, Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        $likeRepository = $em->getRepository(Like::class);
        $existingLike = $likeRepository->findOneBy([
            'user' => $user,
            'post' => $post
        ]);

        if ($existingLike) {
            // Dé-sélectionner le like (toggle off)
            $em->remove($existingLike);
            $em->flush();
            $status = 'unliked';
        } else {
            // Créer un nouveau like (toggle on)
            $like = new Like();
            $like->setUser($user);
            $like->setPost($post);
            $like->setCreatedAt(new \DateTimeImmutable());
            $em->persist($like);
            $em->flush();
            $status = 'liked';
        }
        $this->notificationService->sendNotification($post->getUser(), 'post_like', $user, $post);
        // Retourne le nouveau nombre de likes
        return $this->json([
            'status' => $status,
            'likesCount' => count($post->getLikes())
        ]);
    }
    #[Route('/post/{id}/comment', name: 'app_post_comment', methods: ['POST'])]
    public function commentPost(Post $post, Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }

        // On récupère le contenu du commentaire envoyé
        $content = $request->request->get('content');
        if (empty(trim($content))) {
            return $this->json(['error' => 'Le contenu ne peut être vide.'], Response::HTTP_BAD_REQUEST);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setUser($user);
        $comment->setContent($content);
        $comment->setCreatedAt(new \DateTimeImmutable());

        $em->persist($comment);
        $em->flush();
        $this->notificationService->sendNotification($post->getUser(), 'post_comment', $user, $post);

        return $this->json([
            'status'  => 'commented',
            'comment' => [
                'id' => $comment->getId(),
                'user' => $user->getFullName(),
                'content' => $comment->getContent(),
                'createdAt' => $comment->getCreatedAt()->format('d/m/Y H:i'),
                'commentsCount' => count($post->getComments())
            ]
        ]);
    }
    #[Route('/comment/{id}/edit', name: 'app_comment_edit', methods: ['POST'])]
    public function editComment(Comment $comment, Request $request, EntityManagerInterface $em): Response
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
        $comment->setContent($content);
        $comment->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json([
            'status'  => 'updated',
            'comment' => [
                'id'        => $comment->getId(),
                'content'   => $comment->getContent(),
                'updatedAt' => $comment->getUpdatedAt()->format('d/m/Y H:i')
            ]
        ]);
    }
    #[Route('/comment/{id}/delete', name: 'app_comment_delete', methods: ['POST'])]
    public function deleteComment(Comment $comment, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Utilisateur non authentifié.'], Response::HTTP_UNAUTHORIZED);
        }
        // Vérifier que l'utilisateur est bien l'auteur du commentaire
        if ($comment->getUser() !== $user) {
            return $this->json(['error' => 'Accès refusé.'], Response::HTTP_FORBIDDEN);
        }

        $em->remove($comment);
        $em->flush();

        return $this->json(['status' => 'deleted']);
    }

}
