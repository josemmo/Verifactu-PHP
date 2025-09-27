<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\CorrectiveType;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;

final class RegistrationRecordTest extends TestCase {
    public function testCalculatesHashForFirstRecord(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'PRUEBA-0001';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->invoiceType = InvoiceType::Simplificada;
        $record->description = 'Factura simplificada de prueba';
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::S1;
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxAmount = '2.10';
        $record->totalTaxAmount = '2.10';
        $record->totalAmount = '12.10';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-01T10:20:30+02:00');
        $record->hash = $record->calculateHash();
        $this->assertEquals('F223F0A84F7D0C701C13C97CF10A1628FF9E46A003DDAEF3A804FBD799D82070', $record->hash);
        $record->validate();
    }

    public function testCalculatesHashForOtherRecords(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'PRUEBA-0002';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-02');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->invoiceType = InvoiceType::Simplificada;
        $record->description = 'Factura simplificada de prueba';
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::S1;
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->baseAmount = '100.00';
        $record->breakdown[0]->taxAmount = '21.00';
        $record->totalTaxAmount = '21.00';
        $record->totalAmount = '121.00';
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = 'A00000000';
        $record->previousInvoiceId->invoiceNumber = 'PRUEBA-001';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->previousHash = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $record->hashedAt = new DateTimeImmutable('2025-06-02T20:30:40+02:00');
        $record->hash = $record->calculateHash();
        $this->assertEquals('4566062C5A5D7DA4E0E876C0994071CD807962629F8D3C1F33B91EDAA65B2BA1', $record->hash);
        $record->validate();
    }

    #[DoesNotPerformAssertions]
    public function testValidatesTotalAmounts(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'TEST';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->invoiceType = InvoiceType::Simplificada;
        $record->description = 'Factura simplificada de prueba';
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::S1;
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->baseAmount = '12.34';
        $record->breakdown[0]->taxAmount = '2.59';
        $record->breakdown[1] = new BreakdownDetails();
        $record->breakdown[1]->taxType = TaxType::IVA;
        $record->breakdown[1]->regimeType = RegimeType::C01;
        $record->breakdown[1]->operationType = OperationType::S1;
        $record->breakdown[1]->taxRate = '10.00';
        $record->breakdown[1]->baseAmount = '543.21';
        $record->breakdown[1]->taxAmount = '54.31'; // off by 0.01
        $record->totalTaxAmount = '56.90';
        $record->totalAmount = '612.45';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-01T20:30:40+02:00');

        // Should pass validation
        $record->hash = $record->calculateHash();
        $record->validate();

        // Validate total tax amount
        $record->totalTaxAmount = '56.91';
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for total tax amount validation');
        } catch (InvalidModelException $e) {
            // Expected, ignore
        }
        $record->totalTaxAmount = '56.90';

        // Validate total amount (allows minor differences)
        $record->totalAmount = '612.44';
        $record->hash = $record->calculateHash();
        $record->validate();

        // Validate total amount (throws exception)
        $record->totalAmount = '1.23';
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for total tax amount validation');
        } catch (InvalidModelException $e) {
            // Expected, ignore
        }
    }

    #[DoesNotPerformAssertions]
    public function testValidatesRecipients(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'TEST';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->invoiceType = InvoiceType::Factura;
        $record->description = 'Factura simplificada de prueba';
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::S1;
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxAmount = '2.10';
        $record->totalTaxAmount = '2.10';
        $record->totalAmount = '12.10';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-01T20:30:40+02:00');

        // Missing mandatory recipient for invoice
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for missing recipient validation');
        } catch (InvalidModelException $e) {
            // Expected, ignore
        }

        // Should pass validation with Spanish identifiers
        $record->recipients[0] = new FiscalIdentifier('Antonio García Pérez', '00000000A');
        $record->hash = $record->calculateHash();
        $record->validate();

        // Should pass validation with foreign identifiers
        $record->recipients[1] = new ForeignFiscalIdentifier();
        $record->recipients[1]->name = 'Another Company';
        $record->recipients[1]->country = 'PT';
        $record->recipients[1]->type = ForeignIdType::VAT;
        $record->recipients[1]->value = 'PT999999999';
        $record->hash = $record->calculateHash();
        $record->validate();
    }

    public function testValidatesCorrectiveType(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'RECT-0001';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->description = 'Factura rectificativa de prueba';
        $record->recipients[0] = new FiscalIdentifier('Antonio García Pérez', '00000000A');
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::S1;
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxAmount = '2.10';
        $record->totalTaxAmount = '2.10';
        $record->totalAmount = '12.10';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-01T20:30:40+02:00');

        // Missing corrective type
        $record->invoiceType = InvoiceType::R1;
        $record->correctiveType = null;
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for missing corrective type');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Missing type for corrective invoice', $e->getMessage());
        }

        // Unnecessary corrective type
        $record->invoiceType = InvoiceType::Factura;
        $record->correctiveType = CorrectiveType::Substitution;
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for unnecessary corrective type');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('This type of invoice cannot have a corrective type', $e->getMessage());
        }

        // Throws no exception
        $record->invoiceType = InvoiceType::R2;
        $record->correctiveType = CorrectiveType::Substitution;
        $record->hash = $record->calculateHash();
        $record->validate();
    }
}
