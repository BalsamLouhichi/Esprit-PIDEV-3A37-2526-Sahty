<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    private RouterInterface $router;
    private AuthenticationSuccessHandler $successHandler;
    private RecaptchaVerifier $recaptchaVerifier;
    private float $recaptchaMinScore;

    public function __construct(
        RouterInterface $router,
        AuthenticationSuccessHandler $successHandler,
        RecaptchaVerifier $recaptchaVerifier,
        float $recaptchaMinScore
    ) {
        $this->router = $router;
        $this->successHandler = $successHandler;
        $this->recaptchaVerifier = $recaptchaVerifier;
        $this->recaptchaMinScore = $recaptchaMinScore;
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->router->generate('app_login');
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->get('_username', '');
        if (!is_string($email)) {
            $email = '';
        }
        $email = trim($email);

        if ($request->hasSession()) {
            $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);
        }

        $recaptchaToken = $request->request->get('g-recaptcha-response');
        $recaptchaToken = is_string($recaptchaToken) ? $recaptchaToken : null;
        if (!$this->recaptchaVerifier->verifyV3($recaptchaToken, 'login', $this->recaptchaMinScore, $request->getClientIp())) {
            $details = $this->recaptchaVerifier->getLastError();
            $message = 'Veuillez valider le reCAPTCHA.';
            if (!empty($details['reason'])) {
                $message .= ' (' . $details['reason'] . ')';
            }
            if (!empty($details['errorCodes']) && is_array($details['errorCodes'])) {
                $message .= ' [' . implode(', ', $details['errorCodes']) . ']';
            }
            throw new CustomUserMessageAuthenticationException($message);
        }

        $password = $request->request->get('_password', '');
        $csrfToken = $request->request->get('_csrf_token', '');

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials(is_string($password) ? $password : ''),
            [
                new CsrfTokenBadge('authenticate', is_string($csrfToken) ? $csrfToken : ''),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }
}
