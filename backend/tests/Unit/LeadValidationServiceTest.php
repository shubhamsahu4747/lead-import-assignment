<?php

namespace Tests\Unit;

use App\Services\LeadValidationService;
use PHPUnit\Framework\TestCase;

class LeadValidationServiceTest extends TestCase
{
    protected LeadValidationService $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new LeadValidationService();
    }

    public function test_normalizes_whitespace_and_lowercase_email(): void
    {
        $raw = [
            'name' => '   John Doe   ',
            'email' => '  JOHN.DOE@EXAMPLE.COM  ',
            'phone' => '  +1-555-0199  ',
            'company' => '  Acme Corp  ',
        ];

        $normalized = $this->validator->normalize($raw);

        $this->assertSame('John Doe', $normalized['name']);
        $this->assertSame('john.doe@example.com', $normalized['email']);
        $this->assertSame('+1-555-0199', $normalized['phone']);
        $this->assertSame('Acme Corp', $normalized['company']);
    }

    public function test_accepts_valid_lead(): void
    {
        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'phone' => '+1-555-0123',
            'company' => 'Global Logistics Inc',
        ];

        $result = $this->validator->validate($data);

        $this->assertTrue($result['is_valid']);
        $this->assertNull($result['reason']);
    }

    public function test_rejects_missing_name(): void
    {
        $data = [
            'name' => '',
            'email' => 'valid@example.com',
            'phone' => '+1-555-0123',
            'company' => 'Acme',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Missing name', $result['reason']);
    }

    public function test_rejects_missing_email(): void
    {
        $data = [
            'name' => 'John',
            'email' => '',
            'phone' => '+1-555-0123',
            'company' => 'Acme',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Missing email', $result['reason']);
    }

    public function test_rejects_invalid_email_format(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'not-an-email',
            'phone' => '+1-555-0123',
            'company' => 'Acme',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Invalid email format', $result['reason']);
    }

    public function test_rejects_missing_phone(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'phone' => '',
            'company' => 'Acme',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Missing phone', $result['reason']);
    }

    public function test_rejects_invalid_phone(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'phone' => 'INVALID-PHONE-STRING',
            'company' => 'Acme',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Invalid phone', $result['reason']);
    }

    public function test_rejects_missing_company(): void
    {
        $data = [
            'name' => 'John',
            'email' => 'john@example.com',
            'phone' => '+1-555-0123',
            'company' => '',
        ];

        $result = $this->validator->validate($data);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('Missing company', $result['reason']);
    }
}
