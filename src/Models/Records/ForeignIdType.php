<?php
namespace josemmo\Verifactu\Models\Records;

enum ForeignIdType: string {
    /** NIF-IVA */
    case VAT = '02';

    /** Pasaporte */
    case Passport = '03';

    /** Documento oficial de identificación expedido por el país o territorio de residencia */
    case NationalId = '04';

    /** Certificado de residencia */
    case Residence = '05';

    /** Otro documento probatorio */
    case Other = '06';

    /**
     * No censado (en caso de que todavía no esté dado de alta en la AEAT)
     *
     * NOTA: El uso de este valor obliga a subsanar el registro posteriormente para corregir el tipo de identificación.
     */
    case Unregistered = '07';
}
