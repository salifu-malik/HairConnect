<?php

declare(strict_types=1);

namespace Controllers;

use Api\Controllers\Auth\AuthController;
use App\Helpers\RefreshTokenCookieManagerInterface;
use App\Services\AuthService;
use App\Exceptions\AuthenticationException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class AuthControllerRefreshTokenTest extends TestCase
{
    private function makeController(
        ?AuthService $authService = null,
        ?RefreshTokenCookieManagerInterface $cookieManager = null
    ): AuthController {
        $authService ??= $this->createMock(AuthService::class);

        $cookieManager ??= $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        return new AuthController(
            authService: $authService,
            refreshTokenCookieManager: $cookieManager
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        unset($_COOKIE['refresh_token']);

        http_response_code(200);
    }

    protected function tearDown(): void
    {
        unset($_COOKIE['refresh_token']);

        http_response_code(200);

        parent::tearDown();
    }

    /**
     * Capture the JSON response produced by a controller action.
     */
    private function captureResponse(callable $action): array
    {
        ob_start();

        try {
            $action();
            $body = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $decoded = json_decode(
            $body === false ? '' : $body,
            true
        );

        $this->assertIsArray(
            $decoded,
            'The controller should return a valid JSON response.'
        );

        return $decoded;
    }

    public function testRefreshWithoutCookieReturns401(): void
    {
        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->never())
            ->method('refresh');

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->never())
            ->method('set');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->refresh()
        );

        $this->assertSame(401, http_response_code());
        $this->assertSame('error', $response['status']);
    }

    public function testSuccessfulRefreshRotatesCookieAndHidesRefreshToken(): void
    {
        $oldRefreshToken = 'old-test-refresh-token';
        $newRefreshToken = 'new-test-refresh-token';

        $_COOKIE['refresh_token'] = $oldRefreshToken;

        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->once())
            ->method('refresh')
            ->with($oldRefreshToken)
            ->willReturn([
                'token' => 'test-access-token',
                'refresh_token' => $newRefreshToken,
            ]);

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->once())
            ->method('set')
            ->with($newRefreshToken);

        $cookieManager
            ->expects($this->never())
            ->method('clear');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->refresh()
        );

        $this->assertSame(200, http_response_code());
        $this->assertSame('success', $response['status']);
        $this->assertSame(
            'test-access-token',
            $response['data']['token']
        );

        $this->assertArrayNotHasKey(
            'refresh_token',
            $response['data']
        );

        $this->assertStringNotContainsString(
            $newRefreshToken,
            json_encode($response)
        );
    }

    public function testInvalidRefreshTokenReturns401(): void
    {
        $_COOKIE['refresh_token'] = 'invalid-test-refresh-token';

        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->once())
            ->method('refresh')
            ->with('invalid-test-refresh-token')
            ->willThrowException(
                new AuthenticationException('Invalid or expired refresh token.')
            );

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->never())
            ->method('set');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->refresh()
        );

        $this->assertSame(401, http_response_code());
        $this->assertSame('error', $response['status']);
    }


    public function testUnexpectedRefreshErrorDoesNotLeakInternalDetails(): void
    {
        $_COOKIE['refresh_token'] = 'test-refresh-token';

        $authService = $this->createMock(AuthService::class);

        $authService->expects($this->once())
            ->method('refresh')
            ->with('test-refresh-token')
            ->willThrowException(
                new RuntimeException('SQL connection password leaked')
            );

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager->expects($this->never())->method('set');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->refresh()
        );

        $this->assertSame(500, http_response_code());
        $this->assertSame('error', $response['status']);

        $this->assertSame(
            'Unable to refresh your session. Please try again.',
            $response['message']
        );

        $this->assertStringNotContainsString(
            'SQL connection password leaked',
            json_encode($response)
        );
    }


    public function testSuccessfulLogoutRevokesTokenAndClearsCookie(): void
    {
        $refreshToken = 'test-refresh-token-to-revoke';

        $_COOKIE['refresh_token'] = $refreshToken;

        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->once())
            ->method('logout')
            ->with($refreshToken);

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->once())
            ->method('clear');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->logout()
        );

        $this->assertSame(200, http_response_code());
        $this->assertSame('success', $response['status']);
    }

    public function testLogoutWithoutCookieStillClearsCookie(): void
    {
        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->never())
            ->method('logout');

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->once())
            ->method('clear');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->logout()
        );

        $this->assertSame(200, http_response_code());
        $this->assertSame('success', $response['status']);
    }

    public function testLogoutClearsCookieWhenRevocationThrows(): void
    {
        $_COOKIE['refresh_token'] = 'test-refresh-token';

        $authService = $this->createMock(AuthService::class);

        $authService
            ->expects($this->once())
            ->method('logout')
            ->with('test-refresh-token')
            ->willThrowException(
                new RuntimeException('Database operation failed.')
            );

        $cookieManager = $this->createMock(
            RefreshTokenCookieManagerInterface::class
        );

        $cookieManager
            ->expects($this->once())
            ->method('clear');

        $controller = $this->makeController(
            $authService,
            $cookieManager
        );

        $response = $this->captureResponse(
            fn() => $controller->logout()
        );

        $this->assertSame(500, http_response_code());
        $this->assertSame('error', $response['status']);
    }
}