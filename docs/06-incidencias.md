# Publicar y seguir incidencias

Las incidencias son avisos operativos publicados por responsables autorizados. No son tickets de soporte creados por cualquier usuario. Se crean y asignan desde **Administración > Portal Orange > Publicar y gestionar avisos**.

## Crear categorías

1. Abrir **Portal Orange > Categorías de incidencias y notificaciones**.
2. Elegir Incidencias.
3. Escribir el nombre y seleccionar la categoría superior; guardar.

Se pueden crear subcategorías, renombrarlas y moverlas. El árbol impide mover un nodo dentro de sus propios descendientes. Los ejemplos iniciales son Sistemas > Centrex y Red móvil.

## Publicar y asignar

1. Abrir **Publicar y gestionar avisos > Incidencias > Crear publicación**.
2. Completar título, categoría, descripción, impacto y estado.
3. Elegir usuarios individuales, cohortes globales y/o grupos de cursos.
4. Marcar Publicado y guardar. Sin esa marca, permanece como borrador.

La asignación es la unión de los destinatarios: basta pertenecer a uno de los grupos o cohortes seleccionados, o estar asignado individualmente. No se permite publicar sin destinatarios. El usuario solo ve sus incidencias y los contadores calculan esas mismas incidencias, incluidas las de las subcategorías.

## Estado compartido

Los estados iniciales son Abierta, En seguimiento y Cerrada. Un destinatario con la capacidad de cambiar estado puede actualizarlo desde el detalle. El cambio se aplica a la incidencia completa y queda registrado con usuario y fecha. Los destinatarios restantes ven el nuevo estado.

## Recibido, leído y confirmado

**Recibido** se registra al mostrar el aviso en el listado del portal. **Leído** se registra al abrirlo. **Confirmado** exige pulsar **Confirmar que he leído este aviso**. Estos estados son individuales y no se confunden con el estado compartido de la incidencia.

Si un responsable cambia el título o el contenido, aumenta la versión y se exige una nueva confirmación. Se conservan el texto y las confirmaciones anteriores, consultables desde los informes. Cambiar únicamente el estado no invalida la confirmación de lectura.

Un responsable que abre una vista de gestión sin ser destinatario no se cuenta como receptor ni lector. Un borrador tampoco registra lecturas de destinatarios.

## Modificar o retirar

En el listado de gestión, elegir Editar. Para retirar un aviso sin borrar su histórico, desmarcar Publicado. Cambiar los destinatarios modifica el acceso de acuerdo con la pertenencia actual a sus colectivos.

La presentación y este flujo utilizan el plugin local. La actividad Base de datos de Moodle permite almacenar fichas y controlar permisos, pero no proporciona este circuito global de destinatarios mixtos, estado compartido auditado y tres marcas de lectura por versión.
