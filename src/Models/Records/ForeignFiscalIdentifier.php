<?php
namespace josemmo\Verifactu\Models\Records;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use josemmo\Verifactu\Models\Model;

/**
 * Identificador fiscal de fuera de España
 *
 * @field Caberecera/ObligadoEmision
 * @field Caberecera/Representante
 * @field RegistroAlta/Tercero
 * @field IDDestinatario
 */
class ForeignFiscalIdentifier extends Model {
    /**
     * Nombre-razón social
     *
     * @field NombreRazon
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 120)]
    public string $name;

    /**
     * Código del país (ISO 3166-1 alpha-2 codes)
     *
     * @field IDOtro/CodigoPais
     */
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[A-Z]{2}$/')]
    public string $country;

    /**
     * Clave para establecer el tipo de identificación en el país de residencia
     *
     * @field IDOtro/IDType
     */
    #[Assert\NotBlank]
    public ForeignIdType $type;

    /**
     * Número de identificación en el país de residencia
     *
     * @field IDOtro/ID
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $value;

    #[Assert\Callback]
    final public function validateCountry(ExecutionContextInterface $context): void {
        if (isset($this->country) && $this->country === 'ES') {
            $context->buildViolation('Country code cannot be "ES", use the `FiscalIdentifier` model instead')
                ->atPath('country')
                ->addViolation();
        }
    }
}