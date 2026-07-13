# Informe de refactorización e integración continua

**Proyecto:** Sistema de información Ferretería Shamullo  
**Repositorio:** https://github.com/SamStorm2003/ferreteria_shamullo.git  
**Rol asumido:** Software Engineer y DevOps Engineer  
**Fecha:** 13 de julio de 2026

## Introducción

Este informe presenta la revisión y mejora del sistema de información de la Ferretería Shamullo. El sistema fue desarrollado para apoyar tareas importantes de una ferretería, como el registro de productos, control de inventario, compras, ventas, clientes, proveedores, promociones, facturas, reembolsos y reportes.

La revisión se hizo sobre el código real del proyecto. La idea principal no fue cambiar lo que el sistema hace para el usuario, sino ordenar mejor algunas partes internas para que el proyecto sea más fácil de entender, mantener y seguir mejorando.

También se preparó un flujo básico de Integración Continua. Dicho de forma sencilla, esto significa que el proyecto puede revisarse automáticamente cuando se suben cambios al repositorio, ayudando a detectar errores antes de que lleguen a la versión principal.

## Parte 1. Diagnóstico del código

Se revisaron varias áreas del sistema, especialmente los archivos relacionados con ventas, facturación, chatbot y configuración del repositorio. Durante esta revisión se encontraron oportunidades de mejora en organización, tamaño de métodos, repetición de lógica, nombres poco claros y ausencia de revisión automática.

| Problema | Ubicación | Riesgo | Mejora propuesta |
|---|---|---|---|
| Un archivo de ventas tenía demasiadas tareas juntas: pantalla de ventas, reglas de venta y generación de factura. | `app/Filament/Vendedor/Resources/VentaResource.php` | Si se modificaba la facturación, podía afectarse la pantalla de ventas. Además, el archivo era más difícil de leer. | Separar la generación de facturas en un archivo propio. |
| El chatbot tenía muchos `if` seguidos para reconocer preguntas del usuario. | `app/Http/Controllers/Api/ChatController.php` | Cada nueva pregunta hacía crecer el método y aumentaba el desorden. | Organizar las preguntas en una lista de intenciones más fácil de ampliar. |
| Había un número fijo escrito directamente en el código para el límite diario de consultas a Gemini. | `ChatController.php` | Si se quería cambiar ese límite, había que buscar el número dentro del método. | Crear una constante con un nombre claro. |
| Existían variables calculadas que luego no se usaban. | `ChatController.php` | Confundían al revisar el código, porque parecía que cumplían una función pero no afectaban el resultado. | Eliminar esas variables para dejar el método más limpio. |
| Algunos textos del proyecto tenían problemas de acentos o caracteres extraños. | Varios archivos del sistema | Puede verse mal en pantalla y dificulta corregir textos. | Hacer una limpieza posterior de textos y codificación. |
| No había un flujo automático para revisar pruebas, sintaxis y compilación. | No existía `.github/workflows/ci.yml` | Los errores podían descubrirse tarde, después de subir cambios importantes. | Crear un flujo de revisión automática con GitHub Actions. |

## Parte 2. Refactorización aplicada

Refactorizar significa mejorar el orden interno del código sin cambiar el funcionamiento que ve el usuario. En este proyecto se aplicaron varias mejoras concretas.

### 1. Separar la facturación en una clase propia

**Técnica aplicada:** Extract Class  
**Archivo antes:** `VentaResource.php`  
**Archivo nuevo:** `app/Services/Facturacion/FacturaVentaService.php`

Antes, el archivo de ventas también preparaba y enviaba los datos de la factura. Eso hacía que el archivo creciera demasiado y mezclara cosas distintas.

**Antes:**

```php
->action(function ($record) {
    $cliente = $record->clienteUsuario ?? $record->clienteExterno;
    $detalles = $record->detalles->map(function ($detalle) {
        $producto = Producto::with('promocion')->find($detalle->idProducto);
        ...
    })->toArray();

    $response = Http::post(config('app.arkfacture_api_url'), $data);
    ...
})
```

