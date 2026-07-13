# Sprint de Refactorizacion e Integracion Continua

Proyecto analizado: `ferreteria_shamullo`, sistema Laravel 12 con Filament, Vue/Vite y modulos de ventas, inventario, compras, reportes y chatbot.

## 1. Diagnostico del codigo

| Problema | Ubicacion | Riesgo | Mejora propuesta |
|---|---|---|---|
| Clase de recurso demasiado grande, con formulario, tabla, reglas de negocio y facturacion en el mismo archivo. | `app/Filament/Vendedor/Resources/VentaResource.php` tenia 851 lineas. | Baja mantenibilidad, cambios de facturacion pueden romper la UI de ventas. | Extraer la generacion de facturas a un servicio especializado. |
| Metodo con cadena larga de condiciones para interpretar mensajes del chatbot. | `app/Http/Controllers/Api/ChatController.php`, metodo `procesarMensaje`. | Dificulta agregar nuevas intenciones y aumenta la complejidad ciclomática. | Extraer tabla de intenciones y metodo auxiliar de busqueda por palabras clave. |
| Numero magico para limite diario de Gemini. | `ChatController.php`, limite `10` dentro de `consultarGeminiConContexto`. | El valor queda escondido y se duplica conceptualmente con los mensajes al usuario. | Reemplazar por constante `LIMITE_DIARIO_GEMINI`. |
| Variables calculadas sin uso real. | `ChatController.php`, variables de periodo semanal antes del prompt Gemini. | Ruido en lectura y posible confusion sobre reglas de negocio inexistentes. | Eliminar variables muertas. |
| Codificacion inconsistente en textos visibles. | Varios archivos muestran textos como `InformaciÃ³n`, `TelÃ©fono`, `mÃ¡s`. | Mala experiencia visual y dificultad para buscar textos en el codigo. | Normalizar archivos a UTF-8 y corregir textos en una tarea separada. |
| Pipeline CI inexistente. | No existia `.github/workflows/ci.yml`. | Los errores de sintaxis, pruebas y build se detectan tarde. | Crear flujo CI para PHP, Node, tests, build, analisis y despliegue simulado. |

## 2. Refactorizaciones aplicadas

### Refactorizacion 1: Extract Class

Antes, `VentaResource` hacia todo dentro de la accion `generate_invoice`: validaba `.env`, armaba datos de factura, llamaba la API Arkfacture, interpretaba errores y descargaba el PDF.

Fragmento antes:

```php
->action(function ($record) {
    $apiUrl = config('app.arkfacture_api_url');
    $missingConfig = collect([...])->filter(fn($value) => blank($value));
    $detalles = $record->detalles->map(function ($detalle) { ... });
    $response = Http::timeout(30)->post($apiUrl, $data);
    ...
})
```

Despues:

```php
->action(function ($record) {
    return app(FacturaVentaService::class)->generarDesdeVenta($record);
})
```

Nuevo servicio: `app/Services/Facturacion/FacturaVentaService.php`.

Principios mejorados: SRP de SOLID, Clean Code y KISS. El recurso Filament queda enfocado en la interfaz y el servicio concentra la responsabilidad de facturacion.

### Refactorizacion 2: Extract Method / Remove Conditional Complexity

Antes, `procesarMensaje` tenia una cadena de `if`:

```php
if (str_contains($mensaje, 'compras') || str_contains($mensaje, 'compra')) {
    return $this->analizarCompras();
}
```

Despues, el metodo recorre una lista de intenciones:

```php
foreach ($this->intenciones() as $intencion) {
    if (($intencion['aplica'])($mensaje)) {
        return ($intencion['responde'])($mensaje);
    }
}
```

Principios mejorados: Open/Closed de SOLID y KISS. Para agregar una nueva consulta del chatbot se agrega una entrada en `intenciones()`.

### Refactorizacion 3: Replace Magic Number with Constant

Antes:

```php
$limiteDiario = 10;
if ($consultasRealizadas >= $limiteDiario) { ... }
```

Despues:

```php
private const LIMITE_DIARIO_GEMINI = 10;

if ($consultasRealizadas >= self::LIMITE_DIARIO_GEMINI) { ... }
```

Principios mejorados: Clean Code y mantenibilidad ISO/IEC 25010. El limite queda nombrado y centralizado.

### Refactorizacion 4: Remove Dead Code

Se eliminaron variables calculadas que no eran usadas en el prompt ni en la respuesta:

```php
$dias_periodo = 90;
$semanas_periodo = $dias_periodo / 7;
$unidades_semanales = ...
```

Principios mejorados: Clean Code y KISS.

## 3. Integracion Continua propuesta

Archivo implementado: `.github/workflows/ci.yml`.

Estrategia recomendada: GitHub Flow.

1. Crear rama corta: `feature/refactor-ci`.
2. Hacer commits descriptivos.
3. Abrir Pull Request hacia `main`.
4. Ejecutar CI automaticamente.
5. Revisar resultados de pruebas, build y analisis.
6. Fusionar a `main` si el pipeline pasa.
7. Ejecutar despliegue simulado.

Diagrama del pipeline:

```mermaid
flowchart LR
    A[Commit / Push] --> B[GitHub Actions]
    B --> C[Composer install]
    C --> D[npm ci]
    D --> E[Preparar .env y SQLite]
    E --> F[php -l]
    F --> G[php artisan test]
    G --> H[npm run build]
    H --> I[SonarQube opcional]
    I --> J[Despliegue simulado]
```

Etapas cubiertas:

| Requisito | Implementacion |
|---|---|
| Repositorio Git | Proyecto ya contiene `.git`. |
| Estrategia | GitHub Flow propuesto para ramas cortas y Pull Requests. |
| Compilacion automatica | `npm run build` en GitHub Actions. |
| Pruebas | `php artisan test` con SQLite. |
| Analisis de codigo | `php -l` obligatorio y SonarQube opcional con `SONAR_TOKEN`. |
| Despliegue simulado | Paso `Simulated deploy`. |

Commit descriptivo sugerido:

```bash
git add app/Services/Facturacion/FacturaVentaService.php app/Filament/Vendedor/Resources/VentaResource.php app/Http/Controllers/Api/ChatController.php .github/workflows/ci.yml docs/sprint-refactorizacion-ci.md
git commit -m "refactor: extraer facturacion y agregar pipeline ci"
git push origin feature/refactor-ci
```

## 4. Reflexion tecnica

**Que problemas encontro en el codigo?**  
Se encontro acumulacion de responsabilidades en recursos Filament, metodos largos, condiciones repetitivas en el chatbot, valores magicos, variables sin uso y ausencia de pipeline CI.

**Que tecnica aporto mayor beneficio?**  
La tecnica con mayor beneficio fue `Extract Class`, porque saco la facturacion de `VentaResource` y dejo esa logica en `FacturaVentaService`.

**Que principio de diseno aplico?**  
Principalmente SRP de SOLID, DRY, KISS y Clean Code.

**Como contribuiria la Integracion Continua al mantenimiento?**  
CI ayuda a detectar errores de sintaxis, pruebas fallidas y fallos de build antes de integrar cambios a la rama principal. Tambien documenta una ruta repetible para validar el sistema.

**Que norma internacional respalda estas practicas?**  
ISO/IEC/IEEE 12207 respalda mantenimiento y evolucion del software; ISO/IEC 25010 respalda mantenibilidad y calidad; ISO/IEC 5055 se relaciona con calidad estructural; IEEE 730 con aseguramiento de calidad; IEEE 828 con gestion de configuracion y versiones.
