<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Exceptions\ImportException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

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
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $isCorrection = false;

    /**
     * Indicador de rechazo previo
     *
     * Para ser usado en la remisión de un nuevo registro de facturación de alta subsanado tras haber sido rechazado en
     * su remisión inmediatamente anterior.
     * Es decir, en el último envío que contenía ese registro de facturación de alta rechazado.
     *
     * Toma el valor especial `null` cuando el registro de facturación rechazado no se remitió previamente a la AEAT.
     *
     * @field RechazoPrevio
     */
    #[Assert\Type('boolean')]
    public ?bool $isPriorRejection = false;

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
     * Fecha en la que se realiza la operación
     *
     * NOTE: Time part will be ignored.
     *
     * @field FechaOperacion
     */
    public ?DateTimeImmutable $operationDate = null;

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
     * Base imponible rectificada (para facturas rectificativas por sustitución)
     *
     * @field ImporteRectificacion/BaseRectificada
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $correctedBaseAmount = null;

    /**
     * Cuota repercutida o soportada rectificada (para facturas rectificativas por sustitución)
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
    protected static function getRecordElementName(): string {
        return 'RegistroAlta';
    }

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
    final public function validatePriorRejection(ExecutionContextInterface $context): void {
        if ($this->isPriorRejection !== false && !$this->isCorrection) {
            $context->buildViolation('Record cannot be a prior rejection if it is not a correction')
                ->atPath('isPriorRejection')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateTotals(ExecutionContextInterface $context): void {
        if (!isset($this->breakdown) || !isset($this->totalTaxAmount) || !isset($this->totalAmount)) {
            return;
        }

        $expectedTotalBaseAmount = 0;
        $expectedTotalTaxAmount = 0;
        foreach ($this->breakdown as $details) {
            if (!isset($details->baseAmount) || !isset($details->taxAmount)) {
                return;
            }
            $expectedTotalBaseAmount += $details->baseAmount;
            $expectedTotalTaxAmount += $details->taxAmount;
            $expectedTotalTaxAmount += $details->surchargeAmount ?? 0;
        }

        $expectedTotalTaxAmount = number_format($expectedTotalTaxAmount, 2, '.', '');
        if ($this->totalTaxAmount !== $expectedTotalTaxAmount) {
            $context->buildViolation("Expected total tax amount of $expectedTotalTaxAmount, got {$this->totalTaxAmount}")
                ->atPath('totalTaxAmount')
                ->addViolation();
        }

        $isValidTotalAmount = false;
        $bestTotalAmount = number_format($expectedTotalBaseAmount + $expectedTotalTaxAmount, 2, '.', '');
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedTotalAmount = number_format($bestTotalAmount + $tolerance, 2, '.', '');
            if ($this->totalAmount === $expectedTotalAmount) {
                $isValidTotalAmount = true;
                break;
            }
        }
        if (!$isValidTotalAmount) {
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

    /**
     * @inheritDoc
     */
    protected function importCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->get('sum1:IDFactura');
        if ($idFacturaElement === null) {
            throw new ImportException('Missing <sum1:IDFactura /> element');
        }
        $this->invoiceId = InvoiceIdentifier::fromXml($idFacturaElement);

        // Issuer name
        $issuerName = $recordElement->get('sum1:NombreRazonEmisor')?->asText();
        if ($issuerName === null) {
            throw new ImportException('Missing <sum1:NombreRazonEmisor /> element');
        }
        $this->issuerName = $issuerName;

        // Flags
        $isCorrection = $recordElement->get('sum1:Subsanacion')?->asText() ?? 'N';
        $isPriorRejection = $recordElement->get('sum1:RechazoPrevio')?->asText() ?? 'N';
        $this->isCorrection = ($isCorrection === 'S');
        $this->isPriorRejection = match ($isPriorRejection) {
            'S' => true,
            'N' => false,
            'X' => null,
            default => throw new ImportException('Invalid value for <sum1:RechazoPrevio /> element'),
        };

        // Invoice type
        $rawInvoiceType = $recordElement->get('sum1:TipoFactura')?->asText();
        if ($rawInvoiceType === null) {
            throw new ImportException('Missing <sum1:TipoFactura /> element');
        }
        $invoiceType = InvoiceType::tryFrom($rawInvoiceType);
        if ($invoiceType === null) {
            throw new ImportException('Invalid value for <sum1:TipoFactura /> element');
        }
        $this->invoiceType = $invoiceType;

        // Corrective details
        $rawCorrectiveType = $recordElement->get('sum1:TipoRectificativa')?->asText();
        if ($rawCorrectiveType !== null) {
            $correctiveType = CorrectiveType::tryFrom($rawCorrectiveType);
            if ($correctiveType === null) {
                throw new ImportException('Invalid value for <sum1:TipoRectificativa /> element');
            }
            $this->correctiveType = $correctiveType;
        }
        foreach ($recordElement->getAll('sum1:FacturasRectificadas/sum1:IDFacturaRectificada') as $facturaRectificadaElement) {
            $this->correctedInvoices[] = InvoiceIdentifier::fromXml($facturaRectificadaElement);
        }
        foreach ($recordElement->getAll('sum1:FacturasSustituidas/sum1:IDFacturaSustituida') as $facturaSustituidaElement) {
            $this->replacedInvoices[] = InvoiceIdentifier::fromXml($facturaSustituidaElement);
        }
        $this->correctedBaseAmount = $recordElement->get('sum1:ImporteRectificacion/sum1:BaseRectificada')?->asText();
        $this->correctedTaxAmount = $recordElement->get('sum1:ImporteRectificacion/sum1:CuotaRectificada')?->asText();

        // Operation date
        $rawOperationDate = $recordElement->get('sum1:FechaOperacion')?->asText();
        if ($rawOperationDate !== null) {
            $operationDate = DateTimeImmutable::createFromFormat('d-m-Y', $rawOperationDate);
            if ($operationDate === false) {
                throw new ImportException('Invalid value for <sum1:FechaOperacion /> element');
            }
            $this->operationDate = $operationDate;
        }

        // Description
        $description = $recordElement->get('sum1:DescripcionOperacion')?->asText();
        if ($description === null) {
            throw new ImportException('Missing <sum1:DescripcionOperacion /> element');
        }
        $this->description = $description;

        // Recipients
        foreach ($recordElement->getAll('sum1:Destinatarios/sum1:IDDestinatario') as $destinatarioElement) {
            $recipientName = $destinatarioElement->get('sum1:NombreRazon')?->asText();
            if ($recipientName === null) {
                throw new ImportException('Missing <sum1:NombreRazon /> from <sum1:IDDestinatario /> element');
            }

            // Fiscal identifier
            $recipientNif = $destinatarioElement->get('sum1:NIF')?->asText();
            if ($recipientNif !== null) {
                $this->recipients[] = new FiscalIdentifier($recipientName, $recipientNif);
                continue;
            }

            // Foreign fiscal identifier
            $recipientCountry = $destinatarioElement->get('sum1:IDOtro/sum1:CodigoPais')?->asText();
            if ($recipientCountry === null) {
                throw new ImportException('Missing <sum1:CodigoPais /> from <sum1:IDDestinatario /> element');
            }
            $rawRecipientType = $destinatarioElement->get('sum1:IDOtro/sum1:IDType')?->asText();
            if ($rawRecipientType === null) {
                throw new ImportException('Missing <sum1:IDType /> from <sum1:IDDestinatario /> element');
            }
            $recipientType = ForeignIdType::tryFrom($rawRecipientType);
            if ($recipientType === null) {
                throw new ImportException('Invalid value for <sum1:IDType /> from <sum1:IDDestinatario /> element');
            }
            $recipientValue = $destinatarioElement->get('sum1:IDOtro/sum1:ID')?->asText();
            if ($recipientValue === null) {
                throw new ImportException('Missing <sum1:ID /> from <sum1:IDDestinatario /> element');
            }
            $this->recipients[] = new ForeignFiscalIdentifier($recipientName, $recipientCountry, $recipientType, $recipientValue);
        }

        // Breakdown
        foreach ($recordElement->getAll('sum1:Desglose/sum1:DetalleDesglose') as $detalleDesgloseElement) {
            $this->breakdown[] = BreakdownDetails::fromXml($detalleDesgloseElement);
        }

        // Total tax amount
        $totalTaxAmount = $recordElement->get('sum1:CuotaTotal')?->asText();
        if ($totalTaxAmount === null) {
            throw new ImportException('Missing <sum1:CuotaTotal /> element');
        }
        $this->totalTaxAmount = $totalTaxAmount;

        // Total amount
        $totalAmount = $recordElement->get('sum1:ImporteTotal')?->asText();
        if ($totalAmount === null) {
            throw new ImportException('Missing <sum1:ImporteTotal /> element');
        }
        $this->totalAmount = $totalAmount;
    }

    /**
     * @inheritDoc
     */
    protected function exportCustomProperties(UXML $recordElement): void {
        // Invoice ID
        $idFacturaElement = $recordElement->add('sum1:IDFactura');
        $this->invoiceId->export($idFacturaElement, false);

        // Issuer name
        $recordElement->add('sum1:NombreRazonEmisor', $this->issuerName);

        // Flags
        $recordElement->add('sum1:Subsanacion', $this->isCorrection ? 'S' : 'N');
        if ($this->isPriorRejection === true) {
            $recordElement->add('sum1:RechazoPrevio', 'S');
        } elseif ($this->isPriorRejection === null) {
            $recordElement->add('sum1:RechazoPrevio', 'X');
        }

        // Invoice type
        $recordElement->add('sum1:TipoFactura', $this->invoiceType->value);

        // Corrective details
        if ($this->correctiveType !== null) {
            $recordElement->add('sum1:TipoRectificativa', $this->correctiveType->value);
        }
        if (count($this->correctedInvoices) > 0) {
            $facturasRectificadasElement = $recordElement->add('sum1:FacturasRectificadas');
            foreach ($this->correctedInvoices as $correctedInvoice) {
                $facturaRectificadaElement = $facturasRectificadasElement->add('sum1:IDFacturaRectificada');
                $correctedInvoice->export($facturaRectificadaElement, false);
            }
        }
        if (count($this->replacedInvoices) > 0) {
            $facturasSustituidasElement = $recordElement->add('sum1:FacturasSustituidas');
            foreach ($this->replacedInvoices as $replacedInvoice) {
                $facturaSustituidaElement = $facturasSustituidasElement->add('sum1:IDFacturaSustituida');
                $replacedInvoice->export($facturaSustituidaElement, false);
            }
        }
        if ($this->correctedBaseAmount !== null && $this->correctedTaxAmount !== null) {
            $importeRectificacionElement = $recordElement->add('sum1:ImporteRectificacion');
            $importeRectificacionElement->add('sum1:BaseRectificada', $this->correctedBaseAmount);
            $importeRectificacionElement->add('sum1:CuotaRectificada', $this->correctedTaxAmount);
        }

        // Operation date
        if ($this->operationDate !== null) {
            $recordElement->add('sum1:FechaOperacion', $this->operationDate->format('d-m-Y'));
        }

        // Description
        $recordElement->add('sum1:DescripcionOperacion', $this->description);

        // Recipients
        if (count($this->recipients) > 0) {
            $destinatariosElement = $recordElement->add('sum1:Destinatarios');
            foreach ($this->recipients as $recipient) {
                $destinatarioElement = $destinatariosElement->add('sum1:IDDestinatario');
                $destinatarioElement->add('sum1:NombreRazon', $recipient->name);
                if ($recipient instanceof FiscalIdentifier) {
                    $destinatarioElement->add('sum1:NIF', $recipient->nif);
                } else {
                    $idOtroElement = $destinatarioElement->add('sum1:IDOtro');
                    $idOtroElement->add('sum1:CodigoPais', $recipient->country);
                    $idOtroElement->add('sum1:IDType', $recipient->type->value);
                    $idOtroElement->add('sum1:ID', $recipient->value);
                }
            }
        }

        // Breakdown
        $desgloseElement = $recordElement->add('sum1:Desglose');
        foreach ($this->breakdown as $breakdownDetails) {
            $breakdownDetails->export($desgloseElement);
        }

        // Totals
        $recordElement->add('sum1:CuotaTotal', $this->totalTaxAmount);
        $recordElement->add('sum1:ImporteTotal', $this->totalAmount);
    }
}
