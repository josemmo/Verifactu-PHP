<?php
namespace josemmo\Verifactu\Models\Records;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use josemmo\Verifactu\Models\Model;

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
    public ?RegimeType $regimeType = null;

    /**
     * Clave de la operación sujeta y no exenta o de la operación no sujeta
     *
     * @field CalificacionOperacion
     */
    public ?OperationType $operationType = null;

    /**
     * Porcentaje aplicado sobre la base imponible para calcular la cuota
     *
     * @field TipoImpositivo
     */
    #[Assert\Regex(pattern: '/^\d{1,3}\.\d{2}$/')]
    public ?string $taxRate = null;

    /**
     * Magnitud dineraria sobre la que se aplica el tipo impositivo / Importe no sujeto
     *
     * @field BaseImponibleOimporteNoSujeto
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $baseAmount;

    /**
     * Cuota resultante de aplicar a la base imponible el tipo impositivo
     *
     * @field CuotaRepercutida
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $taxAmount = null;

    /**
     * Causa de exención (obligatoria si IVA = 0%)
     *
     * @field CausaExencion
     */
    #[Assert\Length(min: 2, max: 2)]
    public ?string $exemptReasonCode = null;

    /**
     * Descripción de la exención
     *
     * @field DescripcionExencion
     */
    #[Assert\Length(max: 500)]
    public ?string $exemptReason = null;

    /**
     * Porcentaje de recargo de equivalencia
     *
     * @field TipoRecargoEquivalencia
     */
    #[Assert\Regex(pattern: '/^\d{1,3}\.\d{2}$/')]
    public ?string $surchargeRate = null;

    /**
     * Cuota de recargo de equivalencia
     *
     * @field CuotaRecargoEquivalencia
     */
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public ?string $surchargeAmount = null;

    /**
     * Validate exemption logic
     *
     * @param ExecutionContextInterface $context Validator execution context
     */
    #[Assert\Callback]
    public function validateExemption(ExecutionContextInterface $context): void {
        if ($this->exemptReasonCode !== null) {
            // Operation is EXEMPT
            if ($this->operationType !== null) {
                $context->buildViolation('CalificacionOperacion (OperationType) must not be set for exempt operations.')
                    ->atPath('operationType')
                    ->addViolation();
            }
            if ($this->taxRate !== '0.00') {
                 $context->buildViolation('TipoImpositivo (TaxRate) must be "0.00" for exempt operations.')
                    ->atPath('taxRate')
                    ->addViolation();
            }
             if ($this->taxAmount !== '0.00') {
                 $context->buildViolation('CuotaRepercutida (TaxAmount) must be "0.00" for exempt operations.')
                    ->atPath('taxAmount')
                    ->addViolation();
            }
        } else {
            // Operation is NOT EXEMPT
            if ($this->operationType === null) {
                $context->buildViolation('This field is mandatory for non-exempt operations.')
                    ->atPath('operationType')
                    ->addViolation();
            }
             if ($this->taxRate === null) {
                $context->buildViolation('This field is mandatory for non-exempt operations.')
                    ->atPath('taxRate')
                    ->addViolation();
            }
             if ($this->taxAmount === null) {
                $context->buildViolation('This field is mandatory for non-exempt operations.')
                    ->atPath('taxAmount')
                    ->addViolation();
            }
        }
    }

    /**
     * Validate regime logic based on tax type
     *
     * @param ExecutionContextInterface $context Validator execution context
     */
    #[Assert\Callback]
    public function validateRegime(ExecutionContextInterface $context): void {
        $taxValue = $this->taxType->value;
        $incompatibleTaxes = [TaxType::IPSI->value, TaxType::Other->value]; // Array of incompatible taxes

        if (in_array($taxValue, $incompatibleTaxes) && $this->regimeType !== null) {
            $context->buildViolation('ClaveRegimen (RegimeType) must not be set when TaxType is IPSI or Other.')
                ->atPath('regimeType')
                ->addViolation();
        } elseif (!in_array($taxValue, $incompatibleTaxes) && $this->regimeType === null) {
            $context->buildViolation('ClaveRegimen (RegimeType) is mandatory for this TaxType.')
                ->atPath('regimeType')
                ->addViolation();
        }
    }
}
