<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\LinkGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class NotificationController extends AbstractController
{
    private EntityManagerInterface $em;
    private HubInterface $hub;
    private LinkGenerator $linkGenerator;

    public function __construct(EntityManagerInterface $em, HubInterface $hub, LinkGenerator $linkGenerator)
    {
        $this->em = $em;
        $this->hub = $hub;
        $this->linkGenerator = $linkGenerator;
    }

    #[Route('/notify', name: 'send_notification', methods: ['GET','POST'])]
    public function notify(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['status' => 'User not authenticated'], 401);
        }

        $message = "Votre publication a reçu un nouveau like !";
        $type = "like";

        $notification = new Notification();
        $notification->setMessage($message)
            ->setType($type)
            ->setRecipient($user);
        $this->em->persist($notification);
        $this->em->flush();

        $link = $this->linkGenerator->toProfile($user);

        $update = new Update(
            'notifications_user_' . $user->getId(),
            json_encode([
                'message' => $message,
                'type' => $type,
                'notificationId' => $notification->getId(),
                'link' => $link
            ]),
            true
        );
        $this->hub->publish($update);

        return $this->json(['status' => 'Notification envoyée !']);
    }

    #[Route('/notifications', name: 'notifications_list', methods: ['GET'])]
    public function notificationList(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $notifications = $this->em->getRepository(Notification::class)->findBy(
            ['recipient' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('notifications/list.html.twig', [
            'notifications' => $notifications,
            'linkGenerator' => $this->linkGenerator,
        ]);
    }

    #[Route('/notification/read/{id}', name: 'notification_mark_as_read', methods: ['POST'])]
    public function notificationRead(Notification $notification): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['status' => 'User not authenticated'], 403);
        }

        if ($notification->getRecipient() !== $user) {
            return $this->json(['status' => 'Access denied'], 403);
        }

        $notification->setIsRead(true);
        $this->em->flush();

        return $this->redirectToRoute('notifications_list');
    }

    #[Route('/notifications/unread-count', name: 'notifications_unread_count', methods: ['GET'])]
    public function getUnreadNotificationCount(): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['count' => 0]);
        }

        $count = $this->em->getRepository(Notification::class)
            ->count(['recipient' => $user, 'isRead' => false]);

        return $this->json(['count' => $count]);
    }
}
