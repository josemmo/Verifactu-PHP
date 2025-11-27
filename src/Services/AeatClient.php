<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\CancellationRecord;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
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
    private readonly Client $client;
    private ?string $certificatePath = null;
    private ?string $certificatePassword = null;
    private ?FiscalIdentifier $representative = null;
    private bool $isProduction = true;

    /**
     * Class constructor
     *
     * @param ComputerSystem   $system     Computer system details
     * @param FiscalIdentifier $taxpayer   Taxpayer details (party that issues the invoices)
     * @param Client|null      $httpClient Custom HTTP client, leave empty to create a new one
     */
    public function __construct(
        ComputerSystem $system,
        FiscalIdentifier $taxpayer,
        ?Client $httpClient = null,
    ) {
        $this->system = $system;
        $this->taxpayer = $taxpayer;
        $this->client = $httpClient ?? new Client();
    }

    /**
     * Set certificate
     *
     * NOTE: The certificate path must have the ".p12" extension to be recognized as a PFX bundle.
     *
     * @param string      $certificatePath     Path to encrypted PEM certificate or PKCS#12 (PFX) bundle
     * @param string|null $certificatePassword Certificate password or `null` for none
     *
     * @return $this This instance
     */
    public function setCertificate(
        #[SensitiveParameter] string $certificatePath,
        #[SensitiveParameter] ?string $certificatePassword = null,
    ): static {
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $certificatePassword;
        return $this;
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
     * @return PromiseInterface<AeatResponse> Response from service
     *
     * @throws AeatException   if AEAT server returned an error
     * @throws GuzzleException if request sending failed
     */
    public function send(array $records): PromiseInterface { /** @phpstan-ignore generics.notGeneric */
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
        $options = [
            'base_uri' => $this->getBaseUri(),
            'headers' => [
                'Content-Type' => 'text/xml',
                'User-Agent' => "Mozilla/5.0 (compatible; {$this->system->name}/{$this->system->version})",
            ],
            'body' => $xml->asXML(),
        ];
        if ($this->certificatePath !== null) {
            $options['cert'] = ($this->certificatePassword === null) ?
                $this->certificatePath :
                [$this->certificatePath, $this->certificatePassword];
        }
        $responsePromise = $this->client->postAsync('/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP', $options);

        // Parse and return response
        return $responsePromise
            ->then(fn (ResponseInterface $response): string => $response->getBody()->getContents())
            ->then(fn (string $response): UXML => UXML::fromString($response))
            ->then(fn (UXML $xml): AeatResponse => AeatResponse::from($xml));
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    protected function getBaseUri(): string {
        if ($this->isProduction) {
            return 'https://www1.agenciatributaria.gob.es';
        }

        // Development environment: Entity Seal certificates use prewww10, others use prewww1
        return $this->isEntitySealCertificate() ? 'https://prewww10.aeat.es' : 'https://prewww1.aeat.es';
    }

    /**
     * Detect if the current certificate is an Entity Seal (Sello de Entidad)
     * 1. check for "CSE" (Certificado Sello Electrónico) in Common Name
     * 2. check for "Sello" in Common Name or Organizational Unit
     * 3. heuristic: Organization present, but NO personal name (Given Name / Surname)
     *
     * @return bool
     */
    private function isEntitySealCertificate(): bool {
        if ($this->certificatePath === null || $this->certificatePath === '' || !file_exists($this->certificatePath)) {
            return false;
        }

        $content = file_get_contents($this->certificatePath);
        if ($content === false) {
            return false;
        }

        // Try to parse as X.509 (PEM)
        $cert = openssl_x509_parse($content);

        // If failed, try reading as P12 (just in case, though usually receives PEM)
        if ($cert === false && $this->certificatePassword !== null && $this->certificatePassword !== '') {
            $certs = [];
            if (openssl_pkcs12_read($content, $certs, $this->certificatePassword)) {
                $cert = openssl_x509_parse($certs['cert']);
            }
        }

        if ($cert === false) {
            return false;
        }

        if (!isset($cert['subject']) || !is_array($cert['subject'])) {
            return false;
        }

        $subject = $cert['subject'];

        // 1. Check for "CSE" (Certificado Sello Electrónico) in Common Name
        if (isset($subject['CN']) && is_string($subject['CN']) && stripos($subject['CN'], 'CSE ') !== false) {
            return true;
        }

        // 2. Check for "Sello" in Common Name or Organizational Unit
        if (isset($subject['OU']) && is_string($subject['OU']) && stripos($subject['OU'], 'Sello') !== false) {
            return true;
        }
        if (isset($subject['CN']) && is_string($subject['CN']) && stripos($subject['CN'], 'Sello') !== false) {
            return true;
        }

        // 3. Heuristic: Organization present, but NO personal name (Given Name / Surname)
        // Personal certificates (including Representative) usually have GN/SN or givenName/surname
        $hasOrganization = isset($subject['O']) || isset($subject['organizationIdentifier']);
        $hasPersonName = isset($subject['givenName']) || isset($subject['surname']) || isset($subject['GN']) || isset($subject['SN']);

        return $hasOrganization && !$hasPersonName;
    }
}
