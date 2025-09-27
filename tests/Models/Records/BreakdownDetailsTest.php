<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\TaxType;
use PHPUnit\Framework\TestCase;

final class BreakdownDetailsTest extends TestCase {
    public function testValidatesTaxAmount(): void {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::S1;
        $details->taxRate = '21.00';
        $details->baseAmount = '11.22';
        $details->taxAmount = '2.36';

        // Should pass validation
        $details->validate();

        // Wrong tax amount
        $details->taxAmount = '99.99';
        try {
            $details->validate();
            $this->fail('Did not throw exception for invalid tax amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Expected tax amount of 2.36, got 99.99', $e->getMessage());
        }

        // Acceptable tax amount differences
        $details->taxAmount = '2.35';
        $details->validate();
        $details->taxAmount = '2.37';
        $details->validate();
    }
}
