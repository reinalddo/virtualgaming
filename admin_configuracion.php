<?php
require_once __DIR__ . '/includes/db_connect.php';

// Guardar cambios en la tabla general
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $campos = [
        'correo_corporativo', 'smtp_host', 'smtp_user', 'smtp_pass', 'smtp_port', 'smtp_secure'
    ];
    foreach ($campos as $clave) {
        $valor = trim($_POST[$clave] ?? '');
        $stmt = $mysqli->prepare('UPDATE configuracion_general SET valor=? WHERE clave=?');
        $stmt->bind_param('ss', $valor, $clave);
        $stmt->execute();
    }
}

// Leer valores actuales
$cfg = [];
$res = $mysqli->query('SELECT clave, valor FROM configuracion_general');
while ($row = $res->fetch_assoc()) {
    $cfg[$row['clave']] = $row['valor'];
}
?>
<section class="mt-8 rounded-2xl border border-slate-800 bg-slate-900/70 p-6 max-w-xl mx-auto shadow-lg animate-fadeUp">
    <div class="flex flex-col items-center mb-6">
        <span class="inline-block mb-2 animate-floaty">
            <svg width="44" height="44" viewBox="0 0 44 44" fill="none"><circle cx="22" cy="22" r="20" stroke="#34d399" stroke-width="3"/><path d="M12 16l10 8 10-8" stroke="#22d3ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><rect x="12" y="16" width="20" height="12" rx="3" fill="#0e1722" stroke="#2dd4bf" stroke-width="2"/></svg>
        </span>
        <h2 class="text-2xl font-bold text-cyan-300 text-center">Configuración de correo corporativo</h2>
    </div>
    <form method="post" class="space-y-5">
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><rect x="2" y="5" width="16" height="10" rx="2" stroke="#22d3ee" stroke-width="2"/><path d="M2 5l8 6 8-6" stroke="#34d399" stroke-width="2"/></svg>
                Correo corporativo:
                <span title="Dirección de correo que se usará como remitente en los emails enviados. Debe ser válida y estar configurada en tu servidor SMTP." class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <input type="email" name="correo_corporativo" value="<?= htmlspecialchars($cfg['correo_corporativo'] ?? '') ?>" required class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" placeholder="correo@tudominio.com">
        </div>
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" stroke="#2dd4bf" stroke-width="2"/><path d="M10 6v4l3 2" stroke="#22d3ee" stroke-width="2" stroke-linecap="round"/></svg>
                SMTP Host:
                <span title="Servidor SMTP que se usará para enviar los correos. Ejemplo: smtp.gmail.com o smtp.tuservidor.com" class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>" required class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" placeholder="smtp.tuservidor.com">
        </div>
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><circle cx="10" cy="10" r="8" stroke="#34d399" stroke-width="2"/><path d="M10 8v4" stroke="#22d3ee" stroke-width="2" stroke-linecap="round"/></svg>
                SMTP User:
                <span title="Usuario de autenticación SMTP. Normalmente es el mismo correo corporativo o el usuario configurado en tu servidor." class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>" required class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" placeholder="usuario@tudominio.com">
        </div>
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><rect x="4" y="8" width="12" height="8" rx="2" stroke="#22d3ee" stroke-width="2"/><path d="M10 12v2" stroke="#34d399" stroke-width="2" stroke-linecap="round"/></svg>
                SMTP Password:
                <span title="Contraseña del usuario SMTP. Nunca se mostrará en texto plano. Protege este dato." class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <input type="password" name="smtp_pass" value="<?= htmlspecialchars($cfg['smtp_pass'] ?? '') ?>" class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" placeholder="••••••••">
        </div>
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><rect x="5" y="5" width="10" height="10" rx="2" stroke="#2dd4bf" stroke-width="2"/><text x="10" y="13" text-anchor="middle" fill="#22d3ee" font-size="8">587</text></svg>
                SMTP Port:
                <span title="Puerto de conexión SMTP. Normalmente 587 para TLS o 465 para SSL. Consulta la documentación de tu proveedor." class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port'] ?? 587) ?>" required class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 placeholder:text-slate-600 focus:border-cyan-400 focus:outline-none" placeholder="587">
        </div>
        <div>
            <label class="block text-slate-300 text-sm mb-1 flex items-center gap-2">
                <svg class="inline-block" width="18" height="18" fill="none" viewBox="0 0 20 20"><rect x="3" y="6" width="14" height="8" rx="2" stroke="#34d399" stroke-width="2"/><path d="M7 10h6" stroke="#22d3ee" stroke-width="2" stroke-linecap="round"/></svg>
                SMTP Secure:
                <span title="Tipo de seguridad para la conexión SMTP. TLS es el más común, SSL es usado por algunos proveedores." class="ml-1 cursor-help text-cyan-400">
                    <svg width="16" height="16" fill="none" viewBox="0 0 16 16"><circle cx="8" cy="8" r="7" stroke="#22d3ee" stroke-width="1.5"/><text x="8" y="11" text-anchor="middle" fill="#22d3ee" font-size="10">?</text></svg>
                </span>
            </label>
            <select name="smtp_secure" class="w-full rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2 text-sm text-slate-100 focus:border-cyan-400 focus:outline-none">
                <option value="tls" <?= ($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                <option value="ssl" <?= ($cfg['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
            </select>
        </div>
        <button type="submit" class="w-full glow-ring rounded-2xl bg-gradient-to-r from-cyan-400 via-emerald-400 to-cyan-300 px-4 py-3 text-center text-sm font-bold uppercase tracking-[0.3em] text-slate-950 transition hover:from-cyan-300 hover:to-emerald-300 flex items-center justify-center gap-2">
            <svg width="20" height="20" fill="none" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" stroke="#22d3ee" stroke-width="2"/><path d="M6 10l3 3 5-5" stroke="#34d399" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
            Guardar
        </button>
        <?php if ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="text-emerald-400 text-center font-semibold mt-4 animate-fadeUp flex items-center justify-center gap-2">
                <svg width="22" height="22" fill="none" viewBox="0 0 22 22"><circle cx="11" cy="11" r="10" stroke="#34d399" stroke-width="2"/><path d="M7 11l3 3 5-5" stroke="#22d3ee" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                ¡Configuración actualizada!
            </div>
        <?php endif; ?>
    </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
