<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use InvalidArgumentException;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use Psr\Http\Message\ResponseInterface;
use SensitiveParameter;
use UXML\UXML;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
abstract class AeatClient {
    /** SOAP envelope XML namespace */
    public const NS_SOAPENV = 'http://schemas.xmlsoap.org/soap/envelope/';

    protected readonly ComputerSystem $system;
    protected readonly Client $client;
    private ?string $certificatePath = null;
    private ?string $certificatePassword = null;
    private bool $isProduction = true;
    private bool $isEntitySeal = false;

    /**
     * Builds the XML body for the request
     *
     * @return UXML XML encoded request
     */
    abstract public function createBody(): UXML;

    /**
     * Send the request to AEAT
     *
     * @return PromiseInterface Response from service
     */
    abstract public function send(): PromiseInterface;

    /**
     * Class constructor
     *
     * @param ComputerSystem $system     Computer system details
     * @param Client|null    $httpClient Custom HTTP client, leave empty to create a new one
     */
    public function __construct(
        ComputerSystem $system,
        ?Client $httpClient = null,
    ) {
        $this->system = $system;
        $this->client = $httpClient ?? new Client();
    }

    /**
     * Get base URI of web service
     *
     * @return string Base URI
     */
    protected function getBaseUri(): string {
        if ($this->isEntitySeal) {
            return $this->isProduction ? 'https://www10.agenciatributaria.gob.es' : 'https://prewww10.aeat.es';
        }
        return $this->isProduction ? 'https://www1.agenciatributaria.gob.es' : 'https://prewww1.aeat.es';
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
     * Set entity seal
     *
     * @param bool $entitySeal Pass `true` for entity seal certificate, `false` for regular certificate
     *
     * @return $this This instance
     */
    public function setEntitySeal(bool $entitySeal): static {
        $this->isEntitySeal = $entitySeal;
        return $this;
    }

    /**
     * Builds the options for the requets
     *
     * @param ?UXML $body Already generated body
     *
     * @return array<string, mixed> Request options
     */
    protected function createOptions(?UXML $body = null): array {
        $options = [
            'base_uri' => $this->getBaseUri(),
            'http_errors' => false,
            'headers' => [
                'Content-Type' => 'text/xml',
                'User-Agent' => "Mozilla/5.0 (compatible; {$this->system->name}/{$this->system->version})",
            ],
            'body' => $body ?? $this->createBody()->asXML(),
        ];
        if ($this->certificatePath !== null) {
            $options['cert'] = ($this->certificatePassword === null) ?
                $this->certificatePath :
                [$this->certificatePath, $this->certificatePassword];
        }

        return $options;
    }

    /**
     * Send the request without casting the reponse
     *
     * @param ?UXML $body Already generated body
     *
     * @return PromiseInterface<UXML> Response from service
     */
    public function sendRequest(?UXML $body = null): PromiseInterface { /** @phpstan-ignore generics.notGeneric */
        return $this->client->postAsync(
            '/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
            $this->createOptions($body)
        )
            ->then(fn (ResponseInterface $response): string => $response->getBody()->getContents())
            ->then(function (string $response): UXML {
                try {
                    return UXML::fromString($response);
                } catch (InvalidArgumentException $e) {
                    throw new AeatException('Failed to parse XML response', previous: $e);
                }
            });
    }
}
