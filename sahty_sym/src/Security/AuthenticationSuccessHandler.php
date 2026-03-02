<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class AuthenticationSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    private RouterInterface $router;

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return new RedirectResponse($this->router->generate('app_profile'));
        }

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $url = $this->router->generate('admin_index');
        } else {
            $url = $this->router->generate('app_profile');
        }

        return new RedirectResponse($url);
    }
}
