Introducción
============

Verifactu-PHP es una librería sencilla escrita en PHP que permite generar registros de facturación según el sistema  `VERI*FACTU <https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu.html>`__ y posteriormente enviarlos telemáticamente a la Agencia Tributaria (AEAT).

Instalación
-----------

Asegúrate de que tu entorno de ejecución cumple los siguientes requisitos:

* PHP 8.2 o superior
* libXML

Puedes instalar la librería utilizando el gestor de dependencias Composer:

.. code-block:: shell-session

    $ composer require josemmo/verifactu-php

Qué es VERI*FACTU
-----------------

VERI*FACTU es la regulación española que obliga a registrar la información básica de cada factura emitida en un **Sistema Informático de Facturación (SIF)**, garantizando su trazabilidad y fiabilidad.
No se trata de una factura electrónica completa, sino de un **registro de facturación** que incluye datos resumidos de la factura.

Se aplica a todas las personas físicas o jurídicas sujetas al IRPF (actividad económica), Impuesto de Sociedades o Renta de no Residentes (establecimiento permanente en España) con domicilio fiscal en régimen común español.
Aún así, existen dos excepciones a esta obligación:

* Aquellos adscritos al Suministro Inmediato de Información (SII), que ya envían facturas diariamente a la AEAT.
* Operaciones que no requieran factura según la normativa o que cuenten con autorizaciones especiales de facturación.

El objetivo del reglamento es *"garantizar la integridad, conservación, accesibilidad, legibilidad, trazabilidad e inalterabilidad"* (`RD 1007/2023 <https://www.boe.es/buscar/act.php?id=BOE-A-2023-24840>`__) de estos registros de facturación.
En otras palabras, se busca evitar el fraude a través de una contabilidad B.
Para ello, los registros incluyen un hash encadenado que une cada registro con el anterior.

Modalidades de cumplimiento
---------------------------

Existen dos formas válidas de adaptarse a VERI*FACTU:

* **VERI*FACTU**: El SIF debe enviar cada registro de alta de factura a la Sede Electrónica de la AEAT inmediatamente tras su emisión.
* **No VERI*FACTU**: El SIF no envía los registros a la AEAT, pero exige la firma electrónica de cada registro y la conservación de un libro de eventos análogo. En esta modalidad, la AEAT puede solicitar enviar algunos registros a través de un requirimiento.
