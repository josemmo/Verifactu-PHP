# Verifactu-PHP
[![CI](https://github.com/josemmo/Verifactu-PHP/workflows/CI/badge.svg)](https://github.com/josemmo/Verifactu-PHP/actions)
[![Última versión estable](https://img.shields.io/packagist/v/josemmo/verifactu-php)](https://packagist.org/packages/josemmo/verifactu-php)
[![Versión de PHP](https://img.shields.io/badge/php-%3E%3D8.2-8892BF)](composer.json)
[![Documentación](https://img.shields.io/badge/online-docs-blueviolet)](https://josemmo.github.io/Verifactu-PHP/)

Verifactu-PHP es una librería sencilla escrita en PHP que permite generar registros de facturación según el sistema [VERI*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html) y posteriormente enviarlos telemáticamente a la Agencia Tributaria (AEAT).

## Instalación
Asegúrate de que tu entorno de ejecución cumple los siguientes requisitos:

- PHP 8.2 o superior
- libXML

Puedes instalar la librería utilizando el gestor de dependencias [Composer](https://getcomposer.org/):
```sh
composer require josemmo/verifactu-php
```

## Ejemplo de uso
```php
use josemmo\Verifactu\Models\ComputerSystem;
use josemmo\Verifactu\Models\Records\BreakdownDetails;
use josemmo\Verifactu\Models\Records\FiscalIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceIdentifier;
use josemmo\Verifactu\Models\Records\InvoiceType;
use josemmo\Verifactu\Models\Records\OperationType;
use josemmo\Verifactu\Models\Records\RegimeType;
use josemmo\Verifactu\Models\Records\RegistrationRecord;
use josemmo\Verifactu\Models\Records\TaxType;
use josemmo\Verifactu\Models\Responses\ResponseStatus;
use josemmo\Verifactu\Services\AeatClient;

require __DIR__ . '/vendor/autoload.php';

// Genera un registro de facturación
$record = new RegistrationRecord();
$record->invoiceId = new InvoiceIdentifier();
$record->invoiceId->issuerId = 'A00000000';
$record->invoiceId->invoiceNumber = 'TICKET-2025-06-001';
$record->invoiceId->issueDate = new DateTimeImmutable('2025-06-10');
$record->issuerName = 'Perico de los Palotes, S.A.';
$record->invoiceType = InvoiceType::Simplificada;
$record->description = 'Factura simplificada de prueba';
$record->breakdown[0] = new BreakdownDetails();
$record->breakdown[0]->taxType = TaxType::IVA;
$record->breakdown[0]->regimeType = RegimeType::C01;
$record->breakdown[0]->operationType = OperationType::Subject;
$record->breakdown[0]->baseAmount = '10.00';
$record->breakdown[0]->taxRate = '21.00';
$record->breakdown[0]->taxAmount = '2.10';
$record->totalTaxAmount = '2.10';
$record->totalAmount = '12.10';
$record->previousInvoiceId = null; // primera factura de la cadena
$record->previousHash = null;      // primera factura de la cadena
$record->hashedAt = new DateTimeImmutable();
$record->hash = $record->calculateHash();
$record->validate();

// Define los datos del SIF
$system = new ComputerSystem();
$system->vendorName = 'Perico de los Palotes, S.A.';
$system->vendorNif = 'A00000000';
$system->name = 'Sistema Informático de Prueba';
$system->id = 'PA';
$system->version = '0.0.1';
$system->installationNumber = '1234';
$system->onlySupportsVerifactu = true;
$system->supportsMultipleTaxpayers = false;
$system->hasMultipleTaxpayers = false;
$system->validate();

// Crea un cliente para el webservice de la AEAT
$taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');
$client = new AeatClient($system, $taxpayer);
$client->setCertificate(__DIR__ . '/certificado.pfx', 'contraseña');
$client->setProduction(false); // <-- para usar el entorno de preproducción
$aeatResponse = $client->send([$record])->wait();

// Obtiene la respuesta
if ($aeatResponse->status === ResponseStatus::Correct) {
    $csv = $aeatResponse->csv;
    echo "Registro aceptado sin errores: $csv\n";
} else {
    $errorDescription = $aeatResponse->items[0]->errorDescription;
    echo "Registro rechazado o aceptado con errores: $errorDescription\n";
}
```

## Exención de responsabilidad
Esta librería se proporciona sin una declaración responsable al no ser un Sistema Informático de Facturación (SIF).
Verifactu-PHP es una herramienta para crear SIFs, es tu responsabilidad auditar su código y usarlo de acuerdo a la normativa vigente.

Para más información, consulta el [Artículo 13 del RD 1007/2023](https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840#a1-5).

## Licencia
Verifactu-PHP se encuentra bajo [licencia MIT](LICENSE).
Puedes utilizar este paquete en cualquier proyecto (incluso con fines comerciales), siempre y cuando hagas referencia al uso y autoría de la misma.
