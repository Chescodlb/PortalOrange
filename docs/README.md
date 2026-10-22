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
| Avisos externos de incidencias | [Integración con Teams](10-integracion-teams.md) | Evaluación de plugins y configuración de Microsoft pendiente |

La [decisión de implementación](implementacion.md) explica qué necesita código y por qué. Ningún archivo original de Moodle o Moove se ha modificado.

- [Indicadores personales, contadores y fecha de incorporación](10-indicadores-personales.md).

- [Administrador de Orange: cuentas, colectivos, contenidos y exportación](11-administrador-orange.md).

- [Revisión de la reunión y plan de desarrollo y entregas](12-plan-desarrollo-entregas.md).
- [Despliegue a preproducción, carga de estructura y configuración por la web](13-despliegue-preproduccion.md).

- [Perfiles de consulta de Coordinación y Calidad y configuración desde Moodle](14-perfiles-consulta.md).

- [Replicar perfiles y permisos Orange paso a paso, matriz y XML](15-replicar-perfiles-y-permisos.md).
