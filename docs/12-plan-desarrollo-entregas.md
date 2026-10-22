# Plan de desarrollo y entregas del portal Orange

Revisión del 22 de octubre de 2026. La primera entrega propuesta es una versión de preproducción con la estructura y funciones actuales, configurada con cuentas de validación. El cierre funcional de la reunión requiere completar permisos, seguimiento y validación de contenidos. Subir esta versión no equivale a aprobar su paso a producción.

## Criterio de esta revisión

Se contrasta el resumen de reunión facilitado con el código de `local_orangeportal`, el tema `theme_orange`, las guías del repositorio y una lectura de la estructura local. Las comprobaciones anteriores de navegador cubren los cuatro indicadores, idiomas, móvil y perfiles de prueba; la compatibilidad con el campus real todavía requiere una instalación en su copia de preproducción.

Las decisiones posteriores a la reunión se mantienen: menú sin desplegables; cuatro indicadores de Comunicados, Incidencias, Formaciones y Documentos; campana y triángulo rojos cuando hay críticas sin leer; lectura automática sin confirmación manual en ambas publicaciones. No se vuelve a cuatro accesos separados de normal/crítico para cada tipo de publicación.

## Qué está hecho y qué falta

| Bloque | Situación actual | Trabajo pendiente y aceptación |
| --- | --- | --- |
| Navegación y entrada | Biblioteca, Comunicados, Incidencias y Formaciones; entrada a Biblioteca por cohorte; herramientas por capacidades. Tema hijo y acceso común. | Validar con las versiones, tema, autenticación y cohortes reales del campus. Un usuario externo debe conservar su experiencia anterior. |
| Diseño | Marca Orange, anchos comunes, navegación móvil y columnas adaptables. | Validación visual del cliente en escritorio/móvil y con textos reales largos. No equivale a una auditoría completa de accesibilidad. |
| Biblioteca horizontal | Columnas con nombres reales, ruta, selección naranja, listado y apertura del documento. Detalle vacío hasta elegir documento. Búsqueda por nombre/descripción, orden reciente/alfabético y novedades sin leer. | Concretar filtros adicionales: tipo de recurso, fecha o colectivo no son filtros documentales generales ya implementados. |
| Estructura documental | 168 categorías contando la raíz Biblioteca: 166 en la rama RPV y una rama CENTREX sin descendientes. | Revisar contra la matriz definitiva. CENTREX necesita su árbol si debe tener contenidos propios. No presentar su mera presencia como árbol completo. |
| Tipos documentales | Archivo, Página, Libro, Carpeta y URL mediante Moodle. Una Página permite HTML sin adjunto. | Formación carga contenido real y verifica visualización de vídeos y manuales. Etiquetas y otras actividades no forman parte automáticamente del buscador documental. |
| Genially | La inserción se resolvió en local usando un iframe sin el `sandbox` vacío que bloqueaba JavaScript. | Repetir con el enlace real, HTTPS, editor, permisos y políticas del servidor de pre; validar interacción, flujogramas, móvil y ausencia de scroll innecesario. No cargar HTML remoto como texto ni desactivar filtros globalmente. |
| Incidencias | Tabla, filtros laterales de estado, impacto, cliente, producto, fechas y lectura; detalle con título, tabla y descripción; asignaciones y estado común. Agente sin cambios de estado; cerradas solo para seguimiento autorizado. | Confirmar si agentes podrán consultar cerradas en el futuro. Mantener abiertas solamente en esta entrega, según la decisión posterior explícita. |
| Comunicados | Tabla con sobre, título/descripción, categoría y fecha; filtros laterales; publicación por responsables; Impacto editable y lectura automática. | Añadir acceso directo a seguimiento para perfiles de consulta desde la propia sección; actualmente existe el panel general y las herramientas de gestión. |
| Destinatarios | Usuarios Orange, colectivos delegados y grupos de cursos Orange. Asignación múltiple y validación de IDs en servidor. | Crear los colectivos OC, GP y los que confirme el cliente. Validar casos de pertenencia múltiple, traslado y excepciones por centro. |
| Versiones y lectura | Cambios en título, contenido o impacto renuevan lectura. Se conservan lecturas por versión y cambios de estado. Leer no cierra ni retira la publicación. | Decidir si se necesita una foto histórica de destinatarios en cada versión. Hoy los pendientes se calculan con miembros actuales; las lecturas efectivas anteriores se conservan. |
| Indicadores | Cuatro accesos; campana/triángulo rojos y cifra crítica si corresponde; total en ayuda. Novedades documentales/formativas desde la incorporación a Orange. | Validación con usuarios reales. Se actualizan al cargar la página, no en tiempo real. El contenido leído permanece en su listado normal. |
| Administración Orange | Rol delegado, altas manuales, colectivos, contenido e informes dentro de Orange. Cuenta separada del administrador general. | Configurarlo en pre y probar cuentas exclusivas/compartidas. El delegado no asigna perfiles privilegiados: eso sigue en administración general. |
| Formación | Publicación, documentos, cursos, cuestionarios y encuestas nativos. Columnas con Formación inicial y Formación continua. | Los roles de formación de la demo no se crean todos en una instalación nueva. Configurar los perfiles desde Moodle y probar creación y matrícula de usuarios existentes. |
| Coordinación | Consulta de incidencias e informes; no publica ni cambia estados. | Corregido localmente: se retiró `manageoptions` y se sustituyó Profesor sin edición por Consulta formativa Orange. Repetir la configuración documentada en pre; no copiar el rol nativo de profesor como consulta. |
| Calidad y Cliente | Se pueden construir con permisos nativos y capacidades del portal. | Calidad ya tiene rol y cuenta local de validación, sin notas ajenas ni exportación. Falta aprobación de su alcance definitivo; Cliente sigue pendiente de definición. |
| Seguimiento nominal | Panel general de lecturas, pendientes e histórico por publicación; seguimiento específico de incidencias; resumen formativo y enlaces a informes nativos. | Ya hay selector por tipo, resúmenes por publicación y detalle por destinatario/lectura y paginación de 25 filas. Ya hay porcentajes, periodos de 7/30 días/histórico, fechas descendentes y ficha de seguimiento por usuario. El directorio filtra nombre/CE y colectivo/centro mediante cohortes. Falta el filtro de colectivo en cada resumen por publicación y el alcance por equipo. Los filtros de los listados públicos no filtran automáticamente los informes. |
| Alcance de seguimiento | Los datos se limitan al portal Orange. Las notas requieren permiso en el curso. | No hay delimitación completa por equipo de cada coordinador. Los permisos generales de seguimiento pueden mostrar todo Orange. Si se exige equipo propio, desarrollar el filtro de autorización antes de dar acceso. |
| Exportación | CSV básico de usuarios, lecturas y formación; requiere permiso específico; usuarios solo para administración Orange. | Conceder exportación a Formación/Coordinación solo si se autoriza. Los CSV de lectura ya comparten tipo, publicación, destinatario y lectura con sus bloques; faltan campos y formatos definitivos del cliente. |
| Idiomas | Archivos propios en español e inglés, con respaldo inglés para idiomas nuevos. | Sacar textos fijos restantes del código; definir cómo traducir los nombres de catálogos/contenidos si se requieren. |
| Aislamiento y cuentas | La cohorte habilita Orange; roles y colectivo son independientes; un prefijo no concede acceso. | Acordar prefijo/cuentas separadas y probar autenticación real. Cambiar de colectivo conserva cuenta y lecturas, pero no una bitácora propia completa de todas las pertenencias históricas. |

