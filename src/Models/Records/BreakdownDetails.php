<?php
namespace josemmo\Verifactu\Models\Records;

use Symfony\Component\Validator\Constraints as Assert;
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
    #[Assert\NotBlank]
    public RegimeType $regimeType;

    /**
     * Clave de la operación sujeta y no exenta o de la operación no sujeta
     *
     * @field CalificacionOperacion
     */
    #[Assert\NotBlank]
    public OperationType $operationType;

    /**
     * Porcentaje aplicado sobre la base imponible para calcular la cuota
     *
     * @field TipoImpositivo
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^\d{1,3}\.\d{2}$/')]
    public string $taxRate;

    /**
     * Magnitud dineraria sobre la que se aplica el tipo impositivo / Importe no sujeto
     *
     * @field BaseImponibleOimporteNoSujeto
     */
    #[Assert\NotBlank]
    //#[Assert\Regex(pattern: '/^\d{1,12}\.\d{2}$/')]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $baseAmount;

    /**
     * Cuota resultante de aplicar a la base imponible el tipo impositivo
     *
     * @field CuotaRepercutida
     */
    #[Assert\NotBlank]
    //#[Assert\Regex(pattern: '/^\d{1,12}\.\d{2}$/')]
    #[Assert\Regex(pattern: '/^-?\d{1,12}\.\d{2}$/')]
    public string $taxAmount;

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
}
