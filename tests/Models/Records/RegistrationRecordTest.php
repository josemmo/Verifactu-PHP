<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\CorrectiveType;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignFiscalIdentifier;
use josemmo\Verifactu\Models\Records\ForeignIdType;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxRate = '21.00';
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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '100.00';
        $record->breakdown[0]->taxRate = '21.00';
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

    public function testValidatesPriorRejection(): void {
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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '100.00';
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->taxAmount = '21.00';
        $record->totalTaxAmount = '21.00';
        $record->totalAmount = '121.00';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-02T20:30:40+02:00');
        $record->hash = $record->calculateHash();

        // Should pass validation
        $record->isCorrection = true;
        $record->isPriorRejection = false;
        $record->validate();

        // Should also pass validation
        $record->isCorrection = true;
        foreach ([true, null] as $priorRejectionValue) {
            $record->isPriorRejection = $priorRejectionValue;
            $record->validate();
        }

        // Prior rejection requires correction flag
        foreach ([true, null] as $priorRejectionValue) {
            $record->isCorrection = false;
            $record->isPriorRejection = $priorRejectionValue;
            try {
                $record->validate();
                $this->fail('Did not throw exception for prior rejection validation');
            } catch (InvalidModelException $e) {
                $this->assertStringContainsString('Record cannot be a prior rejection if it is not a correction', $e->getMessage());
            }
        }
    }

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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '12.34';
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->taxAmount = '2.59';
        $record->breakdown[1] = new BreakdownDetails();
        $record->breakdown[1]->taxType = TaxType::IVA;
        $record->breakdown[1]->regimeType = RegimeType::C01;
        $record->breakdown[1]->operationType = OperationType::Subject;
        $record->breakdown[1]->baseAmount = '543.21';
        $record->breakdown[1]->taxRate = '10.00';
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
            $this->assertStringContainsString('Expected total tax amount of 56.90, got 56.91', $e->getMessage());
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
            $this->assertStringContainsString('Expected total amount of 612.45, got 1.23', $e->getMessage());
        }
    }

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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxRate = '21.00';
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
            $this->assertStringContainsString('This type of invoice requires at least one recipient', $e->getMessage());
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

    public function testValidatesCorrectiveDetails(): void {
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
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxRate = '21.00';
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

        // Valid corrective type for differences
        $record->invoiceType = InvoiceType::R2;
        $record->correctiveType = CorrectiveType::Differences;
        $record->hash = $record->calculateHash();
        $record->validate();

        // Missing corrected amounts for substitution
        $record->invoiceType = InvoiceType::R2;
        $record->correctiveType = CorrectiveType::Substitution;
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for corrected amounts for substitution');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Missing corrected base amount', $e->getMessage());
        }

        // Valid corrected amounts
        $record->invoiceType = InvoiceType::R2;
        $record->correctiveType = CorrectiveType::Substitution;
        $record->correctedBaseAmount = '100.00';
        $record->correctedTaxAmount = '21.00';
        $record->hash = $record->calculateHash();
        $record->validate();

        // Unnecessary corrected invoices
        $record->invoiceType = InvoiceType::Factura;
        $record->correctiveType = null;
        $record->correctedInvoices[0] = new InvoiceIdentifier('A00000000', 'PRUEBA-0001', new DateTimeImmutable());
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for unnecessary corrected invoices');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('This type of invoice cannot have corrected invoices', $e->getMessage());
        }
    }

    public function testValidatesReplacedInvoices(): void {
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'SUST-0001';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-01');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->description = 'Factura sustitutiva de prueba';
        $record->recipients[0] = new FiscalIdentifier('Antonio García Pérez', '00000000A');
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '10.00';
        $record->breakdown[0]->taxRate = '21.00';
        $record->breakdown[0]->taxAmount = '2.10';
        $record->totalTaxAmount = '2.10';
        $record->totalAmount = '12.10';
        $record->previousInvoiceId = null;
        $record->previousHash = null;
        $record->hashedAt = new DateTimeImmutable('2025-06-01T20:30:40+02:00');

        // Unnecessary replaced invoices
        $record->invoiceType = InvoiceType::Factura;
        $record->replacedInvoices[] = new InvoiceIdentifier('A00000000', 'PRUEBA-0001', new DateTimeImmutable());
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for unnecessary replaced invoices');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('This type of invoice cannot have replaced invoices', $e->getMessage());
        }

        // Valid invoice type
        $record->invoiceType = InvoiceType::Sustitutiva;
        $record->hash = $record->calculateHash();
        $record->validate();
    }

    public function testExportsXmlElement(): void {
        // Create record
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = 'PRUEBA-0002';
        $record->invoiceId->issueDate = new DateTimeImmutable('2025-06-02');
        $record->issuerName = 'Perico de los Palotes, S.A.';
        $record->invoiceType = InvoiceType::Simplificada;
        $record->operationDate = new DateTimeImmutable('2025-05-15');
        $record->description = 'Factura simplificada de prueba';
        $record->breakdown[0] = new BreakdownDetails();
        $record->breakdown[0]->taxType = TaxType::IVA;
        $record->breakdown[0]->regimeType = RegimeType::C01;
        $record->breakdown[0]->operationType = OperationType::Subject;
        $record->breakdown[0]->baseAmount = '100.00';
        $record->breakdown[0]->taxRate = '21.00';
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
        $record->validate();

        // Build computer system
        $system = new ComputerSystem();
        $system->vendorName = 'Perico de los Palotes, S.A.';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test SIF';
        $system->id = 'TS';
        $system->version = '0.0.1';
        $system->installationNumber = '01234';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = false;
        $system->hasMultipleTaxpayers = false;
        $system->validate();

        // Export to XML
        $xml = UXML::newInstance('container', null, ['xmlns:sum1' => Record::NS]);
        $record->export($xml, $system);
        $expectedXml = TestUtils::getXmlFile(__DIR__ . '/registration-record-example.xml');
        $this->assertXmlStringEqualsXmlString($expectedXml, $xml->get('sum1:RegistroAlta')?->asXML() ?? '');
    }
}