**Después:**

```php
->action(function ($record) {
    return app(FacturaVentaService::class)->generarDesdeVenta($record);
})
```

Ahora la pantalla de ventas solo llama al servicio de facturación. La preparación de la factura quedó en un lugar más adecuado.

**Principio mejorado:** SOLID y Clean Code. En palabras simples, cada parte queda encargada de una tarea más clara.

### 2. Ordenar la forma en que responde el chatbot

**Técnica aplicada:** Extract Method y Remove Duplicate Code  
**Archivo:** `ChatController.php`

Antes, el chatbot revisaba el mensaje con muchas condiciones seguidas. Funcionaba, pero era menos cómodo de mantener.

**Antes:**

```php
if (str_contains($mensaje, 'compras') || str_contains($mensaje, 'compra')) {
    return $this->analizarCompras();
}

if (str_contains($mensaje, 'producto')) {
    return $this->consultarProductos($mensaje);
}
```

**Después:**

```php
foreach ($this->intenciones() as $intencion) {
    if (($intencion['aplica'])($mensaje)) {
        return ($intencion['responde'])($mensaje);
    }
}
```

También se agregó un método para buscar palabras clave:

```php
private function contiene(string $mensaje, array $palabrasClave): bool
{
    foreach ($palabrasClave as $palabraClave) {
        if (str_contains($mensaje, $palabraClave)) {
            return true;
        }
    }

    return false;
}
```

Con esto, agregar una nueva respuesta al chatbot es más ordenado.

**Principio mejorado:** DRY y KISS. Es decir, se repite menos código y se mantiene simple.

### 3. Cambiar un número fijo por un nombre claro

**Técnica aplicada:** Rename Variable / Replace Magic Number with Constant  
**Archivo:** `ChatController.php`

Antes, el límite diario de consultas estaba como un número directo:

```php
$limiteDiario = 10;
```

Después quedó con un nombre claro:

```php
private const LIMITE_DIARIO_GEMINI = 10;
```

Y se usa de esta forma:

```php
if ($consultasRealizadas >= self::LIMITE_DIARIO_GEMINI) {
    ...
}
```

Esto hace que el código se entienda mejor. Ya no se ve solo el número `10`, sino que se sabe que representa el límite diario de consultas.

**Principio mejorado:** Clean Code. Los nombres ayudan a entender el propósito de cada dato.

### 4. Eliminar código que no aportaba

**Técnica aplicada:** Remove Dead Code  
**Archivo:** `ChatController.php`

Se quitaron variables que se calculaban, pero no se usaban después.

**Antes:**

```php
$dias_periodo = 90;
$semanas_periodo = $dias_periodo / 7;
$unidades_semanales = ...
```

**Después:** esas líneas fueron eliminadas.

Esto deja el método más limpio y evita confusiones al momento de revisar el sistema.

**Principio mejorado:** KISS y Clean Code.

## Parte 3. Integración Continua

Para el proyecto se diseñó e implementó un flujo básico de Integración Continua usando GitHub Actions.

**Archivo creado:**

```text
.github/workflows/ci.yml
```

### Repositorio Git

El repositorio usado para el proyecto es:

```text
https://github.com/SamStorm2003/ferreteria_shamullo.git
```

Se actualizó el `origin` local para apuntar a ese repositorio:

```text
origin https://github.com/SamStorm2003/ferreteria_shamullo.git
```

### Estrategia de trabajo

Se recomienda usar **GitHub Flow**, porque es una forma sencilla de trabajar:

1. Se crea una rama para un cambio.
2. Se hacen los ajustes necesarios.
3. Se crea un commit con un mensaje claro.
4. Se suben los cambios a GitHub.
5. Se revisa que el flujo automático pase correctamente.
6. Se une el cambio a la rama principal.

