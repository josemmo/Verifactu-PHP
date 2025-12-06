<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Exceptions\ImportException;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use UXML\UXML;

/**
 * Detalle de desglose
 *
 * @field DetalleDesglose
 */
class BreakdownDetails extends Model {
    /**
     * Impuesto de aplicación
     *
     * @field Impuesto
     */
    #[Assert\NotBlank]
    public TaxType $taxType;

    /**
     * Clave que identifica el tipo de régimen del impuesto o una operación con trascendencia tributaria
     *
     * @field ClaveRegimen
     */
    #[Assert\NotBlank]
    public RegimeType $regimeType;

    /**
     * Clave de la operación sujeta y no exenta, clave de la operación no sujeta, o causa de la exención
     *
     * @field CalificacionOperacion
     * @field OperacionExenta
     */
    #[Assert\NotBlank]
    public OperationType $operationType;

    /**
     * Magnitud dineraria sobre la que se aplica el tipo impositivo / Importe no sujeto
     *
     * @field BaseImponibleOimporteNoSujeto
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $baseAmount;

    /**
     * Porcentaje aplicado sobre la base imponible para calcular la cuota
     *
     * @field TipoImpositivo
     */
    #[Assert\Regex(pattern: '/^\d{1,3}\.\d{2}$/')]
    public ?string $taxRate = null;

    /**
     * Cuota resultante de aplicar a la base imponible el tipo impositivo
     *
     * @field CuotaRepercutida
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $taxAmount = null;

    /**
     * Porcentaje aplicado sobre la base imponible para calcular la cuota
     *
     * @field TipoRecargoEquivalencia
     */
    #[Assert\Regex(pattern: '/^\d{1,3}\.\d{2}$/')]
    public ?string $surchargeRate = null;

    /**
     * Cuota resultante de aplicar a la base imponible el tipo de recargo de equivalencia
     *
     * @field CuotaRecargoEquivalencia
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $surchargeAmount = null;

    /**
     * Import instance from XML element
     *
     * @param UXML $xml XML element
     *
     * @return self New breakdown details instance
     *
     * @throws ImportException if failed to parse XML
     */
    public static function fromXml(UXML $xml): self {
        $model = new self();

        // Tax type
        $rawTaxType = $xml->get('sum1:Impuesto')?->asText();
        if ($rawTaxType === null) {
            throw new ImportException('Missing <sum1:Impuesto /> element');
        }
        $taxType = TaxType::tryFrom($rawTaxType);
        if ($taxType === null) {
            throw new ImportException('Invalid value for <sum1:Impuesto /> element');
        }
        $model->taxType = $taxType;

        // Regime type
        $rawRegimeType = $xml->get('sum1:ClaveRegimen')?->asText();
        if ($rawRegimeType === null) {
            throw new ImportException('Missing <sum1:ClaveRegimen /> element');
        }
        $regimeType = RegimeType::tryFrom($rawRegimeType);
        if ($regimeType === null) {
            throw new ImportException('Invalid value for <sum1:ClaveRegimen /> element');
        }
        $model->regimeType = $regimeType;

        // Operation type
        $rawOperationType = $xml->get('sum1:CalificacionOperacion')?->asText() ?? $xml->get('sum1:OperacionExenta')?->asText();
        if ($rawOperationType === null) {
            throw new ImportException('Missing <sum1:CalificacionOperacion /> element');
        }
        $operationType = OperationType::tryFrom($rawOperationType);
        if ($operationType === null) {
            throw new ImportException('Invalid value for <sum1:CalificacionOperacion /> element');
        }
        $model->operationType = $operationType;

        // Base amount
        $baseAmount = $xml->get('sum1:BaseImponibleOimporteNoSujeto')?->asText();
        if ($baseAmount === null) {
            throw new ImportException('Missing <sum1:BaseImponibleOimporteNoSujeto /> element');
        }
        $model->baseAmount = $baseAmount;

        // Tax rate
        $taxRate = $xml->get('sum1:TipoImpositivo')?->asText();
        if ($taxRate !== null) {
            $model->taxRate = $taxRate;
        }

        // Tax amount
        $taxAmount = $xml->get('sum1:CuotaRepercutida')?->asText();
        if ($taxAmount !== null) {
            $model->taxAmount = $taxAmount;
        }

        // Surcharge rate
        $surchargeRate = $xml->get('sum1:TipoRecargoEquivalencia')?->asText();
        if ($surchargeRate !== null) {
            $model->surchargeRate = $surchargeRate;
        }

        // Surcharge amount
        $surchargeAmount = $xml->get('sum1:CuotaRecargoEquivalencia')?->asText();
        if ($surchargeAmount !== null) {
            $model->surchargeAmount = $surchargeAmount;
        }

        return $model;
    }

