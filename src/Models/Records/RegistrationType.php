<?php
namespace josemmo\Verifactu\Models\Records;

enum RegistrationType: string {
    case REGISTRO_ALTA = 'RegistroAlta';
    case REGISTRO_ANULACION = 'RegistroAnulacion';
}

