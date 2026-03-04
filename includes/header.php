<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}
if (!isset($pageTitle)) {
  $pageTitle = "TVirtualGaming";
}
if (!isset($brandName)) {
  $brandName = "TVirtualGaming";
}
$tenantSlugAttr = isset($tenantData["tenant"]["slug"]) ? $tenantData["tenant"]["slug"] : "default";
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, "UTF-8"); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Oxanium:wght@400;600;700&family=Space+Grotesk:wght@400;500;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="/assets/css/tailwind.prod.css" />
  <link rel="stylesheet" href="/assets/css/estilos.css" />
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: {
            oxanium: ["Oxanium", "sans-serif"],
            space: ["Space Grotesk", "sans-serif"]
          }
        }
      }
    };
  </script>
  <style>
    :root {
      --bg-0: #0a0f14;
      --bg-1: #0e1722;
      --neon-0: #22d3ee;
      --neon-1: #2dd4bf;
      --neon-2: #34d399;
    }
    body {
      font-family: "Space Grotesk", "Oxanium", sans-serif;
      background: radial-gradient(circle at top, #0c1522 0%, #0a0f14 50%, #080b10 100%);
    }
    .glow-ring {
      box-shadow: 0 0 0.75rem rgba(34, 211, 238, 0.4), 0 0 2.2rem rgba(45, 212, 191, 0.2);
    }
    /* Gaming-styled scrollbar for the menu (supports WebKit + Firefox) */
    #menu-panel {
      scrollbar-width: thin;
      scrollbar-color: rgba(34, 211, 238, 0.45) transparent;
    }
    #menu-panel::-webkit-scrollbar {
      width: 10px;
    }
    #menu-panel::-webkit-scrollbar-track {
      background: linear-gradient(180deg, rgba(12, 21, 34, 0.9), rgba(8, 11, 16, 0.6));
      border-radius: 999px;
      border: 1px solid rgba(30, 41, 59, 0.7);
    }
    #menu-panel::-webkit-scrollbar-thumb {
      background: linear-gradient(180deg, rgba(34, 211, 238, 0.85), rgba(52, 211, 153, 0.85));
      border-radius: 999px;
      box-shadow: 0 0 12px rgba(34, 211, 238, 0.35);
      border: 1px solid rgba(12, 21, 34, 0.9);
    }
    #menu-panel::-webkit-scrollbar-thumb:hover {
      background: linear-gradient(180deg, rgba(34, 211, 238, 1), rgba(52, 211, 153, 1));
    }
    @keyframes floaty {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-6px); }
    }
    @keyframes fadeUp {
      from { opacity: 0; transform: translateY(14px); }
      to { opacity: 1; transform: translateY(0); }
    }
  </style>
