<?php
namespace josemmo\Verifactu\Tests\Services;

use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Services\AeatClient;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;

final class AeatClientTest extends TestCase {
    /**
     * Get mocked AEAT client
     *
     * @param Response|ClientExceptionInterface $response Mocked response
     *
     * @return AeatClient AEAT client instance
     */
    private function getMockedClient(Response|ClientExceptionInterface $response): AeatClient {
        // Create HTTP client mock
        $mock = new MockHandler([$response]);
        $httpClient = new Client([
            'handler' => HandlerStack::create($mock),
        ]);

        // Build computer system
        $system = new ComputerSystem();
        $system->vendorName = 'Perico de los Palotes, S.A.';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test SIF';
        $system->id = 'XX';
        $system->version = '0.0.1';
        $system->installationNumber = 'ABC0123';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = true;
        $system->hasMultipleTaxpayers = false;
        $system->validate();

        // Build AEAT client
        $taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');
        $client = new AeatClient($system, $taxpayer, $httpClient);

        return $client;
    }

    /**
     * Get mocked record
     *
     * @return CancellationRecord Record instance
     */
    private function getMockedRecord(): CancellationRecord {
        $record = new CancellationRecord();
        $record->invoiceId = new InvoiceIdentifier('89890001K', 'TEST123', new DateTimeImmutable('2025-12-10'));
        $record->previousInvoiceId = new InvoiceIdentifier('89890001K', 'TEST122', new DateTimeImmutable('2025-12-08'));
        $record->previousHash = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $record->hashedAt = new DateTimeImmutable();
        $record->hash = $record->calculateHash();
        $record->validate();
        return $record;
    }

    public function testThrowsExceptionForMalformedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Failed to parse XML response');
        $client = $this->getMockedClient(new Response(200, [], '<element>Malformed XML</notClosingElement>'));
        $record = $this->getMockedRecord();
        $client->send([$record])->wait();
    }

    public function testThrowsExceptionForUnexpectedXmlResponse(): void {
        $this->expectException(AeatException::class);
        $this->expectExceptionMessage('Missing <tikR:RespuestaRegFactuSistemaFacturacion /> element from response');
        $client = $this->getMockedClient(new Response(401, [], '<html><body>Unauthorized</body></html>'));
        $record = $this->getMockedRecord();
        $client->send([$record])->wait();
    }

    public function testThrowsExceptionOnConnectionError(): void {
        $this->expectException(ConnectException::class);
        $this->expectExceptionMessage('Exception message');
        $client = $this->getMockedClient(new ConnectException('Exception message', new Request('GET', 'test')));
        $record = $this->getMockedRecord();
        $client->send([$record])->wait();
    }
}
