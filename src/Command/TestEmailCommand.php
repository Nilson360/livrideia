<?php

namespace App\Command;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[AsCommand(
    name: 'app:test-email',
    description: 'Teste l\'envoi d\'emails Livridea avec différents templates'
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface       $mailer,
        private ParameterBagInterface $params,
        private UrlGeneratorInterface $urlGenerator
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email de destination')
            ->addOption('type', 't', InputOption::VALUE_OPTIONAL, 'Type d\'email à tester', 'all')
            ->addOption('verbose', 'v', InputOption::VALUE_NONE, 'Affichage détaillé')
            ->setHelp('
Cette commande permet de tester tous les templates d\'emails de Livridea.

Types disponibles:
  - test          : Email de test technique
  - welcome       : Email de bienvenue
  - reset         : Email de reset password
  - newsletter    : Newsletter avec histoire
  - new-books     : Nouvelles sorties de livres
  - recommendations : Recommandations personnalisées
  - communication : Communication générale
  - all           : Tous les emails (par défaut)

Exemples:
  php bin/console app:test-email test@example.com
  php bin/console app:test-email test@example.com --type=welcome
  php bin/console app:test-email test@example.com --type=newsletter -v
            ');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $type = $input->getOption('type');
        $verbose = $input->getOption('verbose');

        $io->title('🧪 Test des Emails Livridea');
        $io->info("Destination: {$email}");
        $io->info("Type: {$type}");

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $io->error('Adresse email invalide!');
            return Command::FAILURE;
        }

        $results = [];

        try {
            switch ($type) {
                case 'test':
                    $results[] = $this->sendTestEmail($email, $io, $verbose);
                    break;
                case 'welcome':
                    $results[] = $this->sendWelcomeEmail($email, $io, $verbose);
                    break;
                case 'reset':
                    $results[] = $this->sendResetEmail($email, $io, $verbose);
                    break;
                case 'newsletter':
                    $results[] = $this->sendNewsletterEmail($email, $io, $verbose);
                    break;
                case 'new-books':
                    $results[] = $this->sendNewBooksEmail($email, $io, $verbose);
                    break;
                case 'recommendations':
                    $results[] = $this->sendRecommendationsEmail($email, $io, $verbose);
                    break;
                case 'communication':
                    $results[] = $this->sendCommunicationEmail($email, $io, $verbose);
                    break;
                case 'all':
                    $io->section('📧 Envoi de tous les templates...');
                    $results[] = $this->sendTestEmail($email, $io, $verbose);
                    $results[] = $this->sendWelcomeEmail($email, $io, $verbose);
                    $results[] = $this->sendResetEmail($email, $io, $verbose);
                    $results[] = $this->sendNewsletterEmail($email, $io, $verbose);
                    $results[] = $this->sendNewBooksEmail($email, $io, $verbose);
                    $results[] = $this->sendRecommendationsEmail($email, $io, $verbose);
                    $results[] = $this->sendCommunicationEmail($email, $io, $verbose);
                    break;
                default:
                    $io->error("Type '{$type}' non reconnu!");
                    return Command::FAILURE;
            }

            // Résumé des résultats
            $successful = array_filter($results);
            $failed = count($results) - count($successful);

            $io->section('📊 Résultats');
            $io->success("✅ {" . count($successful) . "} email(s) envoyé(s) avec succès");

            if ($failed > 0) {
                $io->error("❌ {$failed} email(s) en échec");
                return Command::FAILURE;
            }

            $io->note('Vérifiez votre boîte email (et le dossier spam si nécessaire)');
            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Erreur générale: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function sendTestEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('🧪 Envoi de l\'email de test...');

            $testEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('🧪 Test Email Livridea - Configuration')
                ->htmlTemplate('emails/test.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Utilisateur Test',
                        'username' => 'test_user',
                        'email' => $email
                    ],
                    'siteUrl' => 'https://livridea.com',
                    'loginUrl' => 'https://livridea.com/login',
                    'unsubscribeUrl' => 'https://livridea.com/unsubscribe'
                ]);

            $this->mailer->send($testEmail);
            $io->writeln('✅ Email de test envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec email test: ' . $e->getMessage());
            return false;
        }
    }

    private function sendWelcomeEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('🎉 Envoi de l\'email de bienvenue...');

            $welcomeEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('🎉 Bienvenue dans l\'univers Livridea!')
                ->htmlTemplate('emails/welcome.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Marie Dupont',
                        'username' => 'marie_dupont',
                        'email' => $email
                    ],
                    'loginUrl' => 'https://livridea.com/login',
                    'discoverUrl' => 'https://livridea.com/decouvrir',
                    'profileUrl' => 'https://livridea.com/profil',
                    'siteUrl' => 'https://livridea.com'
                ]);

            $this->mailer->send($welcomeEmail);
            $io->writeln('✅ Email de bienvenue envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec email bienvenue: ' . $e->getMessage());
            return false;
        }
    }

    private function sendResetEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('🔐 Envoi de l\'email de reset...');

            $resetEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('🔐 Réinitialisation de votre mot de passe - Livridea')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Jean Martin',
                        'username' => 'jean_martin',
                        'email' => $email
                    ],
                    'resetUrl' => 'https://livridea.com/reset-password/demo-token-' . bin2hex(random_bytes(16)),
                    'validUntil' => new \DateTime('+1 hour'),
                    'siteUrl' => 'https://livridea.com'
                ]);

            $this->mailer->send($resetEmail);
            $io->writeln('✅ Email de reset envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec email reset: ' . $e->getMessage());
            return false;
        }
    }

    private function sendNewsletterEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('📖 Envoi de la newsletter...');

            $newsletterEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('📖 L\'Histoire de la Semaine - Newsletter Livridea')
                ->htmlTemplate('emails/newsletter.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Sophie Leclerc',
                        'username' => 'sophie_leclerc'
                    ],
                    'story' => [
                        'title' => 'Les Murmures du Temps',
                        'subtitle' => 'Une histoire mystérieuse au cœur de Paris',
                        'date' => new \DateTime(),
                        'intro' => 'Découvrez l\'histoire captivante de Margot et du livre mystérieux qui va changer sa vie.',
                        'views' => '3.2K',
                        'comments' => '127',
                        'shares' => '238',
                        'featured_books' => [
                            [
                                'title' => 'L\'Ombre du Vent',
                                'author' => 'Carlos Ruiz Zafón',
                                'description' => 'Un roman gothique sur le pouvoir des livres et des mots.',
                                'rating' => 4.8
                            ],
                            [
                                'title' => 'Si par une nuit d\'hiver un voyageur',
                                'author' => 'Italo Calvino',
                                'description' => 'Une réflexion ludique sur l\'acte de lire.',
                                'rating' => 4.6
                            ]
                        ]
                    ],
                    'discussionUrl' => 'https://livridea.com/discussions/murmures-temps',
                    'storiesUrl' => 'https://livridea.com/histoires',
                    'siteUrl' => 'https://livridea.com',
                    'unsubscribeUrl' => 'https://livridea.com/unsubscribe'
                ]);

            $this->mailer->send($newsletterEmail);
            $io->writeln('✅ Newsletter envoyée');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec newsletter: ' . $e->getMessage());
            return false;
        }
    }

    private function sendNewBooksEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('🆕 Envoi de l\'email nouveautés...');

            $newBooksEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('🆕 Nouvelles sorties littéraires - Livridea')
                ->htmlTemplate('emails/new_books.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Pierre Moreau',
                        'username' => 'pierre_moreau'
                    ],
                    'featured_book' => [
                        'title' => 'L\'Archipel des Murmures',
                        'author' => 'Clara Beaumont'
                    ],
                    'new_books' => [
                        [
                            'title' => 'La Dernière Librairie',
                            'author' => 'Emma Rousseau',
                            'description' => 'Dans un monde où les livres physiques disparaissent, une libraire se bat pour sauver son commerce familial.',
                            'category' => 'Roman contemporain',
                            'price' => 19.90,
                            'publicationDate' => new \DateTime('-5 days')
                        ],
                        [
                            'title' => 'Mémoires d\'un Correcteur',
                            'author' => 'Antoine Lebon',
                            'description' => 'Les confessions humoristiques d\'un correcteur qui a traversé 40 ans d\'édition française.',
                            'category' => 'Autobiographie',
                            'price' => 16.50,
                            'originalPrice' => 22.00,
                            'publicationDate' => new \DateTime('-2 days')
                        ]
                    ],
                    'newBooksUrl' => 'https://livridea.com/nouveautes',
                    'preordersUrl' => 'https://livridea.com/precommandes',
                    'trendsUrl' => 'https://livridea.com/tendances',
                    'unsubscribeUrl' => 'https://livridea.com/unsubscribe'
                ]);

            $this->mailer->send($newBooksEmail);
            $io->writeln('✅ Email nouveautés envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec email nouveautés: ' . $e->getMessage());
            return false;
        }
    }

    private function sendRecommendationsEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('📚 Envoi des recommandations...');

            $recommendationsEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('📚 Vos recommandations personnalisées - Livridea')
                ->htmlTemplate('emails/book_recommendations.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Emma Dubois',
                        'username' => 'emma_dubois',
                        'favoriteGenres' => ['Fantasy', 'Romance', 'Thriller psychologique'],
                        'booksRead' => 127,
                        'favoriteBooks' => []
                    ],
                    'sectionTitle' => 'Recommandations de la semaine',
                    'books' => [
                        [
                            'title' => 'La Prophétie d\'Avalon',
                            'author' => 'Léa Marchant',
                            'description' => 'Une fantasy moderne où magie et technologie s\'entremêlent dans l\'Angleterre contemporaine.',
                            'rating' => 4.7,
                            'reviewsCount' => 342,
                            'matchReason' => 'Vous adorez la fantasy urbaine',
                            'similarity' => 96,
                            'price' => 18.90
                        ],
                        [
                            'title' => 'Les Secrets de l\'Esprit',
                            'author' => 'Marc Durand',
                            'description' => 'Un thriller psychologique haletant où un psychiatre découvre que ses patients cachent un terrible secret.',
                            'rating' => 4.4,
                            'reviewsCount' => 189,
                            'matchReason' => 'Basé sur vos lectures de thrillers',
                            'similarity' => 91,
                            'price' => 16.90
                        ]
                    ],
                    'recommendations' => [
                        'accuracy' => 94,
                        'count' => 234
                    ],
                    'booksUrl' => 'https://livridea.com/livres',
                    'quizUrl' => 'https://livridea.com/quiz-gouts',
                    'trendsUrl' => 'https://livridea.com/tendances',
                    'unsubscribeUrl' => 'https://livridea.com/unsubscribe'
                ]);

            $this->mailer->send($recommendationsEmail);
            $io->writeln('✅ Email recommandations envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('❌ Échec email recommandations: ' . $e->getMessage());
            return false;
        }
    }

    private function sendCommunicationEmail(string $email, SymfonyStyle $io, bool $verbose): bool
    {
        try {
            if ($verbose) $io->text('📢 Envoi de la communication...');

            $communicationEmail = (new TemplatedEmail())
                ->from($this->params->get('app.mailer.from_email'))
                ->to($email)
                ->subject('🚀 Nouvelle fonctionnalité - Clubs de lecture virtuels!')
                ->htmlTemplate('emails/communications.html.twig')
                ->context([
                    'user' => [
                        'fullName' => 'Alex Petit',
                        'username' => 'alex_petit'
                    ],
                    'communication' => [
                        'title' => 'Lancement des Clubs de Lecture Virtuels',
                        'type' => 'announcement',
                        'date' => new \DateTime(),
                        'callToAction' => [
                            'text' => 'Découvrir les clubs',
                            'url' => 'https://livridea.com/clubs'
                        ],
                        'testimonials' => [
                            [
                                'content' => 'J\'adore pouvoir discuter de mes lectures avec d\'autres passionnés! Les clubs virtuels sont géniaux.',
                                'author' => 'Marie L., lectrice depuis 2 ans'
                            ]
                        ]
                    ],
                    'feedbackUrl' => 'mailto:contact@livridea.com',
                    'communityUrl' => 'https://livridea.com/communaute',
                    'unsubscribeUrl' => 'https://livridea.com/unsubscribe'
                ]);

            $this->mailer->send($communicationEmail);
            $io->writeln('Email communication envoyé');
            return true;

        } catch (\Exception $e) {
            $io->error('Échec email communication: ' . $e->getMessage());
            return false;
        }
    }
}