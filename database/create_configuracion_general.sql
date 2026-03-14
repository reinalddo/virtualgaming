-- Tabla de configuración general para la tienda
CREATE TABLE IF NOT EXISTS configuracion_general (
    id INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(64) NOT NULL UNIQUE,
    valor TEXT NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    actualizado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Ejemplo de inserción de variables iniciales
INSERT INTO configuracion_general (clave, valor, descripcion) VALUES
('correo_corporativo', '', 'Correo usado para notificaciones'),
('smtp_host', '', 'Host SMTP para envío de correos'),
('smtp_user', '', 'Usuario SMTP'),
('smtp_pass', '', 'Contraseña SMTP'),
('smtp_port', '587', 'Puerto SMTP'),
('smtp_secure', 'tls', 'Tipo de seguridad SMTP'),
('nombre_prefijo', 'TIENDA', 'Texto superior del encabezado de la tienda'),
('nombre_tienda', 'TVirtualGaming', 'Nombre principal visible de la tienda'),
('logo_tienda', '', 'Ruta del logo visible en el encabezado'),
('facebook', '', 'URL de Facebook de la tienda'),
('instagram', '', 'URL de Instagram de la tienda'),
('whatsapp', '', 'Número o enlace de WhatsApp de la tienda'),
('mensaje_whatsapp', '', 'Mensaje predefinido para el botón flotante de WhatsApp'),
('whatsapp_channel', '', 'URL del canal de WhatsApp de la tienda');
