<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\User;
use App\Entity\Friend;
use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class NotificationService
{
    private EntityManagerInterface $entityManager;
    private HubInterface $hub;

    public function __construct(EntityManagerInterface $entityManager, HubInterface $hub)
    {
        $this->entityManager = $entityManager;
        $this->hub = $hub;
    }

    public function sendNotification(User $recipient, string $type, ?User $sender = null, ?Post $post = null)
    {
        // Définir le message de la notification en fonction du type
        $message = match ($type) {
            'friend_request'   => "Vous avez reçu une demande d'amitié de " . $sender->getUsername(),
            'friend_accept'    => $sender->getUsername() . " a accepté votre demande d'amitié.",
            'new_post'         => $sender->getUsername() . " a publié un nouveau post.",
            'post_like'        => $sender->getUsername() . " a aimé votre publication.",
            'post_comment'     => $sender->getUsername() . " a commenté votre publication.",
            default            => "Nouvelle notification."
        };

        // Enregistrer la notification en base de données
        $notification = new Notification();
        $notification->setRecipient($recipient);
        $notification->setSender($sender);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setPost($post);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        // Publier l'événement via Mercure
        $update = new Update(
            "notifications_user_" . $recipient->getId(),
            json_encode([
                'message' => $message,
                'type' => $type,
                'notificationId' => $notification->getId()
            ]),
            true
        );
        $this->hub->publish($update);
    }
}
