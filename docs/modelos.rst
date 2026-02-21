Modelos y validación
====================

Verifactu-PHP proporciona una serie de clases llamadas **modelos** que representan los distintos elementos de un registro VERI*FACTU.
Todos los modelos de la librería heredan de la clase :php:class:`josemmo\Verifactu\Models\Model`.
Algunos de los ejemplos más reprensentativos de modelos de esta librería son:

* :php:class:`josemmo\Verifactu\Models\Records\RegistrationRecord`: Clase principal para un registro de alta.
* :php:class:`josemmo\Verifactu\Models\Records\FiscalIdentifier`: Identificación fiscal de una entidad (nombre y NIF). Se usa principalmente para identificar al responsable tributario en el envío.
* :php:class:`josemmo\Verifactu\Models\Records\BreakdownDetails`: Detalle de desglose fiscal por cada tipo impositivo aplicado.
* :php:class:`josemmo\Verifactu\Models\ComputerSystem`: Datos del sistema informático emisor (SIF). Este objeto se usa para informar a AEAT sobre el software que genera los registros.
* :php:class:`josemmo\Verifactu\Models\Responses\AeatResponse`: Datos parseados de una respuesta recibida de una comunicación con el servidor de la AEAT.

Validación
----------

Todos los modelos disponen del método :php:method:`josemmo\Verifactu\Models\Model::validate()`, que revisa las reglas de formato y presencia de datos obligatorios según la normativa.
Si algún campo tiene un valor incorrecto, este método lanzará una excepción del tipo :php:class:`josemmo\Verifactu\Exceptions\InvalidModelException`.

A continuación se muestra un ejemplo de uso:

.. code-block:: php

    use josemmo\Verifactu\Exceptions\InvalidModelException;
    use josemmo\Verifactu\Models\Records\BreakdownDetails;

    $details = new BreakdownDetails();
    $details->taxType = TaxType::IVA;
    $details->regimeType = RegimeType::C01;
    $details->operationType = OperationType::Subject;
    $details->baseAmount = '100.00';
    $details->taxRate = '10.00';
    $details->taxAmount = '12.34'; // <-- Debería ser 10.00
    try {
        $details->validate();
    } catch (InvalidModelException $e) {
        echo "Not a valid model: $e\n";
    }