## Estado de los datos que acompañan esta versión

La copia de estructura está en `backups/preproduccion-20261022/`, fuera de Git para conservar el criterio del repositorio sobre material del cliente. La estructura no incluye cursos, documentos, usuarios, matrículas ni lecturas locales.

| Elemento | Copia actual | Decisión de configuración |
| --- | --- | --- |
| Biblioteca | Raíz y 167 descendientes; RPV y CENTREX como ramas separadas. | Confirmar la estructura CENTREX pendiente; mantener la actual para la primera revisión. |
| Formaciones | Raíz local llamada Cursos y dos ramas: Formación inicial/continua. | En pre puede llamarse Formaciones la raíz; no depende del nombre, sino del ID configurado. |
| Estados | Abierta activa, En seguimiento inactivo y Cerrada activa. | La reunión conserva En seguimiento. Activarlo desde Estados e impactos antes de la validación funcional si se ratifica. La copia conserva su estado actual y no lo cambia a escondidas. |
| Impactos | Normal, Alto, Crítico y Bajo, todos activos. | Confirmar si Bajo forma parte del catálogo definitivo. Los identificadores de Crítico y del resto se conservan. |
| Categorías de avisos | Cinco de Incidencias y una de Comunicados. | Son las actuales de la demo, no un catálogo final aprobado. Noticias/Registro no aparecen actualmente; Registro puede crearse al emitir una alerta formativa automática. |

## Orden de entregas

Los plazos son estimaciones de trabajo efectivo, no fechas comprometidas. T0 será el día en que Sistemas confirme versiones, accesos, copia de pre y ventana de instalación. Los bloques se validan antes de ampliar el uso. La fecha histórica del 26 de octubre necesita ratificación; no sustituye estas dependencias.

