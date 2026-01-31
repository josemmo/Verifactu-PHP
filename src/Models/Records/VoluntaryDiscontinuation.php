<?php
namespace josemmo\Verifactu\Models\Records;

use DateTimeImmutable;
use josemmo\Verifactu\Models\Model;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Remisión Voluntaria VERI*FACTU
 *
 * @field Caberecera/RemisionVoluntaria/FechaFinVeriFactu
 * @field Caberecera/RemisionVoluntaria/Incidencia
 */
class VoluntaryDiscontinuation extends Model {
    /**
     * Class constructor
     *
     * @param DateTimeImmutable|null $endDate  When the company will stop using VERI*FACTU. As the AEAT says, this date should be at the end of the year.
     * @param bool|null              $incident Whether the reason of the voluntary discontinuation is an incident or not (Default: false)
     */
    public function __construct(
        ?DateTimeImmutable $endDate = null,
        ?bool $incident = false,
    ) {
        if ($endDate !== null) {
            $this->endDate = $endDate;
        }
        if ($incident !== null) {
            $this->incident = $incident;
        }
    }

    /**
     * Fecha fin de VERI*FACTU. Debe ser a finales del año contable actual.
     *
     * @field Caberecera/RemisionVoluntaria/FechaFinVeriFactu
     */
    public ?DateTimeImmutable $endDate = null;

    /**
     * Incidencia
     *
     * @field Caberecera/RemisionVoluntaria/Incidencia
     */
    #[Assert\Type('boolean')]
    public bool $incident = false;
}
