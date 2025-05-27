<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\DeviceDetectorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    private DeviceDetectorService $deviceDetector;

    public function __construct(DeviceDetectorService $deviceDetector)
    {
        $this->deviceDetector = $deviceDetector;
    }

    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, Security $security, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            $response = $security->login($user, 'form_login', 'main');

            if($response instanceof Response) {
                return $response;
            }
            return $this->redirectToRoute('app_home');
        }

        $templateData = [
            'registrationForm' => $form,
        ];

        // Détection de l'appareil et choix du template approprié
        if ($this->deviceDetector->isMobile()) {
            return $this->render('auth/mobile/registration/register.html.twig', $templateData);
        }

        // Version desktop par défaut
        return $this->render('auth/desktop/registration/register.html.twig', $templateData);
    }
}