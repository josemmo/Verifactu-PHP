<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\ImportException;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use UXML\UXML;

/**
 * Identificador de factura
 */
class InvoiceIdentifier extends Model {
    /**
     * Class constructor
     *
     * @param string|null            $issuerId      Issuer ID
     * @param string|null            $invoiceNumber Invoice number
     * @param DateTimeImmutable|null $issueDate     Issue date
     */
    public function __construct(
        ?string $issuerId = null,
        ?string $invoiceNumber = null,
        ?DateTimeImmutable $issueDate = null,
    ) {
        if ($issuerId !== null) {
            $this->issuerId = $issuerId;
        }
        if ($invoiceNumber !== null) {
            $this->invoiceNumber = $invoiceNumber;
        }
        if ($issueDate !== null) {
            $this->issueDate = $issueDate;
        }
    }

    /**
     * Número de identificación fiscal (NIF) del obligado a expedir la factura
     *
     * @field IDFactura/IDEmisorFactura
     */
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 9)]
    public string $issuerId;

    /**
     * Nº Serie + Nº Factura que identifica a la factura emitida
     *
     * @field IDFactura/NumSerieFactura
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 60)]
    public string $invoiceNumber;

    /**
     * Fecha de expedición de la factura
     *
     * NOTE: Time part will be ignored.
     *
     * @field IDFactura/FechaExpedicionFactura
     */
    #[Assert\NotBlank]
    public DateTimeImmutable $issueDate;

    /**
     * Import instance from XML element
     *
     * @param UXML $xml XML element
     *
     * @return self New invoice identifier instance
     *
     * @throws ImportException if failed to parse XML
     */
    public static function fromXml(UXML $xml): self {
        $model = new self();

        // Issuer ID
        $issuerIdElement = $xml->get('sum1:IDEmisorFactura') ?? $xml->get('sum1:IdEmisorFacturaAnulada');
        if ($issuerIdElement === null) {
            throw new ImportException('Missing <sum1:IDEmisorFactura /> element');
        }
        $model->issuerId = $issuerIdElement->asText();

        // Invoice number
        $invoiceNumberElement = $xml->get('sum1:NumSerieFactura') ?? $xml->get('sum1:NumSerieFacturaAnulada');
        if ($invoiceNumberElement === null) {
            throw new ImportException('Missing <sum1:NumSerieFactura /> element');
        }
        $model->invoiceNumber = $invoiceNumberElement->asText();

        // Issue date
        $issueDateElement = $xml->get('sum1:FechaExpedicionFactura') ?? $xml->get('sum1:FechaExpedicionFacturaAnulada');
        if ($issueDateElement === null) {
            throw new ImportException('Missing <sum1:FechaExpedicionFactura /> element');
        }
        $issueDate = DateTimeImmutable::createFromFormat('d-m-Y', $issueDateElement->asText());
        if ($issueDate === false) {
            throw new ImportException('Invalid issue date');
        }
        $model->issueDate = $issueDate->setTime(0, 0, 0, 0);

        return $model;
    }

    /**
     * Export model to XML
     *
     * NOTE: Writes properties directly to the provided XML element, without creating a child node to wrap them.
     * This is done as invoice identifiers appear in several different XML nodes.
     *
     * @param UXML    $xml            XML element
     * @param boolean $isCancellation Whether to add cancellation suffix to properties
     */
    public function export(UXML $xml, bool $isCancellation): void {
        $suffix = $isCancellation ? 'Anulada' : '';
        $xml->add("sum1:IDEmisorFactura$suffix", $this->issuerId);
        $xml->add("sum1:NumSerieFactura$suffix", $this->invoiceNumber);
        $xml->add("sum1:FechaExpedicionFactura$suffix", $this->issueDate->format('d-m-Y'));
    }

    /**
     * Compare instance against another invoice identifier
     *
     * @param InvoiceIdentifier $other Other invoice identifier
     *
     * @return boolean Whether instances are equal
     */
    public function equals(InvoiceIdentifier $other): bool {
        return $this->issuerId === $other->issuerId
            && $this->invoiceNumber === $other->invoiceNumber
            && $this->issueDate->format('Y-m-d') === $other->issueDate->format('Y-m-d');
    }
}
