<?php

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
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

            $response  =  $security->login($user, 'form_login', 'main');

            if($response instanceof Response) {
                return $response;
            }
            return $this->redirectToRoute('app_home');
        }

        return $this->render('auth/registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    // Désactiver temporairement cette route si la vérification d'email n'est pas nécessaire
    // #[Route('/verify/email', name: 'app_verify_email')]
    // public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    // {
    //     $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
    //
    //     try {
    //         /** @var User $user */
    //         $user = $this->getUser();
    //         $this->emailVerifier->handleEmailConfirmation($request, $user);
    //     } catch (VerifyEmailExceptionInterface $exception) {
    //         $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));
    //
    //         return $this->redirectToRoute('app_register');
    //     }
    //
    //     $this->addFlash('success', 'Your email address has been verified.');
    //     return $this->redirectToRoute('app_register');
    // }
}