### Flujo automático propuesto

El flujo realiza estas tareas:

| Etapa | Qué hace |
|---|---|
| Descargar código | Toma el proyecto desde GitHub. |
| Preparar PHP | Prepara el entorno para Laravel. |
| Preparar Node | Prepara el entorno para compilar la parte visual. |
| Instalar dependencias | Instala lo necesario para que el sistema funcione. |
| Revisar sintaxis | Busca errores básicos en archivos PHP. |
| Ejecutar pruebas | Corre las pruebas del sistema. |
| Compilar frontend | Verifica que la parte visual pueda generarse. |
| Revisar calidad | Deja preparado SonarQube si se configura un token. |
| Despliegue simulado | Simula que el sistema pasó a una etapa de entrega. |

### Diagrama del flujo

```text
Commit o Push
     |
     v
GitHub Actions
     |
     v
Instalar dependencias
     |
     v
Revisar sintaxis
     |
     v
Ejecutar pruebas
     |
     v
Compilar frontend
     |
     v
Análisis de calidad
     |
     v
Despliegue simulado
```

### Evidencia de commit y push

Se realizó un commit descriptivo:

```text
99d4798 refactor: extraer facturacion y agregar pipeline ci
```

También se realizó el push al repositorio correcto:

```text
To https://github.com/SamStorm2003/ferreteria_shamullo.git
36270d2..99d4798  main -> main
```

Esto cumple con la parte de GitHub solicitada en la actividad.

### Evidencia de pruebas

Se ejecutó el comando:

```text
composer test
```

Resultado:

```text
Tests: 6 passed (14 assertions)
```

También se compiló la parte visual con:

```text
npm.cmd run build
```

Resultado: la compilación terminó correctamente.

## Parte 4. Reflexión técnica

### ¿Qué problemas encontró en su código?

Se encontraron archivos con demasiadas tareas juntas, métodos largos, condiciones repetidas, variables sin uso y falta de una revisión automática antes de subir cambios. También se vio que algunos textos tenían problemas de codificación.

### ¿Qué técnica de refactorización aportó mayor beneficio?

La mejora que más aportó fue separar la facturación en una clase propia. Esto hizo que el archivo de ventas quedara más pequeño y más fácil de entender.

### ¿Qué principio de diseño aplicó?

Se aplicó principalmente la idea de que cada parte del sistema debe encargarse de una tarea clara. También se buscó que el código sea simple, entendible y con menos repetición.

### ¿Cómo contribuiría la Integración Continua al mantenimiento?

La Integración Continua ayuda porque revisa el proyecto automáticamente cada vez que se suben cambios. Si aparece un error, se puede detectar antes de que afecte a la versión principal. Esto es importante porque el sistema tiene varias áreas conectadas: ventas, compras, inventario, facturas, reportes y usuarios.

### ¿Qué norma internacional respalda estas prácticas?

Estas prácticas se relacionan con:

- **ISO/IEC/IEEE 12207:** mantenimiento y evolución del software.
- **ISO/IEC 25010:** calidad del producto y facilidad para mantenerlo.
- **ISO/IEC 5055:** revisión de la calidad interna del código.
- **IEEE 730:** aseguramiento de la calidad del software.
- **IEEE 828:** control de versiones y configuración.

## Conclusión

El trabajo realizado permitió mejorar el proyecto sin cambiar su funcionamiento principal. Se ordenó la facturación, se simplificó el chatbot, se eliminaron partes innecesarias y se preparó un flujo automático para revisar el sistema.

Además, el proyecto quedó vinculado y actualizado en el repositorio correcto de GitHub:

```text
https://github.com/SamStorm2003/ferreteria_shamullo.git
```

Con estos cambios, el sistema de Ferretería Shamullo queda mejor preparado para seguir creciendo y para recibir futuras mejoras con menos riesgo.
