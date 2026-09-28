<?php

namespace App\Services;

class LeadValidationService
{
    /**
     * Normalize raw row data before validation and deduplication.
     *
     * @param array $rawRow
     * @return array
     */
    public function normalize(array $rawRow): array
    {
        return [
            'name' => isset($rawRow['name']) && is_string($rawRow['name']) ? trim($rawRow['name']) : '',
            'email' => isset($rawRow['email']) && is_string($rawRow['email']) ? strtolower(trim($rawRow['email'])) : '',
            'phone' => isset($rawRow['phone']) && is_string($rawRow['phone']) ? trim($rawRow['phone']) : '',
            'company' => isset($rawRow['company']) && is_string($rawRow['company']) ? trim($rawRow['company']) : '',
        ];
    }

    /**
     * Validate normalized row attributes.
     * Returns an array with 'is_valid' (bool), 'reason' (string|null), and 'data' (array).
     *
     * @param array $data
     * @return array{is_valid: bool, reason: string|null, data: array}
     */
    public function validate(array $data): array
    {
        // 1. Validate Name
        if ($data['name'] === '') {
            return [
                'is_valid' => false,
                'reason' => 'Missing name',
                'data' => $data,
            ];
        }

        if (mb_strlen($data['name']) > 255) {
            return [
                'is_valid' => false,
                'reason' => 'Name exceeds 255 characters',
                'data' => $data,
            ];
        }

        // 2. Validate Email
        if ($data['email'] === '') {
            return [
                'is_valid' => false,
                'reason' => 'Missing email',
                'data' => $data,
            ];
        }

        if (mb_strlen($data['email']) > 255) {
            return [
                'is_valid' => false,
                'reason' => 'Email exceeds 255 characters',
                'data' => $data,
            ];
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return [
                'is_valid' => false,
                'reason' => 'Invalid email format',
                'data' => $data,
            ];
        }

        // 3. Validate Phone
        if ($data['phone'] === '') {
            return [
                'is_valid' => false,
                'reason' => 'Missing phone',
                'data' => $data,
            ];
        }

        // Reasonable international phone validation: allow optional leading +, digits, spaces, dashes, dots, parentheses, min 7 digits, max 30 chars
        if (!$this->isValidPhone($data['phone'])) {
            return [
                'is_valid' => false,
                'reason' => 'Invalid phone',
                'data' => $data,
            ];
        }

        // 4. Validate Company
        if ($data['company'] === '') {
            return [
                'is_valid' => false,
                'reason' => 'Missing company',
                'data' => $data,
            ];
        }

        if (mb_strlen($data['company']) > 255) {
            return [
                'is_valid' => false,
                'reason' => 'Company exceeds 255 characters',
                'data' => $data,
            ];
        }

        return [
            'is_valid' => true,
            'reason' => null,
            'data' => $data,
        ];
    }

    /**
     * Check if a phone string matches realistic global phone format.
     */
    protected function isValidPhone(string $phone): bool
    {
        // Strip out allowed formatting characters: +, spaces, dashes, brackets, dots
        $stripped = preg_replace('/[\s\-\.\(\)\+]/', '', $phone);

        // Must contain only digits after stripping formatting, and length between 7 and 15 digits (ITU-T E.164)
        if (!ctype_digit($stripped) || strlen($stripped) < 7 || strlen($stripped) > 20) {
            return false;
        }

        // Verify original doesn't have invalid characters
        return (bool) preg_match('/^\+?[0-9\s\-\.\(\)]{7,30}$/', $phone);
    }
}
