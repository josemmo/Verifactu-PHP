<?php
namespace josemmo\Verifactu\Models\Responses;

use DateTimeImmutable;
use DateTimeInterface;
use josemmo\Verifactu\Exceptions\AeatException;
use josemmo\Verifactu\Models\Model;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\Record;
use josemmo\Verifactu\Services\AeatClient;
use Symfony\Component\Validator\Constraints as Assert;
use UXML\UXML;

/**
 * Response from AEAT server
 *
 * @field RespuestaBaseType
 */
class AeatResponse extends Model {
    /** Response XML namespace */
    public const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/RespuestaSuministro.xsd';

    /**
     * Create new instance from XML response
     *
     * @param UXML $xml Raw XML response
     *
     * @return AeatResponse Parsed response
     *
     * @throws AeatException if server returned an error or failed to parse response
     */
    public static function from(UXML $xml): self {
        $nsEnv = AeatClient::NS_SOAPENV;
        $nsTikr = self::NS;
        $nsTik = Record::NS;
        $instance = new self();

        // Handle server errors
        $faultElement = $xml->get("{{$nsEnv}}Body/{{$nsEnv}}Fault/faultstring");
        if ($faultElement !== null) {
            throw new AeatException($faultElement->asText());
        }

        // Get root XML element
        $rootXml = $xml->get("{{$nsEnv}}Body/{{$nsTikr}}RespuestaRegFactuSistemaFacturacion");
        if ($rootXml === null) {
            throw new AeatException('Missing <tikR:RespuestaRegFactuSistemaFacturacion /> element from response');
        }

        // Parse CSV
        $csvElement = $rootXml->get("{{$nsTikr}}CSV");
        if ($csvElement !== null) {
            $instance->csv = $csvElement->asText();
        }

        // Parse submitted at timestamp
        $submittedAtElement = $rootXml->get("{{$nsTikr}}DatosPresentacion/{{$nsTik}}TimestampPresentacion");
        if ($submittedAtElement !== null) {
            $submittedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $submittedAtElement->asText());
            if ($submittedAt === false) {
                throw new AeatException('Invalid submitted at date: ' . $submittedAtElement->asText());
            }
            $instance->submittedAt = $submittedAt;
        }

        // Parse wait seconds
        $waitSecondsElement = $rootXml->get("{{$nsTikr}}TiempoEsperaEnvio");
        if ($waitSecondsElement !== null) {
            $instance->waitSeconds = (int) $waitSecondsElement->asText();
        }

        // Parse status
        $statusElement = $rootXml->get("{{$nsTikr}}EstadoEnvio");
        if ($statusElement !== null) {
            $instance->status = ResponseStatus::from($statusElement->asText());
        }

        // Parse items
        foreach ($rootXml->getAll("{{$nsTikr}}RespuestaLinea") as $itemElement) {
            $item = new ResponseItem();
            $item->invoiceId = new InvoiceIdentifier();

            // Parse issuer ID
            $issuerIdElement = $itemElement->get("{{$nsTikr}}IDFactura/{{$nsTik}}IDEmisorFactura");
            if ($issuerIdElement !== null) {
                $item->invoiceId->issuerId = $issuerIdElement->asText();
            }

            // Parse invoice number
            $invoiceNumberElement = $itemElement->get("{{$nsTikr}}IDFactura/{{$nsTik}}NumSerieFactura");
            if ($invoiceNumberElement !== null) {
                $item->invoiceId->invoiceNumber = $invoiceNumberElement->asText();
            }

            // Parse issue date
            $issueDateElement = $itemElement->get("{{$nsTikr}}IDFactura/{{$nsTik}}FechaExpedicionFactura");
            if ($issueDateElement !== null) {
                $issueDate = DateTimeImmutable::createFromFormat('d-m-Y', $issueDateElement->asText());
                if ($issueDate === false) {
                    throw new AeatException('Invalid invoice issue date: ' . $issueDateElement->asText());
                }
                $item->invoiceId->issueDate = $issueDate->setTime(0, 0, 0, 0);
            }

            // Parse record type
            $recordTypeElement = $itemElement->get("{{$nsTikr}}Operacion/{{$nsTik}}TipoOperacion");
            if ($recordTypeElement !== null) {
                $item->recordType = RecordType::from($recordTypeElement->asText());
            }

            // Parse is correction
            $isCorrectionElement = $itemElement->get("{{$nsTikr}}Operacion/{{$nsTik}}Subsanacion");
            if ($isCorrectionElement !== null) {
                $item->isCorrection = ($isCorrectionElement->asText() === 'S');
            }

            // Parse status
            $statusElement = $itemElement->get("{{$nsTikr}}EstadoRegistro");
            if ($statusElement !== null) {
                $item->status = ItemStatus::from($statusElement->asText());
            }

            // Parse error code
            $errorCodeElement = $itemElement->get("{{$nsTikr}}CodigoErrorRegistro");
            if ($errorCodeElement !== null) {
                $item->errorCode = $errorCodeElement->asText();
            }

            // Parse error description
            $errorDescriptionElement = $itemElement->get("{{$nsTikr}}DescripcionErrorRegistro");
            if ($errorDescriptionElement !== null) {
                $item->errorDescription = $errorDescriptionElement->asText();
            }

            $instance->items[] = $item;
        }

        // Validate and return
        $instance->validate();
        return $instance;
    }

    /**
     * CSV asociado al envío generado por AEAT
     *
     * Solo se genera si no hay rechazo del envío.
     *
     * @field CSV
     */
    public ?string $csv = null;

    /**
     * Timestamp asociado a la remisión enviada
     *
     * Solo se genera si no hay rechazo del envío.
     *
     * @field DatosPresentacion/TimestampPresentacion
     */
    public ?DateTimeImmutable $submittedAt = null;

    /**
     * Segundos de espera entre envíos
     *
     * Para poder realizar el siguiente envío, el SIF deberá esperar a que transcurran X segundos.
     *
     * @field TiempoEsperaEnvio
     */
    #[Assert\NotBlank]
    #[Assert\Positive]
    public int $waitSeconds;

    /**
     * Estado global del envío
     *
     * @field EstadoEnvio
     */
    #[Assert\NotBlank]
    public ResponseStatus $status;

    /**
     * Estado detallado de cada línea del suministro
     *
     * @var ResponseItem[]
     *
     * @field RespuestaLinea
     */
    #[Assert\Valid]
    public array $items = [];
}
