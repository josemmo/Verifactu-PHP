<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Tests\TestUtils;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class CancellationRecordTest extends TestCase {
    public function testRequiresPreviousInvoice(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = '89890001K';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = null; // This is not allowed
        $record->previousHash = null; // This is not allowed
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for missing previous invoice');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Previous invoice ID is required', $e->getMessage());
            $this->assertStringContainsString('Previous hash is required', $e->getMessage());
        }

        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = '89890001K';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->hash = $record->calculateHash();
        try {
            $record->validate();
            $this->fail('Did not throw exception for missing previous hash');
        } catch (InvalidModelException $e) {
            $this->assertStringContainsString('Previous hash is required', $e->getMessage());
        }
    }

    public function testCalculatesHashForOtherRecords(): void {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = '89890001K';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = '89890001K';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousHash = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
        $record->hash = $record->calculateHash();
        $this->assertEquals('177547C0D57AC74748561D054A9CEC14B4C4EA23D1BEFD6F2E69E3A388F90C68', $record->hash);
        $record->validate();
    }

    public function testExportsXmlElement(): void {
        // Create record
        $record = new CancellationRecord();
        $record->isPriorRejection = true;
        $record->invoiceId = new InvoiceIdentifier();
        $record->invoiceId->issuerId = 'A00000000';
        $record->invoiceId->invoiceNumber = '12345679/G34';
        $record->invoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousInvoiceId = new InvoiceIdentifier();
        $record->previousInvoiceId->issuerId = 'A00000000';
        $record->previousInvoiceId->invoiceNumber = '12345679/G34';
        $record->previousInvoiceId->issueDate = new DateTimeImmutable('2024-01-01');
        $record->previousHash = 'F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97';
        $record->hashedAt = new DateTimeImmutable('2024-01-01T19:20:40+01:00');
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
        $expectedXml = TestUtils::getXmlFile(__DIR__ . '/cancellation-record-example.xml');
        $this->assertXmlStringEqualsXmlString($expectedXml, $xml->get('sum1:RegistroAnulacion')?->asXML() ?? '');
    }

    public function testImportsAndExportsXmlElement(): void {
        // Import model
        $modelXml = TestUtils::getXmlFile(__DIR__ . '/cancellation-record-example.xml');
        $record = Record::fromXml($modelXml);
        $this->assertInstanceOf(CancellationRecord::class, $record);
        $record->validate();

        // Import computer system
        $computerSystemXml = $modelXml->get('sum1:SistemaInformatico');
        $this->assertNotNull($computerSystemXml);
        $computerSystem = ComputerSystem::fromXml($computerSystemXml);

        // Export model
        $exportedXml = UXML::newInstance('container', null, ['xmlns:sum1' => Record::NS]);
        $record->export($exportedXml, $computerSystem);
        $this->assertXmlStringEqualsXmlString($modelXml, $exportedXml->get('sum1:RegistroAnulacion')?->asXML() ?? '');
    }
}
