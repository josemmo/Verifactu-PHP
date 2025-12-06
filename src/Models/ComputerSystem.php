<?php
namespace josemmo\Verifactu\Models;

use josemmo\Verifactu\Exceptions\ImportException;
use Symfony\Component\Validator\Constraints as Assert;
use UXML\UXML;

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

    /**
     * Import instance from XML element
     *
     * @param UXML $xml XML element
     *
     * @return self New computer system instance
     *
     * @throws ImportException if failed to parse XML
     */
    public static function fromXml(UXML $xml): self {
        $model = new self();

        // Vendor name
        $vendorName = $xml->get('sum1:NombreRazon')?->asText();
        if ($vendorName === null) {
            throw new ImportException('Missing <sum1:NombreRazon /> element');
        }
        $model->vendorName = $vendorName;

        // Vendor NIF
        $vendorNif = $xml->get('sum1:NIF')?->asText();
        if ($vendorNif === null) {
            throw new ImportException('Missing <sum1:NIF /> element');
        }
        $model->vendorNif = $vendorNif;

        // Name
        $name = $xml->get('sum1:NombreSistemaInformatico')?->asText();
        if ($name === null) {
            throw new ImportException('Missing <sum1:NombreSistemaInformatico /> element');
        }
        $model->name = $name;

        // ID
        $id = $xml->get('sum1:IdSistemaInformatico')?->asText();
        if ($id === null) {
            throw new ImportException('Missing <sum1:IdSistemaInformatico /> element');
        }
        $model->id = $id;

        // Version
        $version = $xml->get('sum1:Version')?->asText();
        if ($version === null) {
            throw new ImportException('Missing <sum1:Version /> element');
        }
        $model->version = $version;

        // Installation number
        $installationNumber = $xml->get('sum1:NumeroInstalacion')?->asText();
        if ($installationNumber === null) {
            throw new ImportException('Missing <sum1:NumeroInstalacion /> element');
        }
        $model->installationNumber = $installationNumber;

        // Flags
        $onlySupportsVerifactu = $xml->get('sum1:TipoUsoPosibleSoloVerifactu')?->asText() ?? 'N';
        $supportsMultipleTaxpayers = $xml->get('sum1:TipoUsoPosibleMultiOT')?->asText() ?? 'N';
        $hasMultipleTaxpayers = $xml->get('sum1:IndicadorMultiplesOT')?->asText() ?? 'N';
        $model->onlySupportsVerifactu = ($onlySupportsVerifactu === 'S');
        $model->supportsMultipleTaxpayers = ($supportsMultipleTaxpayers === 'S');
        $model->hasMultipleTaxpayers = ($hasMultipleTaxpayers === 'S');

        return $model;
    }

    /**
     * Export model to XML
     *
     * @param UXML $xml XML parent element
     */
    public function export(UXML $xml): void {
        $element = $xml->add('sum1:SistemaInformatico');
        $element->add('sum1:NombreRazon', $this->vendorName);
        $element->add('sum1:NIF', $this->vendorNif);
        $element->add('sum1:NombreSistemaInformatico', $this->name);
        $element->add('sum1:IdSistemaInformatico', $this->id);
        $element->add('sum1:Version', $this->version);
        $element->add('sum1:NumeroInstalacion', $this->installationNumber);
        $element->add('sum1:TipoUsoPosibleSoloVerifactu', $this->onlySupportsVerifactu ? 'S' : 'N');
        $element->add('sum1:TipoUsoPosibleMultiOT', $this->supportsMultipleTaxpayers ? 'S' : 'N');
        $element->add('sum1:IndicadorMultiplesOT', $this->hasMultipleTaxpayers ? 'S' : 'N');
    }
}
