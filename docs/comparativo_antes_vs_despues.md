# Comparativo: Evolución del Sistema Tech-Ecommerce

## 1. Tabla Comparativa General

| Característica | Estado Inicial | Estado Final |
| :--- | :--- | :--- |
| **Arquitectura** | Monolito PHP plano (Spaghetti Code) | Monolito Modular con Composer y Servicios |
| **Gestión de Librerías** | Manual / Inexistente | **Composer** (Gestión profesional) |
| **Base de Datos** | 7 Tablas básicas | **10 Tablas** (+Chatbot, FAQs, Security) |
| **Atención al Cliente** | Inexistente (solo contacto pasivo) | **Chatbot IA (Gemini)** + FAQs |
| **Reportes** | Métricas básicas en Dashboard | **Reportes detallados** (Ventas, Stock) + PDF |
| **Seguridad** | Básica (Sanitize, Prepared Stmts) | **Avanzada** (CSRF, Rate Limiting, Tokens) |
| **Calidad de Código** | Sin estándares, validación manual | **PHPStan Nivel 9**, Clases, Helpers |
| **Recuperación de Cuenta** | No existía (Usuario bloqueado si olvidaba pass) | **Sistema completo** (Token + Email) |

---

## 2. Comparación de Funcionalidades

### Lo que se mejoró
*   **Gestión de Inventario:** Pasó de un simple campo `stock` a un sistema con **Alertas de Inventario** (`AlertasInventario`) y reportes de valoración.
*   **Carrito de Compras:** Se optimizó la lógica de validación de stock en tiempo real.
*   **Panel de Admin:** Evolucionó de un simple CRUD a un **Centro de Control** con generación de facturas PDF y análisis de datos.

### Lo que se agregó
*   **Inteligencia Artificial:** Integración con Google Gemini para un asistente virtual que entiende lenguaje natural.
*   **Sistema de Facturación:** Generación automática de PDFs con `TCPDF`.
*   **Emails Transaccionales:** Envío de correos reales usando la API de `Resend`.
*   **Recuperación de Contraseña:** Funcionalidad crítica que estaba ausente.

---

## 3. Comparación de Arquitectura

### Antes
El sistema dependía de archivos "God Object" (ej. `productos.php`) que mezclaban HTML, SQL y lógica PHP. Si se quería cambiar una configuración, teniamos que buscar en múltiples archivos.

### Después
Se introdujo una **Arquitectura de Servicios y Configuración**:
*   **`config/`**: Centraliza configuraciones complejas (como la de Gemini).
*   **`api/`**: Separa la lógica de respuesta JSON de las vistas HTML.
*   **`includes/`**: Ahora contiene clases (`AlertasInventario`) en lugar de solo funciones sueltas.
*   **Vendor:** El uso de `vendor/` permite actualizar librerías de seguridad y funcionalidad con un solo comando (`composer update`).

---

## 4. Comparación de Seguridad

### Vulnerabilidades
1.  **Ausencia de CSRF:** Los formularios podían ser enviados desde otros sitios maliciosos.
2.  **Fuerza Bruta:** No había límites en los intentos de login o consultas.
3.  **Pérdida de Cuenta:** Sin mecanismo de recuperación, un olvido de contraseña era fatal.

### Soluciones
1.  **Tokens CSRF:** Implementados (visiblemente en el módulo de password reset) para validar el origen de las peticiones.
2.  **Rate Limiting:** El chatbot implementa un límite de 5 peticiones/minuto, protegiendo la API y el servidor.
3.  **Tokens Hash:** Los tokens de recuperación se guardan como hash, no en texto plano, previniendo robos si la BD es comprometida.
4.  **Sanitización IA:** Se implementaron filtros específicos para evitar que el chatbot sea manipulado (Prompt Injection básico).

---

## 5. Comparación de Código

### Malas Prácticas Corregidas
*   **Hardcoding:** Antes, las credenciales o configuraciones podían estar dispersas. Ahora se usa `.env` y clases de configuración.
*   **Lógica en Vistas:** Se ha reducido la cantidad de lógica PHP compleja dentro de los archivos HTML, moviéndola a clases en `includes/` o endpoints en `api/`.
*   **Tipado Débil:** La introducción de **PHPStan** fuerza a declarar tipos (`int`, `string`, `void`), reduciendo bugs por tipos de datos incorrectos.

---

## 6. Comparación Descriptiva

*   **Interfaz de Usuario:** Se mantiene el diseño limpio, pero ahora incluye un **widget flotante** para el Chatbot en la esquina inferior.
*   **Panel Admin:** Se han añadido botones para "Exportar PDF" y nuevas secciones de menú para "Reportes" y "Facturas".
*   **Feedback:** El usuario ahora recibe notificaciones más claras y correos electrónicos reales.

---

## 7. Comparación de Base de Datos

El esquema creció para soportar la modernización:

*   **`chatbot_queries`**: Permite auditar qué preguntan los usuarios y cómo responde la IA.
*   **`faqs`**: Permite al admin controlar las respuestas antes de gastar tokens en la IA.
*   **`password_resets`**: Tabla temporal para seguridad de cuentas.

Las tablas originales (`productos`, `pedidos`) se mantuvieron estables, lo que indica que el diseño de datos inicial fue correcto, pero insuficiente para funcionalidades avanzadas.

---

## 8. Conclusiones y Evolución del Sistema

El sistema **Tech-Ecommerce** ha madurado exitosamente.

1.  **De Prototipo a Producto:** La versión inicial era un prototipo académico funcional. La versión final es un producto con características comerciales viables.
2.  **Deuda Técnica Pagada:** La refactorización y el uso de Composer han reducido significativamente la deuda técnica, facilitando el mantenimiento futuro.
3.  **Valor Agregado:** La inclusión de IA no es solo un "adorno", sino una herramienta funcional de soporte integrada con la base de datos de conocimiento (FAQs).

El sistema demuestra cómo una aplicación PHP "legacy" o básica puede modernizarse integrando servicios en la nube y mejores prácticas de ingeniería de software sin necesidad de reescribir todo desde cero.