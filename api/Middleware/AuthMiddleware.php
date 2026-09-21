<?php
//
//namespace Api\Middleware;
//
//use App\Helpers\JwtHelper;
//
//class AuthMiddleware {
//    public static function checkRole(array $requiredRoles) {
//        $headers = getallheaders();
//        $authHeader = $headers['Authorization'] ?? '';
//
//        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
//            header("HTTP/1.0 401 Unauthorized");
//            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
//            exit;
//        }
//
//        $token = $matches[1];
//        $payload = JwtHelper::decode($token);
//
//        if (!$payload) {
//            header("HTTP/1.0 401 Unauthorized");
//            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
//            exit;
//        }
//    }
//}


namespace Api\Middleware;

use App\Helpers\JwtHelper;
use App\Repositories\UserRepository;

class AuthMiddleware
{
    public static function authenticate(): array
    {
        $headers = getallheaders();

        $authHeader = $headers['Authorization']
            ?? $headers['authorization']
            ?? '';

        if (!preg_match('/^Bearer\s+(.+)$/i', trim($authHeader), $matches)) {
            self::unauthorized('Authorization token required.');
        }

        $token = trim($matches[1]);

        $payload = JwtHelper::decode($token);

        if (!$payload) {
            self::unauthorized('Invalid or expired token.');
        }

        if (
            !isset($payload['user_id']) ||
            !is_numeric($payload['user_id'])
        ) {
            self::unauthorized('Invalid token payload.');
        }

        $userRepository = new UserRepository();

        $user = $userRepository->findById(
            (int)$payload['user_id']
        );

        if (!$user) {
            self::unauthorized('User account no longer exists.');
        }

        return [
            'user' => $user,
            'payload' => $payload
        ];
    }

    public static function checkRole(array $requiredRoles): array
    {
        $auth = self::authenticate();

        $userId = (int)$auth['user']->id;

        $userRepository = new UserRepository();

        $roles = $userRepository->getRoles($userId);

        foreach ($requiredRoles as $requiredRole) {
            if (in_array($requiredRole, $roles, true)) {
                return [
                    'user' => $auth['user'],
                    'payload' => $auth['payload'],
                    'roles' => $roles
                ];
            }
        }

        http_response_code(403);

        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'status' => 'error',
            'message' => 'Forbidden'
        ]);

        exit;
    }

    private static function unauthorized(string $message): never
    {
        http_response_code(401);

        header('Content-Type: application/json; charset=UTF-8');

        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);

        exit;
    }
}