</head>
<body class="min-h-screen text-slate-100">
  <div class="relative min-h-screen overflow-hidden">
    <div class="pointer-events-none absolute -top-24 left-1/2 h-72 w-72 -translate-x-1/2 rounded-full bg-cyan-500/15 blur-3xl"></div>
    <div class="pointer-events-none absolute bottom-0 right-0 h-64 w-64 rounded-full bg-emerald-500/10 blur-3xl"></div>

    <div class="relative mx-auto w-full max-w-6xl px-4 pb-10 pt-5" data-tenant="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, "UTF-8"); ?>">
      <header class="flex items-center justify-between">
        <button id="menu-toggle" class="rounded-full border border-slate-800 bg-slate-900/60 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Abrir menu">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.6">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="text-center">
          <p class="text-[11px] uppercase tracking-[0.3em] text-cyan-300/70">tienda</p>
          <h1 class="font-oxanium text-lg font-semibold text-white"><?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?></h1>
        </div>
        <div id="auth-container" class="relative">
          <?php if (!isset($_SESSION['auth_user'])): ?>
            <button id="auth-trigger" type="button" class="flex items-center gap-2 rounded-full border border-cyan-400/30 bg-slate-900/80 px-3 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-cyan-200 transition hover:border-cyan-300 hover:text-white">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z" />
              </svg>
              Iniciar sesión / Registrarse
            </button>
            <div id="auth-menu" class="absolute right-0 mt-2 z-[70] hidden w-48 rounded-2xl border border-slate-800 bg-slate-950/95 p-2 shadow-2xl">
              <button type="button" data-auth-open="login" class="w-full rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-left text-xs font-semibold text-slate-100 transition hover:border-cyan-400/70">Iniciar sesión</button>
              <button type="button" data-auth-open="register" class="mt-2 w-full rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-left text-xs font-semibold text-slate-100 transition hover:border-cyan-400/70">Registrarse</button>
            </div>
          <?php else: ?>
            <div class="flex items-center gap-2 rounded-full border border-cyan-400/30 bg-slate-900/80 px-3 py-1.5 text-[13px] font-semibold text-cyan-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z" />
              </svg>
              <?php echo htmlspecialchars($_SESSION['auth_user']['nombre'] ?? $_SESSION['auth_user']['full_name'] ?? $_SESSION['auth_user']['email'] ?? 'Usuario', ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>
        </div>
      </header>

      <?php
      $authFlash = $_SESSION["auth_flash"] ?? null;
      if ($authFlash) {
        unset($_SESSION["auth_flash"]);
      }
      ?>
      <?php if (!empty($authFlash["message"])): ?>
        <?php
          $flashType = $authFlash["type"] ?? "info";
          $flashClasses = $flashType === "success"
            ? "border-emerald-400/30 bg-emerald-500/10 text-emerald-200"
            : ($flashType === "error" ? "border-rose-400/30 bg-rose-500/10 text-rose-200" : "border-cyan-400/30 bg-cyan-500/10 text-cyan-200");
        ?>
        <div class="mt-4 rounded-xl border px-3 py-2 text-xs <?php echo $flashClasses; ?>">
          <?php echo htmlspecialchars($authFlash["message"], ENT_QUOTES, "UTF-8"); ?>
        </div>
      <?php endif; ?>

      <div id="menu-overlay" class="fixed inset-0 z-40 hidden bg-slate-950/70 backdrop-blur-sm"></div>
      <nav id="menu-panel" class="fixed left-1/2 top-20 z-50 hidden w-[min(92vw,420px)] max-h-[calc(100vh-96px)] -translate-x-1/2 overflow-y-auto overscroll-contain rounded-2xl border border-slate-800 bg-slate-900/95 p-4 shadow-2xl touch-pan-y" style="-webkit-overflow-scrolling: touch;">
        <div class="flex items-center justify-between">
          <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Menu</p>
          <button id="menu-close" class="rounded-full border border-slate-800 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Cerrar menu">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
        <div class="mt-4 space-y-2">
          <a href="/" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Inicio</a>
          <a href="/populares" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Juegos populares</a>
          <a href="/juegos" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-cyan-400/70">Juegos</a>
          <?php if (isset($_SESSION['auth_user'])): ?>
            <?php if (($_SESSION['auth_user']['rol'] ?? '') === 'admin'): ?>
              <hr class="my-2 border-slate-700">
              <a href="/admin/dashboard" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Dashboard</a>
              <a href="/admin/juegos" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Juegos</a>
              <a href="/admin/monedas" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Monedas</a>
              <a href="/admin/pedidos" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Pedidos</a>
              <a href="/admin/usuarios" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Usuarios</a>
              <a href="/admin/cupones" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Cupones</a>
              <a href="/admin/configuracion" class="block rounded-xl border border-emerald-400 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-emerald-300 transition hover:border-emerald-400/70">Configuración</a>
              <a href="/admin/dashboard" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-cyan-300 transition hover:border-cyan-400/70">Ir al Admin</a>
            <?php endif; ?>
            <a href="/logout" class="block rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm font-semibold text-rose-300 transition hover:border-rose-400/70">Cerrar sesión</a>
          <?php endif; ?>
        </div>
      </nav>

      <div id="auth-modal" class="fixed inset-0 z-50 hidden items-center justify-center px-4">
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" data-auth-close></div>
        <div class="relative z-10 w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900/95 p-6 shadow-2xl" style="animation: fadeUp 320ms ease-out both;">
          <button type="button" data-auth-close class="absolute right-4 top-4 rounded-full border border-slate-800 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Cerrar">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div id="auth-login" class="space-y-4">
            <div>
              <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Cuenta de usuario</p>
              <h2 class="mt-2 font-oxanium text-2xl font-semibold">Iniciar sesión</h2>
            </div>
            <form action="/login.php" method="post" class="space-y-4" novalidate>
              <input type="hidden" name="tenant" value="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, "UTF-8"); ?>" />
              <div class="space-y-3">
                <label class="block text-xs text-slate-400">Correo electrónico</label>
                <input type="email" name="email" autocomplete="email" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="nombre@correo.com" />
                <label class="block text-xs text-slate-400">Contraseña</label>
                <div class="relative">
                  <input type="password" name="password" autocomplete="current-password" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70 pr-10" placeholder="Ingresa tu contraseña" id="login-password" />
                  <button type="button" tabindex="-1" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-cyan-400" onclick="togglePassword('login-password', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12.001C3.226 16.273 7.322 19.5 12 19.5c1.658 0 3.237-.336 4.677-.947M6.228 6.228A9.956 9.956 0 0112 4.5c4.677 0 8.773 3.227 10.065 7.499a10.523 10.523 0 01-4.293 5.774M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                    </svg>
                  </button>
                </div>
              </div>
              <button type="submit" class="w-full rounded-xl border border-sky-400/30 bg-sky-500/80 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-sky-400">Iniciar sesión</button>
              <a href="/reset.php" class="block w-full text-center text-xs font-semibold text-sky-300">¿Has olvidado la contraseña?</a>
            </form>
            <button type="button" data-auth-switch="register" class="w-full text-xs font-semibold text-cyan-300">¿No tienes una cuenta? Regístrate ahora</button>
          </div>

          <div id="auth-register" class="hidden space-y-4">
            <div>
              <p class="text-xs uppercase tracking-[0.35em] text-slate-400">Cuenta</p>
              <h2 class="mt-2 font-oxanium text-2xl font-semibold">Crear cuenta</h2>
              <p class="mt-1 text-xs text-slate-400">Regístrate para empezar a operar en <?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?>.</p>
            </div>
            <form id="registro-form" class="space-y-4" novalidate autocomplete="off">
              <input type="hidden" id="tenant" value="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, 'UTF-8'); ?>" />
              <div class="space-y-3">
                <label class="block text-xs text-slate-400">Nombre completo</label>
                <input type="text" id="nombre" autocomplete="name" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="Ej. Juan Pérez" required />
                <label class="block text-xs text-slate-400">Correo electrónico</label>
                <input type="email" id="correo" autocomplete="email" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="nombre@correo.com" required />
                <label class="block text-xs text-slate-400">Número de teléfono</label>
                <input type="tel" id="telefono" autocomplete="tel" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70" placeholder="+58 412 0000000" />
                <label class="block text-xs text-slate-400">Contraseña</label>
                <div class="relative">
                  <input type="password" id="contrasena" autocomplete="new-password" class="w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-slate-100 outline-none transition focus:border-cyan-400/70 pr-10" placeholder="Crea una contraseña segura" required />
                  <button type="button" tabindex="-1" class="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-cyan-400" onclick="togglePassword('contrasena', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12.001C3.226 16.273 7.322 19.5 12 19.5c1.658 0 3.237-.336 4.677-.947M6.228 6.228A9.956 9.956 0 0112 4.5c4.677 0 8.773 3.227 10.065 7.499a10.523 10.523 0 01-4.293 5.774M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                    </svg>
                  </button>
                </div>
                <script>
                  function togglePassword(inputId, btn) {
                    const input = document.getElementById(inputId);
                    if (!input) return;
                    if (input.type === 'password') {
                      input.type = 'text';
                      btn.querySelector('svg').classList.add('text-cyan-400');
                    } else {
                      input.type = 'password';
                      btn.querySelector('svg').classList.remove('text-cyan-400');
                    }
                  }
                </script>
              </div>
              <button type="submit" id="registro-btn" class="w-full rounded-xl border border-sky-400/30 bg-sky-500/80 px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white transition hover:bg-sky-400">Registrarse ahora</button>
            </form>
            <script src="/registro.js"></script>
            <button type="button" data-auth-switch="login" class="w-full text-xs font-semibold text-cyan-300">¿Ya tienes una cuenta? Inicia sesión</button>
          </div>
        </div>
      </div>
