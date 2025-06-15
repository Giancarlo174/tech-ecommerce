# Westech Ecommerce

### 📋 Descripción

Westech Ecommerce es una plataforma de comercio electrónico desarrollada con PHP puro y MySQL. El sistema cuenta con dos roles principales (administrador y cliente) y ofrece una completa gestión de productos, carritos de compra, proceso de checkout y visualización de historial de pedidos.

### Características principales:

- **Panel de administración** para gestión completa de productos y pedidos
- **Catálogo de productos** con filtrado por categorías y marcas
- **Carrito de compra** dinámico con actualizaciones AJAX
- **Sistema de checkout** para finalización de compras
- **Historial de pedidos** para clientes
- **Gestión de imágenes** con integración a la API de Imgur (OAuth2)
- **Autenticación** y registro de usuarios

## 🚀 Tecnologías

El proyecto utiliza las siguientes tecnologías:

- **PHP 7.x+** (Desarrollo backend sin frameworks)
- **MySQL** (Base de datos relacional)
- **Composer** (Gestor de dependencias PHP)
- **phpdotenv** (Gestión de variables de entorno)
- **Imgur API con OAuth2** (Almacenamiento y gestión de imágenes)
- **JavaScript/AJAX** (Interacciones dinámicas en frontend)
- **HTML/CSS** (Estructura y estilos)

## 💻 Instalación

### Requisitos previos

- XAMPP, WAMP, MAMP o servidor Apache equivalente
- PHP 7.4 o superior
- MySQL 5.7 o superior
- Composer

### Pasos de instalación

1. **Clonar el repositorio**:
   ```bash
   git clone https://github.com/Giancarlo174/tech-ecommerce.git
   ```

2. **Instalar dependencias**:
   ```bash
   composer install
   ```

## ⚙️ Configuración

1. **Crear archivo de entorno**:
   Crea un archivo `.env` en la raíz del proyecto con la siguiente estructura:

   ```env
   # Configuración de la base de datos
   DB_HOST=localhost
   DB_NAME=nombre_base_de_datos
   DB_USER=usuario
   DB_PASS=contraseña
   
   # Configuración base de la aplicación
   BASE_URL=http://localhost/westech-ecommerce/
   SITE_NAME=Westech Ecommerce
   
   # Configuración de Imgur API
   IMGUR_CLIENT_ID=tu_client_id
   IMGUR_CLIENT_SECRET=tu_client_secret
   IMGUR_REDIRECT_URI=http://localhost/westech-ecommerce/callback.php
   IMGUR_ACCESS_TOKEN=tu_access_token
   IMGUR_REFRESH_TOKEN=tu_refresh_token
   ```

2. **Configurar permisos**:
   - Asegúrate de que el servidor web tenga permisos de escritura en directorios relevantes

© 2025 Westech Ecommerce | Todos los derechos caaaasi reservados | Giancarlo Santillana
