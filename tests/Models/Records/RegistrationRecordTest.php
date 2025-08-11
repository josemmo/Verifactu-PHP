<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;

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
}
