<?php
namespace josemmo\Verifactu\Models\Responses;

/**
 * Estado del envío de un registro
 */
enum ItemStatus: string {
    /** El registro de facturación es totalmente correcto y se registra en el sistema */
    case Correct = 'Correcto';

    /** El registro de facturación tiene errores que no provocan su rechazo */
    case AcceptedWithErrors = 'AceptadoConErrores';

    /** El registro de facturación tiene errores que provocan su rechazo */
    case Incorrect = 'Incorrecto';
}
