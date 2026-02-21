Códigos QR
==========

Verifactu-PHP no incluye dependencias para generar imágenes de los códigos QR, aunque sí que proporciona un sencillo servicio para generar la URL que deben contener estos códigos QR a través de la clase :php:class:`josemmo\Verifactu\Services\QrGenerator`.

Dicha clase proporciona varios métodos para la generación de la URL, aunque lo más sencillo es utilizar una instancia de un registro generado previamente:

.. code-block:: php

    use josemmo\Verifactu\Models\Records\RegistrationRecord;
    use josemmo\Verifactu\Services\QrGenerator;

    // Ejemplo de un registro de alta
    $record = new RegistrationRecord();
    $record->invoiceId = new InvoiceIdentifier('A86018322', 'FACT001', new DateTimeImmutable('2025-10-01'));
    $record->totalAmount = '100.23';
    // [...]

    // Obtención de la URL
    $service = new QrGenerator();
    $service->setProduction(false); // Para cambiar al entorno de preproducción de la AEAT
    $url = $service->fromRegistrationRecord($record);
    echo "URL: $url\n";
