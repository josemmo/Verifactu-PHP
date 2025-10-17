<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use UXML\UXML;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
class AeatClient {
    public const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const NS_SUM = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';
    public const NS_SUM1 = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    private readonly ComputerSystem $system;
    private readonly FiscalIdentifier $taxpayer;
    private ?FiscalIdentifier $representative = null;
    private readonly Client $client;
    private bool $isProduction = true;

    /**
     * Class constructor
     *
     * NOTE: The certificate path must have the ".p12" extension to be recognized as a PFX bundle.
     *
     * @param ComputerSystem   $system       Computer system details
     * @param FiscalIdentifier $taxpayer     Taxpayer details (party that issues the invoices)
     * @param string           $certPath     Path to encrypted PEM certificate or PKCS#12 (PFX) bundle
     * @param string|null      $certPassword Certificate password or `null` for none
     */
    public function __construct(
        ComputerSystem $system,
        FiscalIdentifier $taxpayer,
        string $certPath,
        ?string $certPassword = null,
    ) {
        $this->system = $system;
        $this->taxpayer = $taxpayer;
        $this->client = new Client([
            'cert' => ($certPassword === null) ? $certPath : [$certPath, $certPassword],
            'headers' => [
                'User-Agent' => "Mozilla/5.0 (compatible; {$system->name}/{$system->version})",
            ],
        ]);
    }

    /**
     * Set representative
     *
     * NOTE: Requires the represented fiscal entity to fill the "GENERALLEY58" form at AEAT.
     *
     * @param FiscalIdentifier|null $representative Representative details (party that sends the invoices)
     *
     * @return $this This instance
     */
    public function setRepresentative(?FiscalIdentifier $representative): static {
        $this->representative = $representative;
        return $this;
    }

    /**
     * Set production environment
     *
     * @param bool $production Pass `true` for production, `false` for testing
     *
     * @return $this This instance
     */
    public function setProduction(bool $production): static {
        $this->isProduction = $production;
        return $this;
    }

    /**
     * Send invoicing records
     *
     * @param (RegistrationRecord|CancellationRecord)[] $records Invoicing records
     *
     * @return AeatResponse Response from service
     *
     * @throws GuzzleException if request failed
     */
    public function send(array $records): AeatResponse {
        // Build initial request
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum' => self::NS_SUM,
            'xmlns:sum1' => self::NS_SUM1,
        ]);
        $xml->add('soapenv:Header');
        $baseElement = $xml->add('soapenv:Body')->add('sum:RegFactuSistemaFacturacion');

        // Add header
        $cabeceraElement = $baseElement->add('sum:Cabecera');
        $obligadoEmisionElement = $cabeceraElement->add('sum1:ObligadoEmision');
        $obligadoEmisionElement->add('sum1:NombreRazon', $this->taxpayer->name);
        $obligadoEmisionElement->add('sum1:NIF', $this->taxpayer->nif);
        if ($this->representative !== null) {
            $representanteElement = $cabeceraElement->add('sum1:Representante');
            $representanteElement->add('sum1:NombreRazon', $this->representative->name);
            $representanteElement->add('sum1:NIF', $this->representative->nif);
        }

        // Add registration records
        foreach ($records as $record) {
            $record->export($baseElement->add('sum:RegistroFactura'), $this->system);
        }

        // Send request
        $response = $this->client->post('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', [
            'base_uri' => $this->getBaseUri(),
            'headers' => [
                'Content-Type' => 'text/xml',
            ],
            'body' => $xml->asXML(),
        ]);

        // Parse and return response
        $xmlResponse = UXML::fromString($response->getBody()->getContents());
        return AeatResponse::from($xmlResponse);
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    private function getBaseUri(): string {
        return $this->isProduction ? 'https://www1.agenciatributaria.gob.es' : 'https://prewww1.aeat.es';
    }
}
