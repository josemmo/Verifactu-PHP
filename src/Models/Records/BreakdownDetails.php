<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
