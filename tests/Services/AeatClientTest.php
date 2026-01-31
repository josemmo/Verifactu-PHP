<?php
namespace josemmo\Verifactu\Tests\Services;

use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\RemisionRequerimiento;
use josemmo\Verifactu\Services\AeatClient;
use PHPUnit\Framework\TestCase;
use UXML\UXML;

final class AeatClientTest extends TestCase {
    private function createAeatClient(): AeatClient {
        $system = new ComputerSystem();
        $system->vendorName = 'Test Vendor';
        $system->vendorNif = 'A00000000';
        $system->name = 'Test System';
        $system->id = '01';
        $system->version = '1.0';
        $system->installationNumber = 'INST001';
        $system->onlySupportsVerifactu = true;
        $system->supportsMultipleTaxpayers = false;
        $system->hasMultipleTaxpayers = false;

        $taxpayer = new FiscalIdentifier('Test Taxpayer', 'B00000000');

        return new AeatClient($system, $taxpayer);
    }

    /**
     * Build XML structure and return the Cabecera element with RemisionRequerimiento added if set
     *
     * @param AeatClient $client Client instance
     *
     * @return UXML Cabecera element
     */
    private function buildCabeceraXml(AeatClient $client): UXML {
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => AeatClient::NS_SOAPENV,
            'xmlns:sum' => AeatClient::NS_AEAT,
            'xmlns:sum1' => \josemmo\Verifactu\Models\Records\Record::NS,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');

        $reflection = new \ReflectionClass($client);
        $taxpayerProperty = $reflection->getProperty('taxpayer');
        $taxpayerProperty->setAccessible(true);
        $taxpayer = $taxpayerProperty->getValue($client);

        $obligadoEmisionElement->add('sum1:NombreRazon', $taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $taxpayer->nif);

        $remisionProperty = $reflection->getProperty('remisionRequerimiento');
        $remisionProperty->setAccessible(true);
        $remisionRequerimiento = $remisionProperty->getValue($client);

        if ($remisionRequerimiento !== null) {
            $remisionRequerimientoElement = $cabeceraElement->add('sum1:RemisionRequerimiento');
            $remisionRequerimientoElement->add('sum1:RefRequerimiento', $remisionRequerimiento->requirementReference);
            $remisionRequerimientoElement->add('sum1:FinRequerimiento', $remisionRequerimiento->isRequirementEnd ? 'S' : 'N');
        }

        return $cabeceraElement;
    }

    public function testRemisionRequerimientoWithNullIsRequirementEnd(): void {
        $client = $this->createAeatClient();
        $remision = new RemisionRequerimiento('REF123', null);
        $client->setRemisionRequerimiento($remision);

        $cabeceraElement = $this->buildCabeceraXml($client);

        $remisionElement = $cabeceraElement->get('sum1:RemisionRequerimiento');
        $this->assertNotNull($remisionElement, 'RemisionRequerimiento element should exist');

        $refElement = $remisionElement->get('sum1:RefRequerimiento');
        $this->assertNotNull($refElement, 'RefRequerimiento element should exist');
        $this->assertEquals('REF123', $refElement->asText());

        $finElement = $remisionElement->get('sum1:FinRequerimiento');
        $this->assertNotNull($finElement, 'FinRequerimiento element should exist');
        $this->assertEquals('N', $finElement->asText(), 'FinRequerimiento should be "N" when isRequirementEnd is null');
    }

    public function testRemisionRequerimientoWithFalseIsRequirementEnd(): void {
        $client = $this->createAeatClient();
        $remision = new RemisionRequerimiento('REF456', false);
        $client->setRemisionRequerimiento($remision);

        $cabeceraElement = $this->buildCabeceraXml($client);

        $remisionElement = $cabeceraElement->get('sum1:RemisionRequerimiento');
        $this->assertNotNull($remisionElement, 'RemisionRequerimiento element should exist');

        $refElement = $remisionElement->get('sum1:RefRequerimiento');
        $this->assertNotNull($refElement, 'RefRequerimiento element should exist');
        $this->assertEquals('REF456', $refElement->asText());

        $finElement = $remisionElement->get('sum1:FinRequerimiento');
        $this->assertNotNull($finElement, 'FinRequerimiento element should exist');
        $this->assertEquals('N', $finElement->asText(), 'FinRequerimiento should be "N" when isRequirementEnd is false');
    }

    public function testRemisionRequerimientoWithTrueIsRequirementEnd(): void {
        $client = $this->createAeatClient();
        $remision = new RemisionRequerimiento('REF789', true);
        $client->setRemisionRequerimiento($remision);

        $cabeceraElement = $this->buildCabeceraXml($client);

        $remisionElement = $cabeceraElement->get('sum1:RemisionRequerimiento');
        $this->assertNotNull($remisionElement, 'RemisionRequerimiento element should exist');

        $refElement = $remisionElement->get('sum1:RefRequerimiento');
        $this->assertNotNull($refElement, 'RefRequerimiento element should exist');
        $this->assertEquals('REF789', $refElement->asText());

        $finElement = $remisionElement->get('sum1:FinRequerimiento');
        $this->assertNotNull($finElement, 'FinRequerimiento element should exist');
        $this->assertEquals('S', $finElement->asText(), 'FinRequerimiento should be "S" when isRequirementEnd is true');
    }

    public function testRemisionRequerimientoNotAddedWhenNotSet(): void {
        $client = $this->createAeatClient();

        $cabeceraElement = $this->buildCabeceraXml($client);

        // Verify RemisionRequerimiento is not added
        $remisionElement = $cabeceraElement->get('sum1:RemisionRequerimiento');
        $this->assertNull($remisionElement, 'RemisionRequerimiento element should not exist when not set');
    }
}
