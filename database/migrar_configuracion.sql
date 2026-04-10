INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'correo_corporativo', correo_corporativo, 'Correo usado para notificaciones' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_host', smtp_host, 'Host SMTP para envío de correos' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_user', smtp_user, 'Usuario SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_pass', smtp_pass, 'Contraseña SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_port', smtp_port, 'Puerto SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'smtp_secure', smtp_secure, 'Tipo de seguridad SMTP' FROM configuracion WHERE id=1;
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego', '0', 'Activa o desactiva la ventana global que se muestra al entrar a cualquier juego.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_descripcion', 'Lee la información antes de continuar con la recarga.', 'Texto descriptivo debajo del título principal de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_descripcion');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_modal_background', '#18101e', 'Color de fondo del modal global que se muestra al entrar a un juego.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_modal_background');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_title_color', '#f8b53d', 'Color del título principal de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_title_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_check_text_color', '#e2e8f0', 'Color del texto del bloque de confirmación de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_check_text_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_check_background_color', '#1e293b', 'Color de fondo del bloque de confirmación de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_check_background_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_button_text_color', '#0b0f18', 'Color del texto del botón principal de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_button_text_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_button_background_color', '#c99712', 'Color de fondo del botón principal de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_button_background_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_button_disabled_text_color', '#0b0f18', 'Color del texto del botón principal inactivo de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_button_disabled_text_color');
INSERT INTO configuracion_general (clave, valor, descripcion)
SELECT 'ventana_inicio_juego_button_disabled_background_color', '#c99712', 'Color de fondo del botón principal inactivo de la ventana inicial en juegos.'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM configuracion_general WHERE clave = 'ventana_inicio_juego_button_disabled_background_color');
