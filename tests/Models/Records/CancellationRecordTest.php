<?php
namespace josemmo\Verifactu\Tests\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\InvalidModelException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
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
        $xml = UXML::newInstance('container');
        $record->export($xml, $system);
        $this->assertXmlStringEqualsXmlString(<<<XML
        <?xml version="1.0" encoding="UTF-8"?>
        <container>
            <sum1:RegistroAnulacion>
                <sum1:IDVersion>1.0</sum1:IDVersion>
                <sum1:IDFactura>
                    <sum1:IDEmisorFacturaAnulada>A00000000</sum1:IDEmisorFacturaAnulada>
                    <sum1:NumSerieFacturaAnulada>12345679/G34</sum1:NumSerieFacturaAnulada>
                    <sum1:FechaExpedicionFacturaAnulada>01-01-2024</sum1:FechaExpedicionFacturaAnulada>
                </sum1:IDFactura>
                <sum1:RechazoPrevio>S</sum1:RechazoPrevio>
                <sum1:Encadenamiento>
                    <sum1:RegistroAnterior>
                        <sum1:IDEmisorFactura>A00000000</sum1:IDEmisorFactura>
                        <sum1:NumSerieFactura>12345679/G34</sum1:NumSerieFactura>
                        <sum1:FechaExpedicionFactura>01-01-2024</sum1:FechaExpedicionFactura>
                        <sum1:Huella>F7B94CFD8924EDFF273501B01EE5153E4CE8F259766F88CF6ACB8935802A2B97</sum1:Huella>
                    </sum1:RegistroAnterior>
                </sum1:Encadenamiento>
                <sum1:SistemaInformatico>
                    <sum1:NombreRazon>Perico de los Palotes, S.A.</sum1:NombreRazon>
                    <sum1:NIF>A00000000</sum1:NIF>
                    <sum1:NombreSistemaInformatico>Test SIF</sum1:NombreSistemaInformatico>
                    <sum1:IdSistemaInformatico>TS</sum1:IdSistemaInformatico>
                    <sum1:Version>0.0.1</sum1:Version>
                    <sum1:NumeroInstalacion>01234</sum1:NumeroInstalacion>
                    <sum1:TipoUsoPosibleSoloVerifactu>S</sum1:TipoUsoPosibleSoloVerifactu>
                    <sum1:TipoUsoPosibleMultiOT>N</sum1:TipoUsoPosibleMultiOT>
                    <sum1:IndicadorMultiplesOT>N</sum1:IndicadorMultiplesOT>
                </sum1:SistemaInformatico>
                <sum1:FechaHoraHusoGenRegistro>2024-01-01T19:20:40+01:00</sum1:FechaHoraHusoGenRegistro>
                <sum1:TipoHuella>01</sum1:TipoHuella>
                <sum1:Huella>5DCAFD630E24AA03BCE2D3E6F595BAE802555F4604AF830F0340F3338B4935F6</sum1:Huella>
            </sum1:RegistroAnulacion>
        </container>
        XML, $xml->asXML());
    }
}
