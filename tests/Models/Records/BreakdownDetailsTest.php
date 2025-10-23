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
        $details->regimeType = RegimeType::C18;
        $details->operationType = OperationType::Subject;
        $details->baseAmount = '11.22';
        $details->taxRate = '21.00';
        $details->taxAmount = '2.36';
        $details->surchargeRate = '5.20';
        $details->surchargeAmount = '0.58';

        // Should pass validation
        $details->validate();

        // Wrong tax amount
        $details->taxAmount = '99.99';
        $details->surchargeAmount = '99.99';
        try {
            $details->validate();
            $this->fail('Did not throw exception for invalid tax amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Expected tax amount of 2.36, got 99.99', $e->getMessage());
            $this->assertStringContainsString('Expected surcharge amount of 0.58, got 99.99', $e->getMessage());
        }

        // Acceptable tax amount differences
        $details->taxAmount = '2.35';
        $details->surchargeAmount = '0.57';
        $details->validate();
        $details->taxAmount = '2.37';
        $details->surchargeAmount = '0.59';
        $details->validate();
    }

    public function testValidatesOperationType(): void {
        $details = new BreakdownDetails();
        $details->taxType = TaxType::IVA;
        $details->baseAmount = '100.00';

        // Check error message when tax is not given and is subject
        $details->regimeType = RegimeType::C18;
        $details->operationType = OperationType::Subject;
        try {
            $details->validate();
            $this->fail('Did not throw exception for missing tax rate and amount');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Tax rate must be defined for subject operation types', $e->getMessage());
            $this->assertStringContainsString('Tax amount must be defined for subject operation types', $e->getMessage());
            $this->assertStringContainsString('Surcharge rate must be defined for C18 regime type', $e->getMessage());
            $this->assertStringContainsString('Surcharge amount must be defined for C18 regime type', $e->getMessage());
        }

        // Check no error message when tax is not given and is exempt
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::ExemptByOther;
        $details->validate();

        $details->taxRate = '21.00';
        $details->taxAmount = '21.00';
        $details->surchargeRate = '5.20';
        $details->surchargeAmount = '5.20';

        // Check error message when tax is given and is exempt
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::ExemptByOther;
        try {
            $details->validate();
            $this->fail('Did not throw exception for defined tax rate and ammount when the operation is exempt');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Tax rate cannot be defined for non-subject or exempt operation types', $e->getMessage());
            $this->assertStringContainsString('Tax amount cannot be defined for non-subject or exempt operation types', $e->getMessage());
            $this->assertStringContainsString('Surcharge rate cannot be defined for non-subject or exempt operation types', $e->getMessage());
            $this->assertStringContainsString('Surcharge amount cannot be defined for non-subject or exempt operation types', $e->getMessage());
        }

        // Check error when surcharge rate and ammount are given for subject
        $details->regimeType = RegimeType::C01;
        $details->operationType = OperationType::Subject;
        try {
            $details->validate();
            $this->fail('Did not throw exception for defined surcharge rate and ammount when the operation is not C18 regime type');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Surcharge rate cannot be defined for non-C18 regime types', $e->getMessage());
            $this->assertStringContainsString('Surcharge amount cannot be defined for non-C18 regime types', $e->getMessage());
        }

        // Check no error message when tax is given and is subject
        $details->regimeType = RegimeType::C18;
        $details->operationType = OperationType::Subject;
        $details->validate();
    }
}
