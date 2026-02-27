<?php
require_once __DIR__ . "/includes/data.php";
require_once __DIR__ . "/includes/auth.php";

$pageTitle = $brandName . " | Restablecer contraseña";
$notice = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $tenantSlug = auth_get_tenant_slug();
  $email = auth_normalize_email($_POST["email"] ?? "");

  if ($email !== "") {
    $users = auth_load_users($tenantSlug);
    foreach ($users as &$user) {
      if (!empty($user["email"]) && auth_normalize_email($user["email"]) === $email) {
        $user["reset_requested_at"] = date("c");
        break;
      }
    }
    unset($user);
    auth_save_users($tenantSlug, $users);
  }

  $notice = "Si el correo existe, enviamos instrucciones para restablecer la contraseña.";
}

include __DIR__ . "/includes/header.php";
?>

      <section class="mt-10 flex items-center justify-center">
        <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/95 p-6 shadow-2xl" style="animation: fadeUp 320ms ease-out both;">
          <div>
            <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Recuperación</p>
            <h2 class="mt-2 font-oxanium text-2xl font-semibold">Restablecer contraseña</h2>
            <p class="mt-1 text-xs text-slate-400">Ingresa tu correo para recibir instrucciones.</p>
          </div>
          <?php if ($notice !== ""): ?>
            <div class="mt-4 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200">
              <?php echo htmlspecialchars($notice, ENT_QUOTES, "UTF-8"); ?>
            </div>
          <?php endif; ?>
          <form action="/reset.php" method="post" class="mt-4 space-y-4" novalidate>
            <input type="hidden" name="tenant" value="<?php echo htmlspecialchars($tenantData["tenant"]["slug"] ?? "default", ENT_QUOTES, "UTF-8"); ?>" />
            <label class="block text-xs text-slate-400">Correo electrónico</label>
            <input type="email" name="email" autocomplete="email" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="nombre@correo.com" />
            <button type="submit" class="w-full rounded-xl border border-sky-400/30 bg-sky-500/80 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-sky-400">Enviar instrucciones</button>
          </form>
        </div>
      </section>

<?php
include __DIR__ . "/includes/footer.php";
?>
