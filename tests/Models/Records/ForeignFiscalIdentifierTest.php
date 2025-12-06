<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use PHPUnit\Framework\TestCase;

final class ForeignFiscalIdentifierTest extends TestCase {
    public function testValidatesVatNumbers(): void {
        $identifier = new ForeignFiscalIdentifier();
        $identifier->name = 'Test Company Name';
        $identifier->country = 'FR';
        $identifier->type = ForeignIdType::VAT;
        $identifier->value = 'FR12345678901';

        // Should pass validation
        $identifier->validate();

        // Wrong country code
        $identifier->country = 'PT';
        try {
            $identifier->validate();
            $this->fail('Did not throw exception for invalid VAT number');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('VAT number must start with "PT", found "FR"', $e->getMessage());
        }

        // Only applies to VAT type
        $identifier->type = ForeignIdType::NationalId;
        $identifier->validate();
    }

    public function testValidatesType(): void {
        $identifier = new ForeignFiscalIdentifier();
        $identifier->name = 'Pepito Pérez Gómez';
        $identifier->country = 'ES';
        $identifier->value = 'BC858683';

        // Should pass validation
        $identifier->type = ForeignIdType::Passport;
        $identifier->validate();

        // Should pass validation as well
        $identifier->value = '49339626A';
        $identifier->type = ForeignIdType::Unregistered;
        $identifier->validate();

        // Only allow passport numbers and unregistered national numbers for Spanish IDs
        $identifier->type = ForeignIdType::Residence;
        try {
            $identifier->validate();
            $this->fail('Did not throw exception for invalid type');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Type must be passport or unregistered if country code is "ES"', $e->getMessage());
        }

        // Country code must be "ES" for unregistered IDs
        $identifier->country = 'PT';
        $identifier->type = ForeignIdType::Unregistered;
        try {
            $identifier->validate();
            $this->fail('Did not throw exception for unregistered type');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Country code must be "ES" if type is unregistered', $e->getMessage());
        }
    }
}
