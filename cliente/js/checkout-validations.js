// checkout-validations.js - Script para validar el formulario de información de envío
document.addEventListener('DOMContentLoaded', function() {
    // Referencias a los elementos del formulario
    const checkoutForm = document.getElementById('checkout-form');
    const nombreInput = document.getElementById('nombre');
    const celularInput = document.getElementById('celular');
    const direccionInput = document.getElementById('direccion');
    const submitBtn = document.getElementById('submit-btn');
    
    // Cargar datos guardados del localStorage
    cargarDatosGuardados();
    
    // Función para guardar datos en localStorage
    function guardarDatos() {
        // Solo guardamos si hay contenido en los campos
        const datosEnvio = {
            nombre: nombreInput.value.trim(),
            celular: celularInput.value.trim(),
            direccion: direccionInput.value.trim()
        };
        
        localStorage.setItem('datosEnvio', JSON.stringify(datosEnvio));
    }
    
    // Función para cargar datos del localStorage
    function cargarDatosGuardados() {
        const datosGuardados = localStorage.getItem('datosEnvio');
        
        if (datosGuardados) {
            try {
                const datos = JSON.parse(datosGuardados);
                
                // Solo establecer valores si los campos están vacíos (para no sobrescribir datos del formulario)
                if (!nombreInput.value && datos.nombre) nombreInput.value = datos.nombre;
                if (!celularInput.value && datos.celular) celularInput.value = datos.celular;
                if (!direccionInput.value && datos.direccion) direccionInput.value = datos.direccion;
            } catch (error) {
                console.error('Error al cargar datos guardados:', error);
                // Si hay error, eliminamos los datos corruptos
                localStorage.removeItem('datosEnvio');
            }
        }
    }
    
    // Función para mostrar error
    function mostrarError(elemento, mensaje) {
        // Eliminar mensajes de error previos
        const prevError = elemento.parentNode.querySelector('.text-danger');
        if (prevError) {
            prevError.remove();
        }
        
        // Añadir clase de error
        elemento.classList.add('is-invalid');
        
        // Crear y añadir mensaje de error
        const errorMsg = document.createElement('small');
        errorMsg.classList.add('text-danger');
        errorMsg.textContent = mensaje;
        elemento.parentNode.insertBefore(errorMsg, elemento.nextSibling);
        
        // Focus y scroll al elemento
        elemento.focus();
        elemento.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    
    // Función para limpiar error
    function limpiarError(elemento) {
        const error = elemento.parentNode.querySelector('.text-danger');
        if (error) {
            error.remove();
        }
        elemento.classList.remove('is-invalid');
    }
      // Validación del nombre: solo letras y espacios
    nombreInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        // Permitir solo letras y espacios (no números ni caracteres especiales)
        if (/[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]/.test(valor)) {
            e.target.value = valor.replace(/[^a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]/g, '');
        }
        limpiarError(nombreInput);
        // Guardar en localStorage
        guardarDatos();
    });
      // Validación de celular: solo números
    celularInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Permitir solo números
        if (/[^0-9]/.test(valor)) {
            e.target.value = valor.replace(/[^0-9]/g, '');
        }
        
        limpiarError(celularInput);
        // Guardar en localStorage
        guardarDatos();
    });      // Validación de dirección: evitar etiquetas HTML y scripts
    direccionInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Reemplazar etiquetas HTML y caracteres sospechosos
        if (/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i.test(valor)) {
            e.target.value = valor.replace(/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/gi, '');
        }
        
        limpiarError(direccionInput);
        // Guardar en localStorage
        guardarDatos();
    });
    
    // Validación general al enviar el formulario
    checkoutForm.addEventListener('submit', function(e) {
        let hayError = false;
        
        // 1. Nombre
        if (!nombreInput.value.trim()) {
            e.preventDefault();
            mostrarError(nombreInput, 'El nombre completo es obligatorio');
            return;
        } else if (!/^[a-zA-ZáéíóúÁÉÍÓÚüÜñÑ\s]+$/.test(nombreInput.value.trim())) {
            e.preventDefault();
            mostrarError(nombreInput, 'El nombre solo puede contener letras y espacios');
            return;
        }
        
        // 2. Celular
        if (!celularInput.value.trim()) {
            e.preventDefault();
            mostrarError(celularInput, 'El número de celular es obligatorio');
            return;
        } else if (!/^\d+$/.test(celularInput.value.trim())) {
            e.preventDefault();
            mostrarError(celularInput, 'El número de celular debe contener solo dígitos');
            return;
        } else if (celularInput.value.trim().length < 7) {
            e.preventDefault();
            mostrarError(celularInput, 'El número de celular debe tener al menos 7 dígitos');
            return;
        }
        
        // 3. Dirección
        if (!direccionInput.value.trim()) {
            e.preventDefault();
            mostrarError(direccionInput, 'La dirección de envío es obligatoria');
            return;
        }
          // Mostrar spinner de carga
        if (submitBtn) {
            // Guardar el texto original del botón
            const originalText = submitBtn.textContent;
            
            // Crear y agregar el spinner
            submitBtn.innerHTML = '<span class="spinner"></span> Procesando...';
            submitBtn.classList.add('btn-loading');
            
            // Por si el envío falla por alguna razón, restaurar el botón después de 30 segundos
            setTimeout(() => {
                if (document.body.contains(submitBtn)) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.classList.remove('btn-loading');
                }
            }, 30000);
            
            // Si el formulario se envía correctamente, se limpia el localStorage
            // Solo limpiamos si pasamos todas las validaciones
            localStorage.removeItem('datosEnvio');
        }
        
        // Si llegamos aquí, no hay errores y el formulario se enviará
    });
});
