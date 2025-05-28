<?php

namespace App\Service;

use App\Entity\Contact;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private Environment $twig;
    private LoggerInterface $logger;
    private string $fromEmail;
    private string $contactEmail;

    public function __construct(
        MailerInterface $mailer,
        Environment $twig,
        LoggerInterface $logger,
        string $fromEmail = 'noreply@livridea.fr',
        string $contactEmail = 'contact@livridea.fr'
    ) {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->logger = $logger;
        $this->fromEmail = $fromEmail;
        $this->contactEmail = $contactEmail;
    }

    /**
     * Envoie un email de contact depuis le formulaire
     */
    public function sendContactEmail(Contact $contact): bool
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->replyTo($contact->getEmail())
                ->to($this->contactEmail)
                ->subject('Nouveau message de contact - ' . $contact->getSubject())
                ->html($this->twig->render('emails/contact.html.twig', [
                    'contact' => $contact
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email de contact envoyé avec succès', [
                'contact_email' => $contact->getEmail(),
                'subject' => $contact->getSubject()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de contact', [
                'error' => $e->getMessage(),
                'contact_email' => $contact->getEmail(),
                'subject' => $contact->getSubject()
            ]);

            return false;
        }
    }

    /**
     * Envoie un email de confirmation à l'utilisateur
     */
    public function sendContactConfirmation(Contact $contact): bool
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($contact->getEmail())
                ->subject('Confirmation de réception - Livridea')
                ->html($this->twig->render('emails/contact_confirmation.html.twig', [
                    'contact' => $contact
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email de confirmation envoyé avec succès', [
                'contact_email' => $contact->getEmail()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de confirmation', [
                'error' => $e->getMessage(),
                'contact_email' => $contact->getEmail()
            ]);

            return false;
        }
    }

    /**
     * Envoie un email de bienvenue lors de l'inscription
     */
    public function sendWelcomeEmail(string $userEmail, string $userName): bool
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($userEmail)
                ->subject('Bienvenue sur Livridea !')
                ->html($this->twig->render('emails/welcome.html.twig', [
                    'userName' => $userName
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email de bienvenue envoyé avec succès', [
                'user_email' => $userEmail,
                'user_name' => $userName
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de bienvenue', [
                'error' => $e->getMessage(),
                'user_email' => $userEmail,
                'user_name' => $userName
            ]);

            return false;
        }
    }

    /**
     * Envoie un email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(string $userEmail, string $resetToken): bool
    {
        try {
            $email = (new Email())
                ->from($this->fromEmail)
                ->to($userEmail)
                ->subject('Réinitialisation de votre mot de passe - Livridea')
                ->html($this->twig->render('emails/password_reset.html.twig', [
                    'resetToken' => $resetToken
                ]));

            $this->mailer->send($email);

            $this->logger->info('Email de réinitialisation envoyé avec succès', [
                'user_email' => $userEmail
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email de réinitialisation', [
                'error' => $e->getMessage(),
                'user_email' => $userEmail
            ]);

            return false;
        }
    }

    /**
     * Envoie la newsletter
     */
    public function sendNewsletterEmail(array $subscribers, string $subject, string $content): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => []
        ];

        foreach ($subscribers as $subscriber) {
            try {
                $email = (new Email())
                    ->from($this->fromEmail)
                    ->to($subscriber['email'])
                    ->subject($subject)
                    ->html($this->twig->render('emails/newsletter.html.twig', [
                        'subscriber' => $subscriber,
                        'content' => $content
                    ]));

                $this->mailer->send($email);
                $results['success']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'email' => $subscriber['email'],
                    'error' => $e->getMessage()
                ];

                $this->logger->error('Erreur lors de l\'envoi de la newsletter', [
                    'error' => $e->getMessage(),
                    'subscriber_email' => $subscriber['email']
                ]);
            }
        }

        $this->logger->info('Envoi de newsletter terminé', [
            'success_count' => $results['success'],
            'failed_count' => $results['failed']
        ]);

        return $results;
    }
}