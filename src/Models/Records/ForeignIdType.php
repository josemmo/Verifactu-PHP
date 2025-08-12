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

    /** No censado */
    case Unregistered = '07';
}