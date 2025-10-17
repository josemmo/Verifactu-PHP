<?php
namespace josemmo\Verifactu\Models\Records;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Registro de alta de una factura
 *
 * @field RegistroAlta
 */
class RegistrationRecord extends Record {
    /**
     * Indicador de subsanación de un registro de facturación de alta previamente generado
     *
     * @field Subsanacion
     */
    #[Assert\Type('boolean')]
    public bool $isCorrection = false;

    /**
     * Nombre-razón social del obligado a expedir la factura
     *
     * @field NombreRazonEmisor
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $issuerName;

    /**
     * Especificación del tipo de factura
     *
     * @field TipoFactura
     */
    #[Assert\NotBlank]
    public InvoiceType $invoiceType;

    /**
     * Descripción del objeto de la factura
     *
     * @field DescripcionOperacion
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    public string $description;

    /**
     * Destinatarios de la factura
     *
     * @var array<FiscalIdentifier | ForeignFiscalIdentifier>
     *
     * @field Destinatarios
     */
    #[Assert\Valid]
    #[Assert\Count(max: 1000)]
    public array $recipients = [];

    /**
     * Tipo de factura rectificativa
     *
     * @field TipoRectificativa
     */
    public ?CorrectiveType $correctiveType = null;

    /**
     * Listado de facturas rectificadas
     *
     * @var InvoiceIdentifier[]
     *
     * @field FacturasRectificadas
     */
    public array $correctedInvoices = [];

    /**
     * Base imponible rectificada (para facturas rectificativas por diferencias)
     *
     * @field ImporteRectificacion/BaseRectificada
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedBaseAmount = null;

    /**
     * Cuota repercutida o soportada rectificada (para facturas rectificativas por diferencias)
     *
     * @field ImporteRectificacion/CuotaRectificada
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedTaxAmount = null;

    /**
     * Listado de facturas sustituidas
     *
     * @var InvoiceIdentifier[]
     *
     * @field FacturasSustituidas
     */
    public array $replacedInvoices = [];

    /**
     * Desglose de la factura
     *
     * @var BreakdownDetails[]
     *
     * @field Desglose
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, max: 12)]
    public array $breakdown = [];

    /**
     * Importe total de la cuota (sumatorio de la Cuota Repercutida y Cuota de Recargo de Equivalencia)
     *
     * @field CuotaTotal
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $totalTaxAmount;

    /**
     * Importe total de la factura
     *
     * @field ImporteTotal
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $totalAmount;

    /**
     * @inheritDoc
     */
    public function calculateHash(): string {
        // NOTE: Values should NOT be escaped as that what the AEAT says ¯\_(ツ)_/¯
        $payload  = 'IDEmisorFactura=' . $this->invoiceId->issuerId;
        $payload .= '&NumSerieFactura=' . $this->invoiceId->invoiceNumber;
        $payload .= '&FechaExpedicionFactura=' . $this->invoiceId->issueDate->format('d-m-Y');
        $payload .= '&TipoFactura=' . $this->invoiceType->value;
        $payload .= '&CuotaTotal=' . $this->totalTaxAmount;
        $payload .= '&ImporteTotal=' . $this->totalAmount;
        $payload .= '&Huella=' . ($this->previousHash ?? '');
        $payload .= '&FechaHoraHusoGenRegistro=' . $this->hashedAt->format('c');
        return strtoupper(hash('sha256', $payload));
    }

    #[Assert\Callback]
    final public function validateTotals(ExecutionContextInterface $context): void {
        if (!isset($this->breakdown) || !isset($this->totalTaxAmount) || !isset($this->totalAmount)) {
            return;
        }

        $expectedTotalTaxAmount = 0;
        $totalBaseAmount = 0;
        foreach ($this->breakdown as $details) {
            if (!isset($details->taxAmount) || !isset($details->baseAmount)) {
                return;
            }
            $expectedTotalTaxAmount += $details->taxAmount;
            $totalBaseAmount += $details->baseAmount;
        }

        $expectedTotalTaxAmount = number_format($expectedTotalTaxAmount, 2, '.', '');
        if ($this->totalTaxAmount !== $expectedTotalTaxAmount) {
            $context->buildViolation("Expected total tax amount of $expectedTotalTaxAmount, got {$this->totalTaxAmount}")
                ->atPath('totalTaxAmount')
                ->addViolation();
        }

        $validTotalAmount = false;
        $bestTotalAmount = $totalBaseAmount + $expectedTotalTaxAmount;
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedTotalAmount = number_format($bestTotalAmount + $tolerance, 2, '.', '');
            if ($this->totalAmount === $expectedTotalAmount) {
                $validTotalAmount = true;
                break;
            }
        }
        if (!$validTotalAmount) {
            $bestTotalAmount = number_format($bestTotalAmount, 2, '.', '');
            $context->buildViolation("Expected total amount of $bestTotalAmount, got {$this->totalAmount}")
                ->atPath('totalAmount')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateRecipients(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        $hasRecipients = count($this->recipients) > 0;
        if ($this->invoiceType === InvoiceType::Simplificada || $this->invoiceType === InvoiceType::R5) {
            if ($hasRecipients) {
                $context->buildViolation('This type of invoice cannot have recipients')
                    ->atPath('recipients')
                    ->addViolation();
            }
        } elseif (!$hasRecipients) {
            $context->buildViolation('This type of invoice requires at least one recipient')
                ->atPath('recipients')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateCorrectiveDetails(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        $isCorrective = in_array($this->invoiceType, [
            InvoiceType::R1,
            InvoiceType::R2,
            InvoiceType::R3,
            InvoiceType::R4,
            InvoiceType::R5,
        ], true);

        // Corrective type
        if ($isCorrective && $this->correctiveType === null) {
            $context->buildViolation('Missing type for corrective invoice')
                ->atPath('correctiveType')
                ->addViolation();
        } elseif (!$isCorrective && $this->correctiveType !== null) {
            $context->buildViolation('This type of invoice cannot have a corrective type')
                ->atPath('correctiveType')
                ->addViolation();
        }

        // Corrected invoices
        if (!$isCorrective && count($this->correctedInvoices) > 0) {
            $context->buildViolation('This type of invoice cannot have corrected invoices')
                ->atPath('correctedInvoices')
                ->addViolation();
        }

        // Corrected amounts
        if ($this->correctiveType === CorrectiveType::Substitution) {
            if ($this->correctedBaseAmount === null) {
                $context->buildViolation('Missing corrected base amount for corrective invoice by substitution')
                    ->atPath('correctedBaseAmount')
                    ->addViolation();
            }
            if ($this->correctedTaxAmount === null) {
                $context->buildViolation('Missing corrected tax amount for corrective invoice by substitution')
                    ->atPath('correctedTaxAmount')
                    ->addViolation();
            }
        } else {
            if ($this->correctedBaseAmount !== null) {
                $context->buildViolation('This invoice cannot have a corrected base amount')
                    ->atPath('correctedBaseAmount')
                    ->addViolation();
            }
            if ($this->correctedTaxAmount !== null) {
                $context->buildViolation('This invoice cannot have a corrected tax amount')
                    ->atPath('correctedTaxAmount')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    final public function validateReplacedInvoices(ExecutionContextInterface $context): void {
        if (!isset($this->invoiceType)) {
            return;
        }

        if ($this->invoiceType !== InvoiceType::Sustitutiva && count($this->replacedInvoices) > 0) {
            $context->buildViolation('This type of invoice cannot have replaced invoices')
                ->atPath('replacedInvoices')
                ->addViolation();
        }
    }
}
