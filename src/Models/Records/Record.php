<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
}
