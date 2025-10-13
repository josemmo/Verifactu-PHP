<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Estado global del envío
 */
enum ResponseStatus: string {
    /** Todos los registros de facturación de la remisión tienen estado "Correcto" */
    case Correct = 'Correcto';

    /** Algunos registros de la remisión tienen estado "Incorrecto" o "AceptadoConErrores" */
    case PartiallyCorrect = 'ParcialmenteCorrecto';

    /** Todos los registros de la remisión tienen estado "Incorrecto" */
    case Incorrect = 'Incorrecto';
}
