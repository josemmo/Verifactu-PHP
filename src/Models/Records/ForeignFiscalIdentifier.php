<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
        // CodigoPais es obligatorio para todos valores de IDType != 02
        if (!isset($this->country)
            && $this->type !== ForeignIdType::VAT
        ) {
            $context->buildViolation('Country code is mandatory when using an IDType different from "VAT"')
                ->atPath('country')
                ->addViolation();
        }

        // CodigoPais solo puede ser ES para valores de IDType != (03, 07)
        if (isset($this->country)
            && !($this->type === ForeignIdType::Passport || $this->type === ForeignIdType::Unregistered)
            && $this->country === 'ES'
        ) {
            $context->buildViolation('Country code cannot be "ES" when using and IDType different from "Passport" or "Unregistered"')
                ->atPath('country')
                ->addViolation();
        }

        // CodigoPais debe ser ES para IDType == 07
        if (isset($this->country)
            && $this->type === ForeignIdType::Unregistered
            && $this->country !== 'ES'
        ) {
            $context->buildViolation('Country code must be "ES" when using and IDType "Unregistered"')
                ->atPath('country')
                ->addViolation();
        }
    }
}
