<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\RemisionRequerimiento;
use PHPUnit\Framework\TestCase;

final class RemisionRequerimientoTest extends TestCase {
    public function testValidatesRequirementReferenceNotBlank(): void {
        $remision = new RemisionRequerimiento('REF123');

        // Should pass with valid reference
        $remision->validate();

        // Should fail with empty string
        $remision->requirementReference = '';
        try {
            $remision->validate();
            $this->fail('Did not throw exception for empty requirement reference');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('requirementReference', $e->getMessage());
        }
    }

    public function testValidatesRequirementReferenceMaxLength(): void {
        $remision = new RemisionRequerimiento('REF123');

        // Should pass with 18 characters (max length)
        $remision->requirementReference = str_repeat('A', 18);
        $remision->validate();

        // Should fail with more than 18 characters
        $remision->requirementReference = str_repeat('A', 19);
        try {
            $remision->validate();
            $this->fail('Did not throw exception for requirement reference exceeding max length');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('requirementReference', $e->getMessage());
        }
    }

    public function testValidatesIsRequirementEndType(): void {
        $remision = new RemisionRequerimiento('REF123');

        // Should pass with null
        $remision->isRequirementEnd = null;
        $remision->validate();
        $this->assertNull($remision->isRequirementEnd);

        // Should pass with boolean true
        $remision->isRequirementEnd = true;
        $remision->validate();
        $this->assertTrue($remision->isRequirementEnd);

        // Should pass with boolean false
        $remision->isRequirementEnd = false;
        $remision->validate();
        $this->assertFalse($remision->isRequirementEnd);
    }

    public function testConstructorSetsProperties(): void {
        $remision = new RemisionRequerimiento('REF123', true);
        $this->assertEquals('REF123', $remision->requirementReference);
        $this->assertTrue($remision->isRequirementEnd);

        $remision = new RemisionRequerimiento('REF456', false);
        $this->assertEquals('REF456', $remision->requirementReference);
        $this->assertFalse($remision->isRequirementEnd);

        $remision = new RemisionRequerimiento('REF789');
        $this->assertEquals('REF789', $remision->requirementReference);
        $this->assertFalse($remision->isRequirementEnd);
    }
}
