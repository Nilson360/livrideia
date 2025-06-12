<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Service\DeviceDetectorService;
use App\Service\LinkGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[isGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HubInterface           $hub,
        private readonly LinkGenerator          $linkGenerator,
        private readonly DeviceDetectorService  $deviceDetector
    )
    {
    }

    #[Route('/notify', name: 'send_notification', methods: ['GET', 'POST'])]
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

        // Paramètres communs pour les templates
        $templateData = [
            'notifications' => $notifications,
            'linkGenerator' => $this->linkGenerator,
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('home/mobile/notifications/list.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('home/desktop/notifications/list.html.twig', $templateData);
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

        // Redirection différente selon l'appareil
        if ($this->deviceDetector->isMobile()) {
            // Si on vient d'une page de détail, on peut rediriger vers la liste des notifications
            $referer = $this->get('request_stack')->getCurrentRequest()->headers->get('referer');
            if (strpos($referer, 'notification/detail') !== false) {
                return $this->redirectToRoute('notifications_list');
            }

            // Sinon on reste sur la même page
            return $this->redirect($referer ?: $this->generateUrl('notifications_list'));
        }

        // Version desktop
        return $this->redirectToRoute('notifications_list');
    }

    #[Route('/notification/detail/{id}', name: 'notification_detail', methods: ['GET'])]
    public function notificationDetail(Notification $notification): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        if ($notification->getRecipient() !== $user) {
            throw $this->createAccessDeniedException();
        }

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('mobile/notifications/detail.html.twig', [
                'notification' => $notification,
                'linkGenerator' => $this->linkGenerator,
            ]);
        }

        // Version desktop par défaut
        return $this->render('notifications/detail.html.twig', [
            'notification' => $notification,
            'linkGenerator' => $this->linkGenerator,
        ]);
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

    #[Route('/notifications/mark-all-read', name: 'notifications_mark_all_read', methods: ['POST'])]
    public function markAllNotificationsAsRead(): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $unreadNotifications = $this->em->getRepository(Notification::class)
            ->findBy(['recipient' => $user, 'isRead' => false]);

        foreach ($unreadNotifications as $notification) {
            $notification->setIsRead(true);
        }

        $this->em->flush();

        // Redirection différente selon l'appareil
        if ($this->deviceDetector->isMobile()) {
            return $this->redirectToRoute('notifications_list');
        }

        return $this->redirectToRoute('notifications_list');
    }
}