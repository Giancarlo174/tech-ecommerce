// validaciones.js - Script para validar el formulario de productos
document.addEventListener('DOMContentLoaded', function() {
    // Referencias a los elementos del formulario de agregar
    const productForm = document.getElementById('product-form');
    const nombreInput = document.getElementById('nombre');
    const precioInput = document.getElementById('precio');
    const stockInput = document.getElementById('stock');
    const imagenInput = document.getElementById('imagen');
    const descripcionInput = document.getElementById('descripcion');
    
    // Referencias a los elementos del formulario de editar
    const editProductForm = document.getElementById('editProductForm');
    const editNombreInput = document.getElementById('edit_nombre');
    const editPrecioInput = document.getElementById('edit_precio');
    const editStockInput = document.getElementById('edit_stock');
    const editImagenInput = document.getElementById('edit_imagen');
    const editDescripcionInput = document.getElementById('edit_descripcion');
    
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
      // Validación del nombre: evitar etiquetas HTML y scripts
    nombreInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Reemplazar etiquetas HTML y caracteres sospechosos
        if (/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i.test(valor)) {
            e.target.value = valor.replace(/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/gi, '');
        }
        
        limpiarError(nombreInput);
    });
    
    // Validación de precio: solo números y un punto decimal
    precioInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Permitir solo números y un punto decimal
        if (/[^0-9.]/.test(valor)) {
            e.target.value = valor.replace(/[^0-9.]/g, '');
        }
        
        // Asegurar que solo haya un punto decimal
        const puntos = (valor.match(/\./g) || []).length;
        if (puntos > 1) {
            e.target.value = valor.substring(0, valor.lastIndexOf('.'));
        }
        
        // Limitar a dos decimales
        const partes = valor.split('.');
        if (partes.length > 1 && partes[1].length > 2) {
            e.target.value = partes[0] + '.' + partes[1].substring(0, 2);
        }
        
        limpiarError(precioInput);
    });
    
    // Validación de stock: solo enteros positivos
    stockInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Permitir solo enteros positivos
        if (/[^0-9]/.test(valor)) {
            e.target.value = valor.replace(/[^0-9]/g, '');
        }
        
        limpiarError(stockInput);
    });
    
    // Validación de imagen: solo jpg, jpeg, png y tamaño máximo 2MB
    imagenInput.addEventListener('change', function(e) {
        limpiarError(imagenInput);
        
        if (this.files.length > 0) {
            const file = this.files[0];
            
            // Validar tipo de archivo
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                mostrarError(imagenInput, 'Solo se permiten archivos JPG, JPEG o PNG');
                this.value = '';  // Limpiar el input
                
                // Restablecer el texto del selector de archivo personalizado
                const textDisplay = document.getElementById('imagen_file_name_display_text');
                const buttonDisplay = document.getElementById('imagen_file_name_display_button');
                if (textDisplay) textDisplay.textContent = 'Ningún archivo seleccionado';
                if (buttonDisplay) buttonDisplay.textContent = 'Seleccionar archivo';
                
                return;
            }
            
            // Validar tamaño (2MB = 2 * 1024 * 1024 bytes)
            if (file.size > 2 * 1024 * 1024) {
                mostrarError(imagenInput, 'El archivo no debe superar los 2MB');
                this.value = '';  // Limpiar el input
                
                // Restablecer el texto del selector de archivo personalizado
                const textDisplay = document.getElementById('imagen_file_name_display_text');
                const buttonDisplay = document.getElementById('imagen_file_name_display_button');
                if (textDisplay) textDisplay.textContent = 'Ningún archivo seleccionado';
                if (buttonDisplay) buttonDisplay.textContent = 'Seleccionar archivo';
                
                return;
            }
        }
    });
    
    // Validación de descripción: evitar etiquetas HTML y scripts
    descripcionInput.addEventListener('input', function(e) {
        const valor = e.target.value;
        
        // Reemplazar etiquetas HTML y caracteres sospechosos
        if (/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i.test(valor)) {
            e.target.value = valor.replace(/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/gi, '');
        }
        
        limpiarError(descripcionInput);
    });
      // Validación general al enviar el formulario
    productForm.addEventListener('submit', function(e) {
        let hayError = false;
        
        // Validar cada campo en orden
        
        // 1. Nombre
        if (!nombreInput.value.trim()) {
            e.preventDefault();
            mostrarError(nombreInput, 'El nombre es obligatorio');
            return;
        }
        
        // 2. Precio
        if (!precioInput.value.trim()) {
            e.preventDefault();
            mostrarError(precioInput, 'El precio es obligatorio');
            return;
        } else if (parseFloat(precioInput.value) <= 0) {
            e.preventDefault();
            mostrarError(precioInput, 'El precio debe ser mayor a 0');
            return;
        }
        
        // 3. Stock
        if (!stockInput.value.trim()) {
            e.preventDefault();
            mostrarError(stockInput, 'El stock es obligatorio');
            return;
        } else if (parseInt(stockInput.value) < 0) {
            e.preventDefault();
            mostrarError(stockInput, 'El stock no puede ser negativo');
            return;
        }
        
        // 4. Imagen (solo para el formulario de añadir)
        if (productForm.querySelector('input[name="action"]').value === 'add' && 
            (!imagenInput.files || imagenInput.files.length === 0)) {
            e.preventDefault();
            mostrarError(imagenInput, 'Debe seleccionar una imagen');
            return;
        }
        
        // 5. Descripción
        if (!descripcionInput.value.trim()) {
            e.preventDefault();
            mostrarError(descripcionInput, 'La descripción es obligatoria');
            return;
        }
        
        // Mostrar spinner de carga
        const submitBtn = document.getElementById('submit-btn');
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
        }
        
        // Si llegamos aquí, no hay errores y el formulario se enviará
    });
    
    // =================================
    // Validaciones para formulario de edición
    // =================================
      // Validación del nombre: evitar etiquetas HTML y scripts (edición)
    if (editNombreInput) {
        editNombreInput.addEventListener('input', function(e) {
            const valor = e.target.value;
            
            // Reemplazar etiquetas HTML y caracteres sospechosos
            if (/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i.test(valor)) {
                e.target.value = valor.replace(/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/gi, '');
            }
            
            limpiarError(editNombreInput);
        });
    }
    
    // Validación de precio: solo números y un punto decimal (edición)
    if (editPrecioInput) {
        editPrecioInput.addEventListener('input', function(e) {
            const valor = e.target.value;
            
            // Permitir solo números y un punto decimal
            if (/[^0-9.]/.test(valor)) {
                e.target.value = valor.replace(/[^0-9.]/g, '');
            }
            
            // Asegurar que solo haya un punto decimal
            const puntos = (valor.match(/\./g) || []).length;
            if (puntos > 1) {
                e.target.value = valor.substring(0, valor.lastIndexOf('.'));
            }
            
            // Limitar a dos decimales
            const partes = valor.split('.');
            if (partes.length > 1 && partes[1].length > 2) {
                e.target.value = partes[0] + '.' + partes[1].substring(0, 2);
            }
            
            limpiarError(editPrecioInput);
        });
    }
    
    // Validación de stock: solo enteros positivos (edición)
    if (editStockInput) {
        editStockInput.addEventListener('input', function(e) {
            const valor = e.target.value;
            
            // Permitir solo enteros positivos
            if (/[^0-9]/.test(valor)) {
                e.target.value = valor.replace(/[^0-9]/g, '');
            }
            
            limpiarError(editStockInput);
        });
    }
    
    // Validación de imagen: solo jpg, jpeg, png y tamaño máximo 2MB (edición)
    if (editImagenInput) {
        editImagenInput.addEventListener('change', function(e) {
            limpiarError(editImagenInput);
            
            if (this.files.length > 0) {
                const file = this.files[0];
                
                // Validar tipo de archivo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    mostrarError(editImagenInput, 'Solo se permiten archivos JPG, JPEG o PNG');
                    this.value = '';  // Limpiar el input
                    
                    // Restablecer el texto del selector de archivo personalizado
                    const textDisplay = document.getElementById('edit_imagen_file_name_display_text');
                    const buttonDisplay = document.getElementById('edit_imagen_file_name_display_button');
                    if (textDisplay) textDisplay.textContent = 'Ningún archivo seleccionado';
                    if (buttonDisplay) buttonDisplay.textContent = 'Seleccionar archivo';
                    
                    return;
                }
                
                // Validar tamaño (2MB = 2 * 1024 * 1024 bytes)
                if (file.size > 2 * 1024 * 1024) {
                    mostrarError(editImagenInput, 'El archivo no debe superar los 2MB');
                    this.value = '';  // Limpiar el input
                    
                    // Restablecer el texto del selector de archivo personalizado
                    const textDisplay = document.getElementById('edit_imagen_file_name_display_text');
                    const buttonDisplay = document.getElementById('edit_imagen_file_name_display_button');
                    if (textDisplay) textDisplay.textContent = 'Ningún archivo seleccionado';
                    if (buttonDisplay) buttonDisplay.textContent = 'Seleccionar archivo';
                    
                    return;
                }
            }
        });
    }
    
    // Validación de descripción: evitar etiquetas HTML y scripts (edición)
    if (editDescripcionInput) {
        editDescripcionInput.addEventListener('input', function(e) {
            const valor = e.target.value;
            
            // Reemplazar etiquetas HTML y caracteres sospechosos
            if (/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/i.test(valor)) {
                e.target.value = valor.replace(/<[^>]*>|&lt;[^>]*&gt;|javascript:|onerror=|onclick=|onload=/gi, '');
            }
            
            limpiarError(editDescripcionInput);
        });
    }
    
    // Validación general al enviar el formulario de edición
    if (editProductForm) {
        editProductForm.addEventListener('submit', function(e) {
            let hayError = false;
            
            // Validar cada campo en orden
            
            // 1. Nombre
            if (!editNombreInput.value.trim()) {
                e.preventDefault();
                mostrarError(editNombreInput, 'El nombre es obligatorio');
                return;
            }
            
            // 2. Precio
            if (!editPrecioInput.value.trim()) {
                e.preventDefault();
                mostrarError(editPrecioInput, 'El precio es obligatorio');
                return;
            } else if (parseFloat(editPrecioInput.value) <= 0) {
                e.preventDefault();
                mostrarError(editPrecioInput, 'El precio debe ser mayor a 0');
                return;
            }
            
            // 3. Stock
            if (!editStockInput.value.trim()) {
                e.preventDefault();
                mostrarError(editStockInput, 'El stock es obligatorio');
                return;
            } else if (parseInt(editStockInput.value) < 0) {
                e.preventDefault();
                mostrarError(editStockInput, 'El stock no puede ser negativo');
                return;
            }
            
            // 4. Imagen (opcional en edición, ya que puede mantener la actual)
            if (editImagenInput.files && editImagenInput.files.length > 0) {
                // Solo validamos si se seleccionó un archivo
                const file = editImagenInput.files[0];
                
                // Validar tipo de archivo
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    mostrarError(editImagenInput, 'Solo se permiten archivos JPG, JPEG o PNG');
                    return;
                }
                
                // Validar tamaño (2MB = 2 * 1024 * 1024 bytes)
                if (file.size > 2 * 1024 * 1024) {
                    e.preventDefault();
                    mostrarError(editImagenInput, 'El archivo no debe superar los 2MB');
                    return;
                }
            }
            
            // 5. Descripción
            if (!editDescripcionInput.value.trim()) {
                e.preventDefault();
                mostrarError(editDescripcionInput, 'La descripción es obligatoria');
                return;
            }
              // Mostrar spinner de carga
            const updateBtn = document.getElementById('update-btn');
            if (updateBtn) {
                // Guardar el texto original del botón
                const originalText = updateBtn.textContent;
                
                // Crear y agregar el spinner
                updateBtn.innerHTML = '<span class="spinner"></span> Procesando...';
                updateBtn.classList.add('btn-loading');
                
                // Por si el envío falla por alguna razón, restaurar el botón después de 30 segundos
                setTimeout(() => {
                    if (document.body.contains(updateBtn)) {
                        updateBtn.innerHTML = originalText;
                        updateBtn.classList.remove('btn-loading');
                    }
                }, 30000);
            }
            
            // Si llegamos aquí, no hay errores y el formulario se enviará
        });
    }
});
