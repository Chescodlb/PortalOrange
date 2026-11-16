# Gestión del portal Orange desde Moodle

El cliente puede gestionar la marca, los documentos, la formación y los destinatarios desde la administración de Moodle. Las incidencias y comunicaciones utilizan páginas del plugin local integradas en esa misma administración.

El portal local está en http://localhost:8080. Los usuarios ficticios y la revisión visual se describen en [Pruebas y revisión](08-pruebas-y-revision.md). Los cambios posteriores al commit inicial quedan pendientes de aprobación; no se harán commits ni push automáticamente.

| Caso | Guía | Cómo se gestiona |
| --- | --- | --- |
| Logo, colores y tipografía | [Marca](01-marca.md) | Tema hijo Orange y ajustes nativos de tema/cohorte |
| Menú y sección de entrada | [Navegación](02-navegacion.md) | Configuración Moodle y hooks del plugin |
| Roles, permisos y colectivos | [Usuarios y permisos](03-usuarios-y-permisos.md) | Usuarios, roles, cohortes y grupos nativos |
| Árbol RPV/CENTREX y documentos | [Biblioteca](04-biblioteca.md) | Categorías, cursos y recursos nativos |
| Cursos, evaluaciones y encuestas | [Formación](05-cursos-y-encuestas.md) | Actividades y libro de calificaciones nativos |
| Categorías, asignación y estado | [Incidencias](06-incidencias.md) | Portal Orange dentro de administración |
| Noticias, Registro y Comunicado | [Notificaciones](07-notificaciones.md) | Portal Orange y mensajería nativa |
| Lecturas y resultados | [Informes](09-informes.md) | Informes nativos y panel de trazabilidad |
| Avisos externos de contenido | [Integración con Teams](10-integracion-teams.md) | Power Automate configurable y envíos mediante la cola de Moodle |

La [decisión de implementación](implementacion.md) explica qué necesita código y por qué. Ningún archivo original de Moodle o Moove se ha modificado.

- [Indicadores personales, contadores y fecha de incorporación](10-indicadores-personales.md).

- [Administrador de Orange: cuentas, colectivos, contenidos y exportación](11-administrador-orange.md).

- [Revisión de la reunión y plan de desarrollo y entregas](12-plan-desarrollo-entregas.md).
- [Despliegue a preproducción, carga de estructura y configuración por la web](13-despliegue-preproduccion.md).

- [Perfiles de consulta de Coordinación y Calidad y configuración desde Moodle](14-perfiles-consulta.md).

- [Replicar perfiles y permisos Orange paso a paso, matriz y XML](15-replicar-perfiles-y-permisos.md).

- Manual para operadores: [Usuarios, permisos y colectivos en PDF](manuales/Guia_usuarios_permisos_y_colectivos_Orange.pdf) y [Word editable](manuales/Guia_usuarios_permisos_y_colectivos_Orange.docx).

- Primera instalación en producción e incidencias: [PDF](manuales/Guia_despliegue_produccion_Orange.pdf), [Word editable](manuales/Guia_despliegue_produccion_Orange.docx) y [comandos en una línea](manuales/Comandos_despliegue_Orange.txt).

- [Actividades y recursos por categoría/publicación, colecciones formativas y actualización](16-actividades-recursos-y-colecciones.md).


## Manuales por perfil — 6 de noviembre de 2026

Entrega basada en Portal Orange `2026110601`, revisión `48461c2`. Cada manual está disponible en DOC, DOCX y PDF; incluye procedimientos diarios, permisos y resolución de dudas. El manual general cubre la configuración completa en un ambiente nuevo y la actualización de uno existente.

- [Descargar los cinco manuales y material de apoyo](manuales/20261106/Manuales_Orange_20261106.zip).
- Agentes Orange: [DOC](manuales/20261106/01_Agentes_Orange.doc), [DOCX](manuales/20261106/01_Agentes_Orange.docx), [PDF](manuales/20261106/01_Agentes_Orange.pdf).
- Coordinadores Orange: [DOC](manuales/20261106/02_Coordinadores_Orange.doc), [DOCX](manuales/20261106/02_Coordinadores_Orange.docx), [PDF](manuales/20261106/02_Coordinadores_Orange.pdf).
- Formadores Orange: [DOC](manuales/20261106/03_Formadores_Orange.doc), [DOCX](manuales/20261106/03_Formadores_Orange.docx), [PDF](manuales/20261106/03_Formadores_Orange.pdf).
- Administradores Orange: [DOC](manuales/20261106/04_Administradores_Orange.doc), [DOCX](manuales/20261106/04_Administradores_Orange.docx), [PDF](manuales/20261106/04_Administradores_Orange.pdf).
- Administradores generales de Moodle: [DOC](manuales/20261106/05_Administradores_Generales_Moodle.doc), [DOCX](manuales/20261106/05_Administradores_Generales_Moodle.docx), [PDF](manuales/20261106/05_Administradores_Generales_Moodle.pdf).

El contenido fuente está en `manuales/20261106/fuentes/contenido_manuales.py`. Los generadores y el validador están en `scripts/generar-manuales-orange.py`, `scripts/generar-manuales-pdf-orange.mjs` y `scripts/validar-manuales-orange.py`. Son herramientas documentales locales, no scripts de Moodle ni de despliegue.

Manual de configuración y revisión de Teams para servidores de preproducción y producción: [PDF](manuales/teams-20261110/Manual_configuracion_y_revision_Teams_Orange.pdf) · [Word](manuales/teams-20261110/Manual_configuracion_y_revision_Teams_Orange.docx). Incluye PHP CLI, cron, configuración por ambiente, cola, logs y resolución de incidencias.

Manual completo del pase a producción: [PDF](manuales/produccion-20261110/Manual_pase_a_produccion_Portal_Orange.pdf) · [Word](manuales/produccion-20261110/Manual_pase_a_produccion_Portal_Orange.docx). Incluye alternativas por consola y portal, carga inicial o actualización, Biblioteca y Formaciones, perfiles, Teams y cron.

[Configuración inicial automatizada por consola](17-configuracion-automatica.md): raíces/cohorte por Número ID, PHP CLI, tema, Teams y catálogo inicial desde un archivo privado.
