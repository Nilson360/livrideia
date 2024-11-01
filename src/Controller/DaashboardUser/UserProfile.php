<?php

namespace App\Controller\DaashboardUser;

use App\Entity\User;use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserProfile Extends AbstractController
{
    #[Route('/dashboard-user/profile', name: 'dashboard-user-profile')]
    public function profile(Request $request, EntityManagerInterface $em) : Response
    {
        $user = $em->getRepository(User::class)->find($this->getUser()->getId());

        return $this->render('dashboard_user/profile.html.twig');
    }
}