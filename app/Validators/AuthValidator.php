<?php

namespace App\Validators;

class AuthValidator {
    public static function validateRegistration(array $data) {
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email format.");
        }
        if (empty($data['password']) || strlen($data['password']) < 6) {
            throw new \Exception("Password must be at least 6 characters long.");
        }
        if (empty($data['firstname'])) {
            throw new \Exception("First Name is required.");
        }
        if (empty($data['lastname'])) {
            throw new \Exception("Last Name is required.");
        }
        if (empty($data['phone']) || strlen($data['phone']) < 10) {
            throw new \Exception("Valid phone number is required.");
        }
        if (empty($data['role']) || !in_array($data['role'], ['CUSTOMER', 'BARBER', 'VENDOR', 'SHOP_OWNER'])) {
            throw new \Exception("Invalid role.");
        }
    }
}
