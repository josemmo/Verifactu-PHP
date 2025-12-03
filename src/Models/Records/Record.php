<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use DateTimeInterface;
use josemmo\Verifactu\Exceptions\ImportException;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Base invoice record
 */
abstract class Record extends Model {
    /** XML namespace */
    public const NS = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/SuministroInformacion.xsd';

    /**
     * ID de factura
     *
     * @field IDFactura
     */
    #[Assert\NotBlank]
    #[Assert\Valid]
    public InvoiceIdentifier $invoiceId;

    /**
     * ID de factura del registro anterior
     *
     * @field Encadenamiento/RegistroAnterior
     */
    #[Assert\Valid]
    public ?InvoiceIdentifier $previousInvoiceId;

    /**
     * Primeros 64 caracteres de la huella o hash del registro de facturación anterior
     *
     * @field Encadenamiento/RegistroAnterior/Huella
     */
    #[Assert\Regex(pattern: '/^[0-9A-F]{64}$/')]
    public ?string $previousHash;

    /**
     * Huella o hash de cierto contenido de este registro de facturación
     *
     * @field Huella
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9A-F]{64}$/')]
    public string $hash;

    /**
     * Fecha, hora y huso horario de generación del registro de facturación
     *
     * @field FechaHoraHusoGenRegistro
     */
    #[Assert\NotBlank]
    public DateTimeImmutable $hashedAt;

    /**
     * Get record element name
     *
     * @return string XML element name
     */
    abstract protected static function getRecordElementName(): string;

    /**
     * Import instance from XML element
     *
     * @param UXML $xml XML element
     *
     * @return RegistrationRecord|CancellationRecord Record instance
     *
     * @throws ImportException if failed to parse XML
     */
    final public static function fromXml(UXML $xml): self {
        $nodeName = $xml->element()->nodeName;
        if (!str_starts_with($nodeName, 'sum1:')) {
            throw new ImportException('Node namespace must be "sum1"');
        }
        $nodeName = mb_substr($nodeName, 5); // Strip namespace

        // Create record instance
        $record = match ($nodeName) {
            RegistrationRecord::getRecordElementName() => new RegistrationRecord(),
            CancellationRecord::getRecordElementName() => new CancellationRecord(),
            default => throw new ImportException('Unexpected node type'),
        };

        // Previous invoice and hash
        $registroAnteriorElement = $xml->get('sum1:Encadenamiento/sum1:RegistroAnterior');
        if ($registroAnteriorElement === null) {
            $record->previousInvoiceId = null;
            $record->previousHash = null;
        } else {
            $record->previousInvoiceId = InvoiceIdentifier::fromXml($registroAnteriorElement);
            $previousHash = $registroAnteriorElement->get('sum1:Huella')?->asText();
            if ($previousHash === null) {
                throw new ImportException('Missing <sum1:Huella /> from <sum1: sum1:RegistroAnterior /> element');
            }
            $record->previousHash = $previousHash;
        }

        // Custom properties
        $record->importCustomProperties($xml);

        // Hashed at
        $rawHashedAt = $xml->get('sum1:FechaHoraHusoGenRegistro')?->asText();
        if ($rawHashedAt === null) {
            throw new ImportException('Missing <sum1:FechaHoraHusoGenRegistro /> element');
        }
        $hashedAt = DateTimeImmutable::createFromFormat(DateTimeInterface::ISO8601, $rawHashedAt);
        if ($hashedAt === false) {
            throw new ImportException('Invalid value for <sum1:FechaHoraHusoGenRegistro /> element');
        }
        $record->hashedAt = $hashedAt;

        // Hash
        $hash = $xml->get('sum1:Huella')?->asText();
        if ($hash === null) {
            throw new ImportException('Missing <sum1:Huella /> element');
        }
        $record->hash = $hash;

        return $record;
    }

    /**
     * Calculate record hash
     *
     * @return string Expected record hash
     */
    abstract public function calculateHash(): string;

    #[Assert\Callback]
    final public function validateHash(ExecutionContextInterface $context): void {
        $expectedHash = $this->calculateHash();
        if ($this->hash !== $expectedHash) {
            $context->buildViolation("Invalid hash, expected value $expectedHash")
                ->atPath('hash')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validatePreviousInvoice(ExecutionContextInterface $context): void {
        if ($this->previousInvoiceId !== null && $this->previousHash === null) {
            $context->buildViolation('Previous hash is required if previous invoice ID is provided')
                ->atPath('previousHash')
                ->addViolation();
        } elseif ($this->previousHash !== null && $this->previousInvoiceId === null) {
            $context->buildViolation('Previous invoice ID is required if previous hash is provided')
                ->atPath('previousInvoiceId')
                ->addViolation();
        }
    }

    /**
     * Export record to XML
     *
     * @param UXML           $xml    XML parent element
     * @param ComputerSystem $system Computer system information
     */
    public function export(UXML $xml, ComputerSystem $system): void {
        $recordElementName = $this::class::getRecordElementName();
        $recordElement = $xml->add("sum1:$recordElementName");
        $recordElement->add('sum1:IDVersion', '1.0');

        $this->exportCustomProperties($recordElement);

        $encadenamientoElement = $recordElement->add('sum1:Encadenamiento');
        if ($this->previousInvoiceId === null) {
            $encadenamientoElement->add('sum1:PrimerRegistro', 'S');
        } else {
            $registroAnteriorElement = $encadenamientoElement->add('sum1:RegistroAnterior');
            $this->previousInvoiceId->export($registroAnteriorElement, false);
            $registroAnteriorElement->add('sum1:Huella', $this->previousHash);
        }

        $system->export($recordElement);

        $recordElement->add('sum1:FechaHoraHusoGenRegistro', $this->hashedAt->format('c'));
        $recordElement->add('sum1:TipoHuella', '01'); // SHA-256
        $recordElement->add('sum1:Huella', $this->hash);
    }

    /**
     * Import custom record properties from XML
     *
     * @param UXML $recordElement Record element
     *
     * @throws ImportException if failed to parse XML
     */
    abstract protected function importCustomProperties(UXML $recordElement): void;

    /**
     * Export custom record properties to XML
     *
     * @param UXML $recordElement Record element
     */
    abstract protected function exportCustomProperties(UXML $recordElement): void;
}
