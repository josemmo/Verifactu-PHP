<?php
namespace josemmo\Verifactu\Models;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Computer system
 *
 * @field SistemaInformatico
 */
class ComputerSystem extends Model {
    /**
     * Nombre-razón social de la persona o entidad productora
     *
     * @field NombreRazon
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $vendorName;

    /**
     * NIF de la persona o entidad productora
     *
     * @field NIF
     */
    #[Assert\NotBlank]
    #[Assert\Length(exactly: 9)]
    public string $vendorNif;

    /**
     * Nombre dado por la persona o entidad productora a su sistema informático de facturación (SIF)
     *
     * @field NombreSistemaInformatico
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $name;

    /**
     * Código identificativo dado por la persona o entidad productora a su sistema informático de facturación (SIF)
     *
     * @field IdSistemaInformatico
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 2)]
    public string $id;

    /**
     * Identificación de la versión del sistema informático de facturación (SIF)
     *
     * @field Version
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public string $version;

    /**
     * Número de instalación del sistema informático de facturación (SIF) utilizado
     *
     * @field NumeroInstalacion
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    public string $installationNumber;

    /**
     * Especifica si solo puede funcionar como "VERI*FACTU" o también puede funcionar como "no VERI*FACTU" (offline)
     *
     * @field TipoUsoPosibleSoloVerifactu
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $onlySupportsVerifactu;

    /**
     * Especifica si permite llevar independientemente la facturación de varios obligados tributarios
     *
     * @field TipoUsoPosibleMultiOT
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $supportsMultipleTaxpayers;

    /**
     * En el momento de la generación de este registro, está soportando la facturación de más de un obligado tributario
     *
     * @field IndicadorMultiplesOT
     */
    #[Assert\NotNull]
    #[Assert\Type('boolean')]
    public bool $hasMultipleTaxpayers;
}
