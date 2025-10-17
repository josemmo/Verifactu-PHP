<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Base invoice record
 */
abstract class Record extends Model {
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
     * @param UXML           $xml    UXML instance
     * @param ComputerSystem $system Computer system information
     */
    public function export(UXML $xml, ComputerSystem $system): void {
        $recordElementName = $this->getRecordElementName();
        $recordElement = $xml->add("sum1:$recordElementName");
        $recordElement->add('sum1:IDVersion', '1.0');

        $this->exportCustomProperties($recordElement);

        $encadenamientoElement = $recordElement->add('sum1:Encadenamiento');
        if ($this->previousInvoiceId === null) {
            $encadenamientoElement->add('sum1:PrimerRegistro', 'S');
        } else {
            $registroAnteriorElement = $encadenamientoElement->add('sum1:RegistroAnterior');
            $registroAnteriorElement->add('sum1:IDEmisorFactura', $this->previousInvoiceId->issuerId);
            $registroAnteriorElement->add('sum1:NumSerieFactura', $this->previousInvoiceId->invoiceNumber);
            $registroAnteriorElement->add('sum1:FechaExpedicionFactura', $this->previousInvoiceId->issueDate->format('d-m-Y'));
            $registroAnteriorElement->add('sum1:Huella', $this->previousHash);
        }

        $sistemaInformaticoElement = $recordElement->add('sum1:SistemaInformatico');
        $sistemaInformaticoElement->add('sum1:NombreRazon', $system->vendorName);
        $sistemaInformaticoElement->add('sum1:NIF', $system->vendorNif);
        $sistemaInformaticoElement->add('sum1:NombreSistemaInformatico', $system->name);
        $sistemaInformaticoElement->add('sum1:IdSistemaInformatico', $system->id);
        $sistemaInformaticoElement->add('sum1:Version', $system->version);
        $sistemaInformaticoElement->add('sum1:NumeroInstalacion', $system->installationNumber);
        $sistemaInformaticoElement->add('sum1:TipoUsoPosibleSoloVerifactu', $system->onlySupportsVerifactu ? 'S' : 'N');
        $sistemaInformaticoElement->add('sum1:TipoUsoPosibleMultiOT', $system->supportsMultipleTaxpayers ? 'S' : 'N');
        $sistemaInformaticoElement->add('sum1:IndicadorMultiplesOT', $system->hasMultipleTaxpayers ? 'S' : 'N');

        $recordElement->add('sum1:FechaHoraHusoGenRegistro', $this->hashedAt->format('c'));
        $recordElement->add('sum1:TipoHuella', '01'); // SHA-256
        $recordElement->add('sum1:Huella', $this->hash);
    }

    /**
     * Get record element name
     *
     * @return string XML element name
     */
    abstract protected function getRecordElementName(): string;

    /**
     * Export custom record properties to XML
     *
     * @param UXML $recordElement Record element
     */
    abstract protected function exportCustomProperties(UXML $recordElement): void;
}
