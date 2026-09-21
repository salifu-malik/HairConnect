<?php

namespace App\Models;

class User implements \JsonSerializable
{
    public int $id;
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phone;
    public ?string $profileImage;
    private string $passwordHash;
    public string $status;
    public array $roles = [];

    public function __construct(array $data)
    {
        $this->id = (int)($data['id'] ?? 0);

        $this->firstName = $data['first_name'] ?? '';

        $this->lastName = $data['last_name'] ?? '';

        $this->email = $data['email'] ?? '';

        $this->phone = $data['phone'] ?? '';

        $this->passwordHash = $data['password_hash'] ?? '';

        $this->profileImage = $data['profile_image'] ?? null;

        $this->status = $data['status'] ?? 'active';
    }

    public function jsonSerialize(): mixed
    {
        return [
            'id' => \App\Helpers\HashHelper::encode($this->id),
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'profile_image' => $this->profileImage,
            'status' => $this->status,
            'roles' => $this->roles,
        ];
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


    public function getPasswordHash(): string
    {
        return $this->passwordHash;
    }
}