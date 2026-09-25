<?php

namespace App\Repositories;

use App\Models\User;
use App\Helpers\DatabaseManager;
use PDO;

class UserRepository {
    private PDO $db;

    public function __construct() {
        $this->db = DatabaseManager::getConnection('users_db');
    }


    public function getRoles(int $userId): array
    {
        $stmt = $this->db->prepare("
        SELECT r.name
        FROM roles r
        INNER JOIN user_roles ur ON ur.role_id = r.id
        WHERE ur.user_id = :user_id
    ");

        $stmt->execute([
            'user_id' => $userId
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function findByEmail(string $email): ?User {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }

    public function findById(int $id): ?User {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch();
        return $data ? new User($data) : null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (first_name, last_name, email, phone, password_hash)
            VALUES (:first_name, :last_name, :email, :phone, :password_hash)
        ");
        $stmt->execute([
            'first_name' => $data['firstname'],
            'last_name' => $data['lastname'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function assignRole(int $userId, string $roleName): void
    {
        $stmt = $this->db->prepare("
        SELECT id
        FROM roles
        WHERE name = :name
        LIMIT 1
    ");

        $stmt->execute([
            'name' => $roleName
        ]);

        $roleId = $stmt->fetchColumn();

        if (!$roleId) {
            throw new \RuntimeException(
                "Role '{$roleName}' does not exist."
            );
        }

        $stmt = $this->db->prepare("
        INSERT INTO user_roles (user_id, role_id)
        VALUES (:user_id, :role_id)
    ");

        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);
    }

    public function getPasswordHashById(int $userId): ?string
    {
        $stmt = $this->db->prepare("
        SELECT password_hash
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

        $stmt->execute([
            'id' => $userId
        ]);

        $passwordHash = $stmt->fetchColumn();

        return $passwordHash !== false
            ? $passwordHash
            : null;
    }


    public function updatePassword(
        int $userId,
        string $passwordHash
    ): void {
        $stmt = $this->db->prepare("
        UPDATE users
        SET password_hash = :password_hash
        WHERE id = :id
    ");

        $stmt->execute([
            'password_hash' => $passwordHash,
            'id' => $userId
        ]);
    }


    public function updateProfile(
        int $userId,
        string $firstName,
        string $lastName,
        string $phone
    ): void {
        $stmt = $this->db->prepare("
        UPDATE users
        SET
            first_name = :first_name,
            last_name = :last_name,
            phone = :phone
        WHERE id = :id
    ");

        $stmt->execute([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $phone,
            'id' => $userId
        ]);
    }

    public function updateProfileImage(
        int $userId,
        string $profileImage
    ): void {
        $stmt = $this->db->prepare("
        UPDATE users
        SET profile_image = :profile_image
        WHERE id = :id
    ");

        $stmt->execute([
            'profile_image' => $profileImage,
            'id' => $userId
        ]);
    }
}


