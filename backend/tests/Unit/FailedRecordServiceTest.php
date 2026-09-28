<?php

namespace Tests\Unit;

use App\Services\FailedRecordService;
use PHPUnit\Framework\TestCase;

class FailedRecordServiceTest extends TestCase
{
    public function test_escapes_formula_injection_characters(): void
    {
        $service = new FailedRecordService();

        $this->assertSame("'=SUM(1,2)", $service->escapeFormulaInjection('=SUM(1,2)'));
        $this->assertSame("'+12345", $service->escapeFormulaInjection('+12345'));
        $this->assertSame("'-54321", $service->escapeFormulaInjection('-54321'));
        $this->assertSame("'@malicious", $service->escapeFormulaInjection('@malicious'));
        $this->assertSame('Normal Lead', $service->escapeFormulaInjection('Normal Lead'));
        $this->assertSame('', $service->escapeFormulaInjection(''));
    }
}
