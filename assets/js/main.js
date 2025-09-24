/**
 * GLS Package Pickup System - Main JavaScript
 */

// DOM ready function
function ready(fn) {
    if (document.readyState !== 'loading') {
        fn();
    } else {
        document.addEventListener('DOMContentLoaded', fn);
    }
}

// Initialize common functionality
ready(function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 300);
        }, 5000);
    });

    // Confirm delete actions
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-delete')) {
            if (!confirm('¿Está seguro de que desea eliminar este elemento?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // Auto-refresh pickup status (every 30 seconds on delivery panel)
    if (document.body.classList.contains('delivery-panel')) {
        setInterval(function() {
            const statusElements = document.querySelectorAll('[data-pickup-id]');
            statusElements.forEach(function(element) {
                const pickupId = element.getAttribute('data-pickup-id');
                refreshPickupStatus(pickupId);
            });
        }, 30000);
    }
});

// Package management functions
const PackageManager = {
    addPackageRow: function() {
        const container = document.getElementById('packages-container');
        const packageCount = container.children.length;
        const packageRow = document.createElement('div');
        packageRow.className = 'package-row card mb-3';
        packageRow.innerHTML = `
            <div class="card-header">
                <h5>Paquete ${packageCount + 1} 
                    <button type="button" class="btn btn-danger btn-sm float-right" onclick="PackageManager.removePackageRow(this)">
                        Eliminar
                    </button>
                </h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Número de seguimiento GLS (opcional)</label>
                            <input type="text" name="packages[${packageCount}][tracking_number]" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre del destinatario *</label>
                            <input type="text" name="packages[${packageCount}][recipient_name]" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono del destinatario *</label>
                            <input type="tel" name="packages[${packageCount}][recipient_phone]" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dirección del destinatario *</label>
                            <textarea name="packages[${packageCount}][recipient_address]" class="form-control" rows="2" required></textarea>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Ciudad *</label>
                            <input type="text" name="packages[${packageCount}][recipient_city]" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código postal *</label>
                            <input type="text" name="packages[${packageCount}][recipient_postal_code]" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" step="0.1" name="packages[${packageCount}][weight]" class="form-control">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dimensiones</label>
                            <input type="text" name="packages[${packageCount}][dimensions]" class="form-control" placeholder="Ej: 30x20x10 cm">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Cantidad de bultos</label>
                            <input type="number" min="1" name="packages[${packageCount}][quantity]" class="form-control" value="1">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Observaciones</label>
                            <textarea name="packages[${packageCount}][observations]" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
            </div>
        `;
        container.appendChild(packageRow);
    },

    removePackageRow: function(button) {
        const packageRow = button.closest('.package-row');
        packageRow.remove();
        this.updatePackageNumbers();
    },

    updatePackageNumbers: function() {
        const packages = document.querySelectorAll('.package-row');
        packages.forEach(function(packageRow, index) {
            const header = packageRow.querySelector('.card-header h5');
            header.firstChild.textContent = `Paquete ${index + 1} `;
            
            // Update input names
            const inputs = packageRow.querySelectorAll('input, textarea, select');
            inputs.forEach(function(input) {
                if (input.name) {
                    input.name = input.name.replace(/packages\[\d+\]/, `packages[${index}]`);
                }
            });
        });
    }
};

// Status management functions
const StatusManager = {
    updateStatus: function(pickupId, newStatus, notes = '') {
        const formData = new FormData();
        formData.append('pickup_id', pickupId);
        formData.append('status', newStatus);
        formData.append('notes', notes);

        fetch('/includes/ajax/update_status.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                this.refreshStatusDisplay(pickupId, newStatus);
                this.showAlert('Estado actualizado correctamente', 'success');
            } else {
                this.showAlert('Error al actualizar el estado: ' + data.message, 'danger');
            }
        })
        .catch(error => {
            this.showAlert('Error de conexión', 'danger');
            console.error('Error:', error);
        });
    },

    refreshStatusDisplay: function(pickupId, status) {
        const statusElement = document.querySelector(`[data-pickup-id="${pickupId}"] .status-badge`);
        if (statusElement) {
            statusElement.className = `badge badge-${status.replace('_', '-')}`;
            statusElement.textContent = this.getStatusText(status);
        }
    },

    getStatusText: function(status) {
        const statusTexts = {
            'pendiente_confirmar': 'Pendiente Confirmar',
            'confirmada': 'Confirmada',
            'sin_asignar': 'Sin Asignar',
            'asignada': 'Asignada',
            'en_ruta': 'En Ruta',
            'no_mercancia': 'No Mercancía',
            'hecho': 'Hecho',
            'incidencia': 'Incidencia',
            'vehiculo_no_apropiado': 'Vehículo No Apropiado'
        };
        return statusTexts[status] || status;
    },

    showAlert: function(message, type) {
        const alertContainer = document.getElementById('alert-container') || document.body;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type}`;
        alert.textContent = message;
        alertContainer.insertBefore(alert, alertContainer.firstChild);

        setTimeout(function() {
            alert.remove();
        }, 5000);
    }
};

// Barcode generation (simple implementation)
const BarcodeGenerator = {
    generate: function(text, elementId) {
        const element = document.getElementById(elementId);
        if (element && typeof JsBarcode !== 'undefined') {
            JsBarcode(element, text, {
                format: "CODE128",
                width: 2,
                height: 100,
                displayValue: true
            });
        }
    }
};

// Print functionality
function printLabel(pickupId, packageId = null) {
    const url = packageId 
        ? `/includes/print_label.php?pickup_id=${pickupId}&package_id=${packageId}`
        : `/includes/print_label.php?pickup_id=${pickupId}`;
    
    window.open(url, '_blank', 'width=800,height=600,scrollbars=yes');
}

// Form validation helpers
const FormValidator = {
    validateEmail: function(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },

    validatePhone: function(phone) {
        const re = /^[+]?[\d\s\-\(\)]{9,}$/;
        return re.test(phone);
    },

    validatePostalCode: function(postalCode) {
        const re = /^\d{5}$/;
        return re.test(postalCode);
    }
};

// Refresh pickup status
function refreshPickupStatus(pickupId) {
    fetch(`/includes/ajax/get_pickup_status.php?id=${pickupId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                StatusManager.refreshStatusDisplay(pickupId, data.status);
            }
        })
        .catch(error => {
            console.error('Error refreshing status:', error);
        });
}