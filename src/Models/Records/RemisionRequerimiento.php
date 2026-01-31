<?php
namespace josemmo\Verifactu\Models\Records;

use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Remisión por requerimiento
 *
 * @field RemisionRequerimiento
 */
class RemisionRequerimiento extends Model {
    /**
     * Class constructor
     *
     * @param string   $requirementReference Reference to the requirement
     * @param bool|null $isRequirementEnd    End of requirement, to be set when the requirement is completed
     */
    public function __construct(
        string $requirementReference,
        ?bool $isRequirementEnd = null,
    ) {
        $this->requirementReference = $requirementReference;
        if ($isRequirementEnd !== null) {
            $this->isRequirementEnd = $isRequirementEnd;
        }
    }

    /**
     * Referencia del requerimiento
     *
     * @field RefRequerimiento
     */
    #[Assert\NotBlank]
    #[Assert\Length(max: 18)]
    public string $requirementReference;

    /**
     * Fin del requerimiento
     *
     * @field FinRequerimiento
     */
    #[Assert\Type('boolean')]
    public ?bool $isRequirementEnd = null;
}
