Comunicación con la AEAT
========================

Para comunicarnos con el servidor de la AEAT, al que deberemos enviar los registros de facturación obligatoriamente si nuestro SIF opera en el modo VERI*FACTU, la librería proporciona la clase :php:class:`josemmo\Verifactu\Services\AeatClient`.

Envío de registros de facturación
---------------------------------

Crea una instancia de :php:class:`josemmo\Verifactu\Models\ComputerSystem` con los datos de tu SIF, y prepara un :php:class:`josemmo\Verifactu\Models\Records\FiscalIdentifier` con el nombre y NIF del emisor o responsable tributario.
Debes indicar la ruta hacia el fichero PFX que contiene tu certificado de la Fábrica Nacional de Moneda y Timbre (FMNT) o entidad similar autorizada por la AEAT para poder comunicarte con su servidor.

.. code-block:: php

    use josemmo\Verifactu\Models\ComputerSystem;
    use josemmo\Verifactu\Models\Records\FiscalIdentifier;
    use josemmo\Verifactu\Services\AeatClient;

    // Define los datos del SIF
    $system = new ComputerSystem();
    $system->vendorName = 'Perico de los Palotes, S.A.';
    $system->vendorNif = 'A00000000';
    $system->name = 'Test SIF';
    $system->id = 'XX';
    $system->version = '0.0.1';
    $system->installationNumber = 'ABC0123';
    $system->onlySupportsVerifactu = true;
    $system->supportsMultipleTaxpayers = true;
    $system->hasMultipleTaxpayers = false;
    $system->validate();

    // Define el responsable tributario
    $taxpayer = new FiscalIdentifier('Perico de los Palotes, S.A.', 'A00000000');

    // Crea el AeatClient
    $client = new AeatClient($system, $taxpayer);
    $client->setCertificate(__DIR__ . '/ruta/certificado.pfx', 'contraseña');
    $client->setProduction(false); // Para cambiar al entorno de preproducción de la AEAT


Envía los registros a la AEAT (hasta un máximo de 1000 por llamada):

.. code-block:: php

    $aeatResponse = $client->send([$record])->wait();

Procesa la respuesta.
En caso de un envío correcto de registros de facturación, la respuesta contendrá un Código Seguro de Verificación (CSV):

.. code-block:: php

    use josemmo\Verifactu\Models\Responses\ResponseStatus;

    if ($aeatResponse->status === ResponseStatus::Correct) {
        echo "Accepted: " . $aeatResponse->csv . "\n";
    } else {
        echo "Rejected: " . $aeatResponse->items[0]->errorDescription . "\n";
    }

Requerimiento de información
----------------------------

En caso de que el SIF opere en modo "No VERI*FACTU", la AEAT puede solicitar un requerimiento de información.
Esta funcionalidad se ve implementada en el método :php:method:`josemmo\Verifactu\Services\AeatClient::setRequirementReference()`:

.. code-block:: php

    // Para enviar la primera página de registros
    $client->setRequirementReference('REF00001ABDEAF1234');

    // Para enviar la última página de registros (fin del requerimiento)
    $client->setRequirementReference('REF00001ABDEAF1234', true);

    // Para desactivar el modo de envío de requirimiento de informacióna
    $client->setRequirementReference(null);
