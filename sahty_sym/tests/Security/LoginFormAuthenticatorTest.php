<?php

namespace App\Tests\Security;

use App\Security\AuthenticationSuccessHandler;
use App\Security\LoginFormAuthenticator;
use App\Security\RecaptchaVerifier;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class LoginFormAuthenticatorTest extends TestCase
{
    private function createAuthenticator(
        RouterInterface $router,
        AuthenticationSuccessHandler $successHandler,
        RecaptchaVerifier $recaptchaVerifier,
        float $minScore = 0.5
    ): LoginFormAuthenticator {
        return new LoginFormAuthenticator($router, $successHandler, $recaptchaVerifier, $minScore);
    }

    public function testAuthenticateReturnsPassportAndSetsLastUsername(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $successHandler = $this->createMock(AuthenticationSuccessHandler::class);
        $recaptchaVerifier = $this->createMock(RecaptchaVerifier::class);

        $recaptchaVerifier->expects($this->once())
            ->method('verifyV3')
            ->with('token123', 'login', 0.5, '127.0.0.1')
            ->willReturn(true);

        $authenticator = $this->createAuthenticator($router, $successHandler, $recaptchaVerifier);

        $request = Request::create(
            '/login',
            'POST',
            [
                '_username' => '  test@example.com ',
                '_password' => 'secret',
                '_csrf_token' => 'csrf',
                'g-recaptcha-response' => 'token123',
            ],
            [],
            [],
            ['REMOTE_ADDR' => '127.0.0.1']
        );
        $session = new Session(new MockArraySessionStorage());
        $request->setSession($session);

        $passport = $authenticator->authenticate($request);

        $this->assertSame('test@example.com', $session->get(SecurityRequestAttributes::LAST_USERNAME));
        $this->assertInstanceOf(UserBadge::class, $passport->getBadge(UserBadge::class));
        $this->assertSame('test@example.com', $passport->getBadge(UserBadge::class)->getUserIdentifier());
        $credentials = $passport->getBadge(PasswordCredentials::class);
        $this->assertInstanceOf(PasswordCredentials::class, $credentials);
        $this->assertSame('secret', $credentials->getPassword());
        $this->assertInstanceOf(CsrfTokenBadge::class, $passport->getBadge(CsrfTokenBadge::class));
        $this->assertInstanceOf(RememberMeBadge::class, $passport->getBadge(RememberMeBadge::class));
    }

    public function testAuthenticateThrowsWhenRecaptchaFails(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $successHandler = $this->createMock(AuthenticationSuccessHandler::class);
        $recaptchaVerifier = $this->createMock(RecaptchaVerifier::class);

        $recaptchaVerifier->expects($this->once())
            ->method('verifyV3')
            ->willReturn(false);
        $recaptchaVerifier->method('getLastError')
            ->willReturn([
                'reason' => 'missing_token',
                'errorCodes' => ['invalid-input-response'],
            ]);

        $authenticator = $this->createAuthenticator($router, $successHandler, $recaptchaVerifier);

        $request = Request::create('/login', 'POST', [
            '_username' => 'user@example.com',
            '_password' => 'secret',
            '_csrf_token' => 'csrf',
            'g-recaptcha-response' => null,
        ]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Veuillez valider le reCAPTCHA.');

        $authenticator->authenticate($request);
    }

    public function testOnAuthenticationSuccessDelegates(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $successHandler = $this->createMock(AuthenticationSuccessHandler::class);
        $recaptchaVerifier = $this->createMock(RecaptchaVerifier::class);

        $response = new Response('ok');
        $successHandler->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->willReturn($response);

        $authenticator = $this->createAuthenticator($router, $successHandler, $recaptchaVerifier);

        $request = new Request();
        $token = $this->createMock(TokenInterface::class);

        $result = $authenticator->onAuthenticationSuccess($request, $token, 'main');

        $this->assertSame($response, $result);
    }
}
