<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use PHPUnit\Framework\TestCase;

final class InvoiceIdentifierTest extends TestCase {
    public function testComparesInstances(): void {
        // Same instance
        $a = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T12:34:56+02:00'));
        $this->assertTrue($a->equals($a));

        // Same exact values
        $a = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T12:34:56+02:00'));
        $b = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T12:34:56+02:00'));
        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));

        // Different time
        $a = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T12:34:56+02:00'));
        $b = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T20:30:40+02:00'));
        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));

        // Different timezone, still same date
        $a = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T00:00:00+02:00'));
        $b = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T02:00:00+00:00'));
        $this->assertTrue($a->equals($b));
        $this->assertTrue($b->equals($a));

        // Different dates
        $a = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-11T00:00:00+02:00'));
        $b = new InvoiceIdentifier('IssuerId', 'InvoiceNumber123', new DateTimeImmutable('2025-10-17T00:00:00+02:00'));
        $this->assertFalse($a->equals($b));
        $this->assertFalse($b->equals($a));
    }
}
