<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Identificador fiscal de fuera de España
 *
 * @field RegistroAlta/Tercero
 * @field IDDestinatario
 */
class ForeignFiscalIdentifier extends Model {
    /**
     * Class constructor
     *
     * @param string|null        $name    Name
     * @param string|null        $country Country
     * @param ForeignIdType|null $type    ID type
     * @param string|null        $value   ID value
     */
    public function __construct(
        ?string $name = null,
        ?string $country = null,
        ?ForeignIdType $type = null,
        ?string $value = null,
    ) {
        if ($name !== null) {
            $this->name = $name;
        }
        if ($country !== null) {
            $this->country = $country;
        }
        if ($type !== null) {
            $this->type = $type;
        }
        if ($value !== null) {
            $this->value = $value;
        }
    }

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
    final public function validateVatNumber(ExecutionContextInterface $context): void {
        if (!isset($this->country) || !isset($this->type) || !isset($this->value)) {
            return;
        }
        if ($this->type !== ForeignIdType::VAT) {
            return;
        }
        $vatCountry = mb_substr($this->value, 0, 2);
        if ($vatCountry !== $this->country) {
            $context->buildViolation('VAT number must start with "' . $this->country . '", found "' . $vatCountry . '"')
                ->atPath('value')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateType(ExecutionContextInterface $context): void {
        if (!isset($this->country) || !isset($this->type)) {
            return;
        }
        if ($this->country !== 'ES') {
            return;
        }
        if ($this->type !== ForeignIdType::Passport && $this->type !== ForeignIdType::Unregistered) {
            $context->buildViolation('Type must be passport or unregistered if country code is "ES"')
                ->atPath('type')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    final public function validateCountry(ExecutionContextInterface $context): void {
        if (!isset($this->country) || !isset($this->type)) {
            return;
        }
        if ($this->type !== ForeignIdType::Unregistered) {
            return;
        }
        if ($this->country !== 'ES') {
            $context->buildViolation('Country code must be "ES" if type is unregistered')
                ->atPath('country')
                ->addViolation();
        }
    }
}
