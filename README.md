# GLS Package Pickup System

Sistema completo de gestión de recogidas de paquetes desarrollado en PHP con base de datos SQLite.

## Características

- **Panel Cliente**: Acceso mediante token único, solicitud de recogidas con múltiples destinatarios
- **Panel Repartidor**: Interfaz táctil optimizada para tablets/móviles, gestión de rutas y estados
- **Panel Administrador**: Gestión completa de recogidas, clientes, repartidores y configuración
- **Sistema de Etiquetas**: Generación automática con códigos de barras GLS y del sistema
- **Estados de Recogida**: Flujo completo desde solicitud hasta finalización
- **Historial Completo**: Seguimiento de todos los cambios de estado
- **Responsive Design**: Adaptado a escritorio, tablet y móvil

## Instalación

1. Subir archivos al servidor web con PHP 7.4+
2. Configurar permisos de escritura en `/database/`
3. Ejecutar inicialización: `php database/add_sample_data.php`
4. Acceder mediante navegador web

## Credenciales de Acceso

### Administrador
- **URL**: `/login.php` 
- **Usuario**: `admin`
- **Contraseña**: `admin123`

### Repartidor
- **URL**: `/login.php`
- **Usuario**: `repartidor1` 
- **Contraseña**: `delivery123`

### Cliente Demo
- **URL**: `/cliente/login.php`
- **Token**: `c827a682d283ee7998fc5977e9584cb9`

## Estados de Recogida

1. **Pendiente de Confirmar**: Solicitud inicial del cliente
2. **Confirmada**: Oficina confirma la recogida
3. **Sin Asignar**: Confirmada pero sin repartidor asignado
4. **Asignada**: Asignada a un repartidor específico
5. **En Ruta**: Repartidor ha iniciado la ruta
6. **Hecho**: Recogida completada exitosamente
7. **No Mercancía**: No había paquetes para recoger
8. **Incidencia**: Problema durante la recogida
9. **Vehículo No Apropiado**: Vehículo inadecuado para la recogida

## Estructura del Sistema

```
/cliente/          - Panel de cliente (token-based auth)
/repartidor/       - Panel de repartidor (touch-optimized)
/administrador/    - Panel de administración completo
/includes/         - Funciones y configuración compartida
/assets/           - CSS, JavaScript e imágenes
/database/         - Base de datos SQLite y scripts
```

## Funcionalidades Principales

### Cliente
- Solicitar recogidas con múltiples paquetes
- Ver historial de recogidas
- Imprimir etiquetas con códigos de barras
- Dashboard con estadísticas personales

### Repartidor  
- Ver recogidas asignadas
- Actualizar estados con interfaz táctil
- Acceso directo a teléfonos y direcciones
- Auto-actualización de estado

### Administrador
- Dashboard con estadísticas generales
- Gestión completa de recogidas (confirmar, asignar)
- Administración de clientes y tokens
- Gestión de repartidores
- Configuración del sistema
- Herramientas de mantenimiento y backup

## Tecnologías

- **Backend**: PHP 7.4+
- **Base de Datos**: SQLite 3
- **Frontend**: HTML5, CSS3, JavaScript vanilla
- **Códigos de Barras**: JsBarcode library
- **Diseño**: Responsive CSS (Mobile-first)

## Log de Desarrollo

Ver archivo `Log.dm` para historial detallado de modificaciones y creación de archivos.