    #[Assert\Callback]
    final public function validateRegimeType(ExecutionContextInterface $context): void {
        if (!isset($this->regimeType)) {
            return;
        }
        if (!$this->operationType->isSubject()) {
            return;
        }

        if ($this->regimeType === RegimeType::C18) {
            if ($this->surchargeRate === null) {
                $context->buildViolation('Surcharge rate must be defined for C18 regime type')
                    ->atPath('surchargeRate')
                    ->addViolation();
            }
            if ($this->surchargeAmount === null) {
                $context->buildViolation('Surcharge amount must be defined for C18 regime type')
                    ->atPath('surchargeAmount')
                    ->addViolation();
            }
        } else {
            if ($this->surchargeRate !== null) {
                $context->buildViolation('Surcharge rate cannot be defined for non-C18 regime types')
                    ->atPath('surchargeRate')
                    ->addViolation();
            }
            if ($this->surchargeAmount !== null) {
                $context->buildViolation('Surcharge amount cannot be defined for non-C18 regime types')
                    ->atPath('surchargeAmount')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    final public function validateOperationType(ExecutionContextInterface $context): void {
        if (!isset($this->operationType)) {
            return;
        }

        if ($this->operationType->isSubject()) {
            if ($this->taxRate === null) {
                $context->buildViolation('Tax rate must be defined for subject operation types')
                    ->atPath('taxRate')
                    ->addViolation();
            }
            if ($this->taxAmount === null) {
                $context->buildViolation('Tax amount must be defined for subject operation types')
                    ->atPath('taxAmount')
                    ->addViolation();
            }
        } else {
            if ($this->taxRate !== null) {
                $context->buildViolation('Tax rate cannot be defined for non-subject or exempt operation types')
                    ->atPath('taxRate')
                    ->addViolation();
            }
            if ($this->taxAmount !== null) {
                $context->buildViolation('Tax amount cannot be defined for non-subject or exempt operation types')
                    ->atPath('taxAmount')
                    ->addViolation();
            }
            if ($this->surchargeRate !== null) {
                $context->buildViolation('Surcharge rate cannot be defined for non-subject or exempt operation types')
                    ->atPath('surchargeRate')
                    ->addViolation();
            }
            if ($this->surchargeAmount !== null) {
                $context->buildViolation('Surcharge amount cannot be defined for non-subject or exempt operation types')
                    ->atPath('surchargeAmount')
                    ->addViolation();
            }
        }
    }

    #[Assert\Callback]
    final public function validateTaxAmount(ExecutionContextInterface $context): void {
        if (!isset($this->baseAmount) || $this->taxRate === null || $this->taxAmount === null) {
            return;
        }
        $this->validateRateAmount($context, $this->taxRate, $this->taxAmount, 'taxAmount');
    }

    #[Assert\Callback]
    final public function validateSurchargeAmount(ExecutionContextInterface $context): void {
        if (!isset($this->baseAmount) || $this->surchargeRate === null || $this->surchargeAmount === null) {
            return;
        }
        $this->validateRateAmount($context, $this->surchargeRate, $this->surchargeAmount, 'surchargeAmount');
    }

    /**
     * Export model to XML
     *
     * @param UXML $xml XML parent element
     */
    public function export(UXML $xml): void {
        $element = $xml->add('sum1:DetalleDesglose');

        // Tax type
        $element->add('sum1:Impuesto', $this->taxType->value);

        // Regime type
        $element->add('sum1:ClaveRegimen', $this->regimeType->value);

        // Operation type
        $element->add(
            $this->operationType->isExempt() ? 'sum1:OperacionExenta' : 'sum1:CalificacionOperacion',
            $this->operationType->value,
        );

        // Tax rate
        if ($this->taxRate !== null) {
            $element->add('sum1:TipoImpositivo', $this->taxRate);
        }

        // Base amount
        $element->add('sum1:BaseImponibleOimporteNoSujeto', $this->baseAmount);

        // Tax amount
        if ($this->taxAmount !== null) {
            $element->add('sum1:CuotaRepercutida', $this->taxAmount);
        }

        // Surcharge rate
        if ($this->surchargeRate !== null) {
            $element->add('sum1:TipoRecargoEquivalencia', $this->surchargeRate);
        }

        // Surcharge amount
        if ($this->surchargeAmount !== null) {
            $element->add('sum1:CuotaRecargoEquivalencia', $this->surchargeAmount);
        }
    }

    /**
     * Validate rate amount
     *
     * @param ExecutionContextInterface $context      Execution context
     * @param string                    $rate         Rate
     * @param string                    $actualAmount Actual amount
     * @param string                    $propertyName Property name
     */
    private function validateRateAmount(
        ExecutionContextInterface $context,
        string $rate,
        string $actualAmount,
        string $propertyName,
    ): void {
        $isValidAmount = false;
        $bestAmount = (float) $this->baseAmount * ((float) $rate / 100);
        foreach ([0, -0.01, 0.01, -0.02, 0.02] as $tolerance) {
            $expectedAmount = number_format($bestAmount + $tolerance, 2, '.', '');
            if ($actualAmount === $expectedAmount) {
                $isValidAmount = true;
                break;
            }
        }
        if (!$isValidAmount) {
            $bestAmountFormatted = number_format($bestAmount, 2, '.', '');
            $context->buildViolation("Expected amount of $bestAmountFormatted, got $actualAmount")
                ->atPath($propertyName)
                ->addViolation();
        }
    }
}