| Entrega | Alcance y resultado visible | Esfuerzo orientativo | Condición de aceptación |
| --- | --- | --- | --- |
| E0 Preparación de pre | Guía, estructura portable, carga inicial controlada, inventario de archivos y configuración de roles. | Preparada en esta revisión; falta ejecución en el servidor. | Sistemas dispone de código y datos del mismo estado, respaldos y versiones compatibles. |
| E1 Primera versión en pre | Instalar tema/plugin, cargar categorías/catálogos, configurar cohorte y crear cuentas de validación por la web. | 0,5–1 día desde T0. | Orange entra a Biblioteca; externo conserva campus; administrador delegado sin acceso global; listados, lectura y alertas críticas funcionan. Acceso restringido a validadores. |
| E2 Permisos definitivos | Formación, Coordinación y Calidad; Cliente según alcance aprobado. Retirar escritura de consulta y probar combinación de roles, matrículas y exportación. | 1–2 días. | Pruebas negativas: agente no publica/cierra/exporta; coordinación/calidad no modifican; formación no crea cuentas ni administra otros proyectos. |
| E3 Seguimiento y exportación | Filtros nominales/por colectivos/fechas, accesos desde Comunicados e Incidencias, CSV coherente con la selección. Delimitación por equipo si procede. | 2–3 días con seguimiento Orange general; añadir 1–2 días si se exige autorización por equipo. | Mismos usuarios y cantidades en pantalla/CSV; comprobaciones de URL/IDs fuera de ámbito y colectivos múltiples. |
| E4 Contenido y recorrido real | Validar matriz/CENTREX, contenido de muestra aprobado, Genially, formación inicial/continua, test y encuesta. | 1–2 días técnicos, más carga del equipo de Formación. | Un agente recorre categorías, abre Genially, recibe un comunicado/incidencia y completa un test; responsable consulta resultados autorizados. |
| E5 Cierre y entrega | Textos pendientes, revisión móvil/accesibilidad, ensayo de actualización/reversión, capturas por bloque y correcciones finales. | 1–2 días, más validación del cliente. | Registro de resultados, límites aceptados, documentación vigente y aprobación para solicitar producción. |

La configuración local de Coordinación/Calidad de E2 ya está aplicada y tiene pruebas específicas; la definición de Cliente y el traslado/validación en pre siguen pendientes. Ver [Perfiles de consulta](14-perfiles-consulta.md).

E1 permite probar ya el portal. No debe anunciarse como cumplimiento íntegro de la reunión mientras E2/E3 o las decisiones de alcance sigan pendientes. Como orientación, el cierre necesita aproximadamente 6–10 jornadas técnicas desde T0, más el alcance adicional por equipos y los tiempos de Sistemas, contenido y cliente. Las capturas se entregan por bloque al interlocutor del proyecto; no se envían automáticamente a terceros.

## Decisiones que necesitamos cerrar

1. Pre será una copia del campus existente. Moodle 4.5.1 Build 20241209, PHP 8.3.6 y MySQL 8.4.8 están informados; falta confirmar Moove, autenticación, URL y fecha objetivo. Si falta Moove, instalarlo como dependencia sin cambiar el tema global.
2. Aprobar que seguimiento vea todo Orange o definir relación coordinador/equipo, usuarios con varios colectivos y sustituciones.
3. Definir Calidad y Cliente: contenido, notas, histórico y exportación autorizados para cada uno.
4. Aprobar matriz definitiva, estructura CENTREX, filtros de Biblioteca y nombre Formaciones/Formación.
5. Ratificar En seguimiento activo y catálogo de impactos, incluyendo Bajo.
6. Acordar cuentas exclusivas o compartidas, prefijo y qué significa conservar pertenencias al cambiar de colectivo: historial auditable o mantenimiento del resto de grupos actuales.
7. Confirmar campos de informes, histórico de destinatarios y datos que deben aparecer en CSV.

## Fuera de esta primera versión

Teams y Power Automate permanecen aparcados. La sincronización bidireccional con SharePoint no se incorpora. Detección automática de bajo rendimiento, planes de acción e informes definitivos no se consideran entregados sin requisitos y aceptación propios.

## Documentación para ejecutar la primera entrega

Usar [Despliegue a preproducción](13-despliegue-preproduccion.md) para instalar, cargar estructura, crear usuarios por la web, configurar perfiles, validar y revertir. La carga completa de manuales y formaciones corresponde al equipo de Formación; el desarrollo entrega estructura, ejemplos de validación y herramientas.

## Ampliación acordada: estadísticas por usuario

1. **Disponible en local:** porcentajes y fracciones, pendientes antes de leídos, fecha de versión descendente, periodos móviles 7/30 días/histórico.
2. **Disponible en local:** Seguimiento → Usuarios y ficha de resumen, incidencias, comunicados, formación e identificación de encuestas no anónimas, reutilizando datos y permisos nativos. Centro por pertenencia a colectivos/cohortes (Cristalia-Madrid, Centro-Valencia); CE en Número de ID.
3. **Siguiente entrega:** definir código/edición/centro/modalidad/duración de las acciones formativas mediante campos nativos cuando sea posible, y registro explícito de asistencia. El Excel mezcla ediciones, personas e intentos: no importar filas como si fueran cursos únicos.
4. **Posterior:** satisfacción agregada por formación/formador e incorporación opcional del histórico, previa normalización y filtrado Orange. El fichero de encuestas no identifica agentes y no puede usarse para atribuir respuestas individuales.

Esta ampliación no cambia los permisos de Calidad ni concede a Coordinación acceso a formaciones no autorizadas. Los indicadores reflejan destinatarios y matrículas actuales; no reconstruyen retrospectivamente colectivos o asignaciones. Ver [Informes](09-informes.md).
