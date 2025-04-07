<?php

namespace App\Service;

use Symfony\Component\Routing\RouterInterface;
use App\Entity\User;
use App\Entity\Post;

class LinkGenerator
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function toProfile(User $user): string
    {
        return $this->router->generate('dashboard-user-profile-other', ['id' => $user->getId()]);
    }

    public function toChat(User $user): string
    {
        return $this->router->generate('app_chat') . '?user=' . $user->getId();
    }

    public function toPost(Post $post): string
    {
        return $this->router->generate('app_post_show', ['id' => $post->getId()]);
    }
}
