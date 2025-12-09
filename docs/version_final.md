# Documentación del Estado Final

## 1. Resumen del Sistema (Estado Final)
El sistema ha evolucionado de un e-commerce básico a una plataforma más robusta e inteligente. Mantiene su núcleo en PHP nativo pero integra servicios modernos vía API y librerías de terceros gestionadas por Composer. Se han añadido capacidades de Inteligencia Artificial para atención al cliente, generación de documentos PDF, reportes avanzados y un sistema de recuperación de contraseñas seguro.

## 2. Cambios Arquitectónicos

### Estructura de Carpetas
La estructura se ha profesionalizado, separando configuraciones y endpoints API:
```
/
├── admin/                  # Panel de administración (Mejorado con reportes y PDFs)
│   ├── api_reportes.php    # Endpoint interno para datos de gráficos
│   ├── factura_pdf.php     # Generador de facturas
│   ├── reportes_*.php      # Nuevos módulos de reportes
│   └── ...
├── api/                    # Endpoints públicos/internos
│   ├── chatbot.php         # Lógica del asistente IA
│   └── send_invoice_email.php
├── config/                 # Configuraciones específicas
│   └── gemini.php          # Configuración del cliente AI
├── includes/               # Lógica de negocio y helpers
│   ├── alertas.php         # Nueva clase AlertasInventario
│   └── ...
├── vendor/                 # Dependencias gestionadas por Composer
├── composer.json           # Definición de dependencias
├── phpstan.neon            # Configuración de análisis estático
└── ds6p2.sql               # Esquema de base de datos actualizado
```

### Gestión de Dependencias
Se ha adoptado **Composer** para gestionar librerías externas, abandonando la inclusión manual de archivos.
*   `google-gemini-php/client`: Para la integración con IA.
*   `resend/resend-php`: Para el envío transaccional de correos.
*   `tecnickcom/tcpdf`: Para la generación de documentos PDF.
*   `vlucas/phpdotenv`: Para variables de entorno (ya existía, pero ahora es parte de un ecosistema más grande).

### Integración de Servicios
El sistema ya no es aislado; se conecta con:
*   **Google Gemini:** Para el chatbot "Westito".
*   **Resend:** Para correos electrónicos.
*   **Imgur:** (Mantenido) Para imágenes.

---

## 3. Funcionalidades Nuevas y Mejoradas

### Chatbot IA "Westito"
*   **Descripción:** Asistente virtual capaz de responder preguntas sobre productos y pedidos.
*   **Implementación:** `api/chatbot.php` y `config/gemini.php`.
*   **Características:**
    *   **Híbrido:** Busca primero en una tabla local de `faqs` y, si no encuentra respuesta, consulta a la IA de Gemini.
    *   **Contexto:** Mantiene el hilo de la conversación mediante `conversation_id`.
    *   **Seguridad:** Filtra palabras clave peligrosas y sanitiza entradas.
    *   **Rate Limiting:** Limita a 5 mensajes por minuto por usuario para evitar abusos y costos.
![alt text](image.png)
### Reportes Avanzados
*   **Ventas:** (`admin/reportes_ventas.php`) Visualización de ingresos, ticket promedio y productos más vendidos.
![alt text](image-1.png) ![alt text](image-2.png) ![alt text](image-3.png)
*   **Inventario:** (`admin/reportes_inventarios.php`) Análisis de stock y valoración del inventario.
![alt text](image-4.png)
*   **Alertas:** (`includes/alertas.php`) Sistema automático que detecta stock bajo o crítico.
![alt text](image-5.png) ![alt text](image-6.png) 
### Generación de Documentos
*   **Facturas PDF:** (`admin/factura_pdf.php`) Generación dinámica de facturas profesionales usando TCPDF.
![alt text](image-7.png)
*   **Reportes PDF:** (`admin/reporte_pedidos_pdf.php`) Exportación de listados de pedidos.
![alt text](image-8.png)
### Recuperación de Contraseñas
*   **Flujo:** (`cliente/password_reset.php`) Sistema seguro basado en tokens temporales.
*   **Seguridad:** Tokens con expiración, hash de tokens en BD, y protección CSRF específica para este módulo.
![alt text](image-9.png)

---

## 4. Mejoras de Seguridad

### Nuevas Implementaciones
*   **Rate Limiting:** Implementado en la API del chatbot para prevenir ataques de denegación de servicio (DoS) y abuso de la API de IA.
*   **Protección CSRF:** Se ha añadido generación y validación de tokens CSRF, aunque por el momento solo se observa en el módulo de recuperación de contraseñas.
*   **Sanitización Mejorada:** Uso de helpers específicos (`WestitoHelpers`) para limpiar los mensajes del chat antes de procesarlos.
*   **Tokens de un solo uso:** Para el reseteo de contraseñas, almacenados como hash (`token_hash`) en la base de datos, no en texto plano.

---

## 5. Refactorizaciones y Calidad de Código

### Análisis Estático
Se ha integrado **PHPStan** con un nivel de exigencia **9 (máximo)**. Esto implica:
*   Tipado estricto de variables y retornos.
*   Manejo exhaustivo de posibles valores `null`.
*   Detección de código muerto.

### Orientación a Objetos
Se observa una transición hacia un diseño más orientado a objetos con nuevas clases como:
*   `GeminiConfig`: Encapsula la configuración y comunicación con la IA.
*   `AlertasInventario`: Encapsula la lógica de negocio del stock.

---

## 6. Cambios en la Base de Datos

Se han agregado nuevas tablas para soportar las funcionalidades:
1.  **`chatbot_queries`**: Registro de interacciones con la IA (auditoría y mejora).
2.  **`faqs`**: Base de conocimiento local para el chatbot.
3.  **`password_resets`**: Almacenamiento seguro de tokens de recuperación.
    *   *Columnas:* `correo`, `token_hash`, `expires_at`.

---

## 7. Diagrama UML Final (Mermaid)
loremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremloremlorem

---

## 8. Calidad Técnica Final
El sistema ha dado un salto cualitativo importante. Aunque mantiene la base de PHP nativo (sin framework completo como Laravel), ha adoptado prácticas profesionales:
*   **Estandarización:** Uso de PSR-4 (vía Composer).
*   **Robustez:** Manejo de excepciones y validaciones más estrictas.
*   **Mantenibilidad:** Código más modular y documentado.
*   **Modernidad:** Integración de IA y servicios Cloud.