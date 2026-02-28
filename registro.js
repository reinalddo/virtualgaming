// Registro de usuario para VirtualGaming
// Este script asume que el formulario tiene los siguientes IDs:
// #nombre, #correo, #telefono, #contrasena, #registro-btn

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('registro-form');
    if (!form) return;

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const nombre = document.getElementById('nombre').value.trim();
        const correo = document.getElementById('correo').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const contrasena = document.getElementById('contrasena').value;
        const btn = document.getElementById('registro-btn');
        btn.disabled = true;
        btn.textContent = 'Registrando...';
        try {
            const res = await fetch('register_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nombre, correo, telefono, contrasena })
            });
            const data = await res.json();
            if (data.success) {
                alert('¡Registro exitoso! Ahora puedes iniciar sesión.');
                window.location.href = 'login.php';
            } else {
                alert(data.message || 'Error al registrar.');
            }
        } catch (err) {
            alert('Error de red o del servidor.');
        }
        btn.disabled = false;
        btn.textContent = 'REGISTRARSE AHORA';
    });
});
