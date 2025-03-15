<?php

namespace App\Controller;

use App\Entity\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

class NotificationController extends AbstractController
{
    private EntityManagerInterface $em;
    private HubInterface $hub;

    public function __construct(EntityManagerInterface $em, HubInterface $hub) {
        $this->em = $em;
        $this->hub = $hub;
    }

    #[Route('/notify', name: 'send_notification', methods: ['GET'])]
    public function notify(): JsonResponse
    {
        $message = "Nouvelle notification reçue";

        // Enregistrer en base de données
        $notification = new Notification();
        $notification->setMessage($message);
        $this->em->persist($notification);
        $this->em->flush();

        // Envoyer la notification via Mercure
        $update = new Update(
            'notifications',
            json_encode(['message' => $message]),
            true
        );
        $this->hub->publish($update);

        return $this->json(['status' => 'Notification envoyée !']);
    }
}
