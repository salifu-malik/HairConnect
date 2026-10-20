<?php

declare(strict_types=1);

namespace Controllers;

use Api\Controllers\Auth\AuthController;
use App\Exceptions\AuthenticationException;
use App\Exceptions\EmailNotVerifiedException;
use App\Helpers\LoginRiskAssessor;
use App\Helpers\RequestBodyReader;
use App\Helpers\TurnstileVerifierInterface;
use App\Services\AuthService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthControllerLoginTest extends TestCase
{
    /**
     * Create a controller with mocked dependencies.
     *
     * This prevents tests from contacting Cloudflare or authenticating
     * real users. The request body and risk level are controlled by each test.
     */
    private function makeController(
        array $requestData,
        string $riskLevel = 'low',
        bool $turnstileValid = false,
        ?AuthService $authService = null,
        ?LoginRiskAssessor $riskAssessor = null,
        ?TurnstileVerifierInterface $turnstileVerifier = null
    ): AuthController {
        $requestReader = $this->createMock(RequestBodyReader::class);

        $requestReader
            ->method('read')
            ->willReturn($requestData);

        $riskAssessor ??= $this->createMock(
            LoginRiskAssessor::class
        );

        $riskAssessor
            ->method('assess')
            ->willReturn([
                'level' => $riskLevel,
                'reason' => 'phpunit_test',
            ]);

        $turnstileVerifier ??= $this->createMock(
            TurnstileVerifierInterface::class
        );

        $turnstileVerifier
            ->method('verify')
            ->willReturn($turnstileValid);

        $authService ??= $this->createMock(
            AuthService::class
        );

        return new AuthController(
            $authService,
            $riskAssessor,
            $turnstileVerifier,
            $requestReader
        );
    }

    protected function tearDown(): void
    {
        http_response_code(200);

        unset($_SERVER['REMOTE_ADDR']);

        parent::tearDown();
    }

    /**
     * Malformed or empty JSON input should return HTTP 400.
     */
    public function testMalformedRequestReturns400(): void
    {
        $requestReader = $this->createMock(
            RequestBodyReader::class
        );

        $requestReader
            ->method('read')
            ->willReturn(null);

        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $controller = new AuthController(
            $authService,
            null,
            null,
            $requestReader
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(400, http_response_code());

        $this->assertSame(
            'Invalid login request.',
            $response['message']
        );
    }

    /**
     * Missing credentials should return HTTP 400.
     */
    public function testMissingCredentialsReturns400(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $controller = $this->makeController(
            [
                'email' => '',
                'password' => '',
            ],
            'low',
            false,
            $authService
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(400, http_response_code());

        $this->assertSame(
            'Email and password are required.',
            $response['message']
        );
    }

    /**
     * High-risk requests should be rejected before authentication.
     */
    public function testHighRiskRequestReturns429WithoutAuthentication(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'example-password',
            ],
            'high',
            false,
            $authService
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(429, http_response_code());

        $this->assertSame(
            'Too many requests. Please try again later.',
            $response['message']
        );
    }

    /**
     * Medium-risk requests without a token should be rejected.
     */
    public function testMediumRiskWithoutTokenReturns403(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $verifier = $this->createMock(
            TurnstileVerifierInterface::class
        );

        $verifier
            ->expects($this->never())
            ->method('verify');

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'example-password',
            ],
            'medium',
            false,
            $authService,
            null,
            $verifier
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(403, http_response_code());

        $this->assertSame(
            'Security verification failed. Please try again.',
            $response['message']
        );
    }

    /**
     * A rejected Turnstile token should block authentication.
     */
    public function testMediumRiskWithRejectedTokenReturns403(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $verifier = $this->createMock(
            TurnstileVerifierInterface::class
        );

        $verifier
            ->expects($this->once())
            ->method('verify')
            ->with('invalid-test-token')
            ->willReturn(false);

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'example-password',
                'turnstile_token' => 'invalid-test-token',
            ],
            'medium',
            false,
            $authService,
            null,
            $verifier
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(403, http_response_code());

        $this->assertSame(
            'Security verification failed. Please try again.',
            $response['message']
        );
    }

    /**
     * Invalid credentials should return HTTP 401.
     */
    public function testInvalidCredentialsReturn401(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->once())
            ->method('login')
            ->with(
                'test@example.invalid',
                'wrong-password'
            )
            ->willThrowException(
                new AuthenticationException(
                    'Invalid credentials.'
                )
            );

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'wrong-password',
            ],
            'low',
            false,
            $authService
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(401, http_response_code());

        $this->assertSame(
            'Invalid credentials.',
            $response['message']
        );
    }

    /**
     * Accounts with unverified email addresses should be rejected.
     */
    public function testUnverifiedEmailReturns403(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->once())
            ->method('login')
            ->willThrowException(
                new EmailNotVerifiedException(
                    'Please verify your email before logging in.'
                )
            );

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'correct-password',
            ],
            'low',
            false,
            $authService
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(403, http_response_code());

        $this->assertSame(
            'Please verify your email before logging in.',
            $response['message']
        );
    }

    /**
     * Turnstile infrastructure failures should return HTTP 503.
     */
    public function testTurnstileInfrastructureFailureReturns503(): void
    {
        $verifier = $this->createMock(
            TurnstileVerifierInterface::class
        );

        $verifier
            ->expects($this->once())
            ->method('verify')
            ->with('test-token')
            ->willThrowException(
                new RuntimeException(
                    'Simulated verification infrastructure failure.'
                )
            );

        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->never())
            ->method('login');

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'example-password',
                'turnstile_token' => 'test-token',
            ],
            'medium',
            false,
            $authService,
            null,
            $verifier
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(503, http_response_code());

        $this->assertSame(
            'Login security is temporarily unavailable. Please try again later.',
            $response['message']
        );

        $this->assertStringNotContainsString(
            'Simulated verification infrastructure failure.',
            $body
        );
    }

    /**
     * Low-risk requests should not require Turnstile verification.
     */
    public function testLowRiskLoginDoesNotRequireTurnstile(): void
    {
        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->once())
            ->method('login')
            ->with(
                'test@example.invalid',
                'example-password'
            )
            ->willThrowException(
                new AuthenticationException(
                    'Invalid credentials.'
                )
            );

        $verifier = $this->createMock(
            TurnstileVerifierInterface::class
        );

        $verifier
            ->expects($this->never())
            ->method('verify');

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'example-password',
            ],
            'low',
            false,
            $authService,
            null,
            $verifier
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(401, http_response_code());

        $this->assertSame(
            'Invalid credentials.',
            $response['message']
        );
    }

    /**
     * A valid Turnstile token should allow medium-risk requests
     * to proceed to authentication.
     */
    public function testValidTurnstileAllowsMediumRiskLoginToProceed(): void
    {
        $verifier = $this->createMock(
            TurnstileVerifierInterface::class
        );

        $verifier
            ->expects($this->once())
            ->method('verify')
            ->with('valid-test-token')
            ->willReturn(true);

        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->once())
            ->method('login')
            ->with(
                'test@example.invalid',
                'wrong-password'
            )
            ->willThrowException(
                new AuthenticationException(
                    'Invalid credentials.'
                )
            );

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'wrong-password',
                'turnstile_token' => 'valid-test-token',
            ],
            'medium',
            true,
            $authService,
            null,
            $verifier
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(401, http_response_code());

        $this->assertSame(
            'Invalid credentials.',
            $response['message']
        );
    }

    /**
     * Successful authentication should return the access token
     * while excluding the refresh token from the JSON response.
     */
    public function testSuccessfulLoginReturns200WithoutExposingRefreshToken(): void
    {
        $refreshToken = bin2hex(random_bytes(64));

        $authService = $this->createMock(
            AuthService::class
        );

        $authService
            ->expects($this->once())
            ->method('login')
            ->with(
                'test@example.invalid',
                'correct-password'
            )
            ->willReturn([
                'user' => [
                    'id' => 123,
                    'email' => 'test@example.invalid',
                    'roles' => ['CUSTOMER'],
                ],
                'token' => 'test-access-token',
                'refresh_token' => $refreshToken,
            ]);

        $controller = $this->makeController(
            [
                'email' => 'test@example.invalid',
                'password' => 'correct-password',
            ],
            'low',
            false,
            $authService
        );

        ob_start();
        $controller->login();
        $body = ob_get_clean();

        $response = json_decode($body, true);

        $this->assertSame(200, http_response_code());

        $this->assertSame(
            'success',
            $response['status']
        );

        $this->assertSame(
            'test-access-token',
            $response['data']['token']
        );

        $this->assertArrayNotHasKey(
            'refresh_token',
            $response['data']
        );

        $this->assertStringNotContainsString(
            $refreshToken,
            $body
        );
    }
}