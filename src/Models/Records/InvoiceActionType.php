<?php
namespace josemmo\Verifactu\Models\Records;

enum InvoiceActionType: int {
    case REGISTRO_COMPLETA = 1;
    case REGISTRO_SIMPLIFICADA = 2;
    case REGISTRO_R1_SUSTITUCION = 3;
    case REGISTRO_R1_DIFERENCIAS = 4;
    case REGISTRO_COMPLETA_ABONO = 5;
    case REGISTRO_SIMPLIFICADA_ABONO = 6;
    case REGISTRO_R5_SIMPLIFICADA = 7;
    case ANULACION_COMPLETA = 8;
    case ANULACION_SIMPLIFICADA = 9;
    
}