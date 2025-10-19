<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\VoluntaryDiscontinuation;
use PHPUnit\Framework\TestCase;

final class VoluntaryDiscontinuationTest extends TestCase {
    public function testConstructorWithDefaultValues(): void {
        $discontinuation = new VoluntaryDiscontinuation();
        $this->assertNull($discontinuation->endDate);
        $this->assertFalse($discontinuation->incident);
    }

    public function testConstructorWithValues(): void {
        $endDate = new DateTimeImmutable('2024-12-31');
        $discontinuation = new VoluntaryDiscontinuation($endDate, true);
        $this->assertEquals($endDate, $discontinuation->endDate);
        $this->assertTrue($discontinuation->incident);
    }

    public function testConstructorWithNullValues(): void {
        $discontinuation = new VoluntaryDiscontinuation(null, null);
        $this->assertNull($discontinuation->endDate);
        $this->assertFalse($discontinuation->incident);
    }

    public function testValidationRequiresEndDate(): void {
        $discontinuation = new VoluntaryDiscontinuation();
        $discontinuation->incident = false;

        try {
            $discontinuation->validate();
            $this->fail('Did not throw exception for missing end date');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('endDate', $e->getMessage());
        }
    }

    public function testValidationAcceptsValidData(): void {
        $discontinuation = new VoluntaryDiscontinuation();
        $discontinuation->endDate = new DateTimeImmutable('2024-12-31');
        $discontinuation->incident = true;

        // Should not throw exception
        $discontinuation->validate();
        $this->assertTrue(true);
    }

    public function testValidationAcceptsBooleanIncident(): void {
        $discontinuation = new VoluntaryDiscontinuation();
        $discontinuation->endDate = new DateTimeImmutable('2024-12-31');
        $discontinuation->incident = false;

        // Should not throw exception
        $discontinuation->validate();
        $this->assertFalse($discontinuation->incident);

        $discontinuation->incident = true;
        $discontinuation->validate();
        $this->assertTrue($discontinuation->incident);
    }
}
