# Cómo contribuir

Gracias por tu interés en contribuir a Verifactu-PHP.

Este es un proyecto de código abierto que implementa VERI\*FACTU, una especificación con consecuencias directas en el ámbito tributario.
El objetivo principal es garantizar su **mantenibilidad** a largo plazo con una base de código limpia y estable.

Esta librería se mantiene de forma altruista.
Por favor, respeta el tiempo de los colaboradores del proyecto: no envíes PRs de baja calidad o que ignoren las normas de este documento.

## Alcance de las contribuciones

* Los PRs que solucionan fallos (**bugfixes**) siempre tienen prioridad sobre el resto.
* No se aceptan PRs con funcionalidades nicho o específicas para una sola empresa. El proyecto solo busca implementar la especificación de VERI\*FACTU.

## Requisitos

Los PRs que no cumplan las siguientes normas serán **rechazadas automáticamente**:

1.  **Linting:** El código debe pasar las reglas de estilo de Laravel Pint definidas en [pint.json](pint.json).\
    Comando: `composer lint`

2.  **Análisis estático:** El código debe pasar las comprobaciones de PHPStan configuradas en [phpstan.neon](phpstan.neon).\
    Comando: `composer stan`

3.  **Test unitarios:** Toda contribución debe incluir los tests que cubran el cambio o la nueva funcionalidad.\
    Comando: `composer test`

Debes verificar tu código en local usando estos comandos antes de enviar el PR.

## Convenciones de idioma

- El código fuente (incluyendo nombres de variables, funciones y comentarios) y las descripciones de los commits debe escribirse en **inglés**.
- La documentación, el título y la descripción del PR deben estar en **castellano**.
