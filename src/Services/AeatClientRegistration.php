<?php
namespace josemmo\Verifactu\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\PromiseInterface;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Models\Responses\AeatResponse;
use Psr\Http\Client\ClientExceptionInterface;
use Symfony\Component\Validator\Constraints as Assert;
use UXML\UXML;

/**
 * Class to communicate with the AEAT web service endpoint for VERI*FACTU
 */
class AeatClientRegistration extends AeatClient {
    /** Client XML namespace */
    public const NS_AEAT = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroLR.xsd';

    private readonly FiscalIdentifier $taxpayer;
    private ?FiscalIdentifier $representative = null;

    /**
     * Registros a enviar
     *
     * @var array<Record>
     *
     * @field Records
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, max: 1000)]
    public array $records = [];

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
        parent::__construct($system, $httpClient);
        $this->taxpayer = $taxpayer;
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
     * Builds the XML body of the request
     *
     * @return UXML XML encoded request
     */
    public function createBody(): UXML {
        $xml = UXML::newInstance('soapenv:Envelope', null, [
            'xmlns:soapenv' => self::NS_SOAPENV,
            'xmlns:sum' => self::NS_AEAT,
            'xmlns:sum1' => Record::NS,
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
        foreach ($this->records as $record) {
            $record->export($baseElement->add('sum:RegistroFactura'), $this->system);
        }

        return $xml;
    }

    /**
     * Send invoicing records
     *
     * @param null|UXML|array<Record> $body Request already generated or array of records to be sent
     *
     * @return PromiseInterface<AeatResponse> Response from service
     *
     * @throws AeatException            if AEAT server returned an error
     * @throws ClientExceptionInterface if request sending failed
     */
    public function send(null|UXML|array $body = null): PromiseInterface { /** @phpstan-ignore generics.notGeneric */
        if (is_array($body)) {
            $this->records = $body;
            $body = null;
        }

        // Send request
        return $this->sendRequest($body)
            ->then(fn (UXML $xml): AeatResponse => AeatResponse::from($xml));
    }
}
