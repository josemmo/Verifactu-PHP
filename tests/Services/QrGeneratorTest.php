<?php
namespace josemmo\Verifactu\Tests\Services;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Services\QrGenerator;
use PHPUnit\Framework\TestCase;

final class QrGeneratorTest extends TestCase {
    public function testGeneratesLinksFromRawParameters(): void {
        $service = new QrGenerator();
        $this->assertEquals(
            'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=A86018322&numserie=FACT001&fecha=01-10-2025&importe=100.23',
            $service->from('A86018322', 'FACT001', new DateTimeImmutable('2025-10-01'), '100.23'),
        );

        $service->setOnlineMode(false);
        $this->assertEquals(
            'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu?nif=A86018322&numserie=FACT002&fecha=01-10-2025&importe=100.23',
            $service->from('A86018322', 'FACT002', new DateTimeImmutable('2025-10-01'), '100.23'),
        );

        $service->setProduction(false);
        $this->assertEquals(
            'https://prewww2.aeat.es/wlpl/TIKE-CONT/ValidarQRNoVerifactu?nif=A86018322&numserie=FACT003&fecha=01-10-2025&importe=100.23',
            $service->from('A86018322', 'FACT003', new DateTimeImmutable('2025-10-01'), '100.23'),
        );
    }

    public function testGeneratesLinksFromInvoiceIdentifier(): void {
        $service = new QrGenerator();
        $this->assertEquals(
            'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=A86018322&numserie=FACT001&fecha=01-10-2025&importe=100.23',
            $service->fromInvoiceId(new InvoiceIdentifier('A86018322', 'FACT001', new DateTimeImmutable('2025-10-01')), '100.23'),
        );
    }

    public function testGeneratesLinksFromRegistrationRecord(): void {
        $service = new QrGenerator();
        $record = new RegistrationRecord();
        $record->invoiceId = new InvoiceIdentifier('A86018322', 'FACT001', new DateTimeImmutable('2025-10-01'));
        $record->totalAmount = '100.23';
        $this->assertEquals(
            'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/ValidarQR?nif=A86018322&numserie=FACT001&fecha=01-10-2025&importe=100.23',
            $service->fromRegistrationRecord($record),
        );
    }
}
