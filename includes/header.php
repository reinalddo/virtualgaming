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
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <!--<link rel="stylesheet" href="/assets/css/estilos.css" />-->
  <link rel="stylesheet" href="/assets/css/estilos.css" />
  <!-- Bootstrap does not require JS config for colors here -->
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
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var menuToggle = document.getElementById('menu-toggle');
      var menuPanel = document.getElementById('menu-panel');
      var menuOverlay = document.getElementById('menu-overlay');
      var menuClose = document.getElementById('menu-close');
      if (menuToggle && menuPanel && menuOverlay) {
        menuToggle.addEventListener('click', function() {
          menuPanel.classList.remove('d-none');
          menuOverlay.classList.remove('d-none');
        });
        menuOverlay.addEventListener('click', function() {
          menuPanel.classList.add('d-none');
          menuOverlay.classList.add('d-none');
        });
      }
      if (menuClose) {
        menuClose.addEventListener('click', function() {
          menuPanel.classList.add('d-none');
          menuOverlay.classList.add('d-none');
        });
      }
    });
  </script>
</head>
<body class="bg-dark text-light min-vh-100">
  <div class="position-relative min-vh-100 overflow-hidden">
    <div class="position-absolute top-0 start-50 translate-middle-x rounded-circle" style="height:18rem;width:18rem;background:rgba(34,211,238,0.15);filter:blur(48px);pointer-events:none;"></div>
    <div class="position-absolute bottom-0 end-0 rounded-circle" style="height:16rem;width:16rem;background:rgba(52,211,153,0.10);filter:blur(48px);pointer-events:none;"></div>

    <div class="container-lg position-relative pb-5 pt-4" data-tenant="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, "UTF-8"); ?>">
      <header class="d-flex align-items-center justify-content-between">
        <button id="menu-toggle" class="btn btn-outline-info rounded-circle d-flex align-items-center justify-content-center" style="width:44px;height:44px;" aria-label="Abrir menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" fill="currentColor" class="bi bi-list" viewBox="0 0 16 16">
            <path fill-rule="evenodd" d="M2.5 12.5a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1h-10a.5.5 0 0 1-.5-.5z"/>
          </svg>
        </button>
        <div class="text-center">
          <p class="small text-uppercase text-info mb-0" style="letter-spacing:0.3em;">tienda</p>
          <h1 class="fw-bold" style="font-family:'Oxanium', 'Space Grotesk', sans-serif;font-size:1.25rem;color:#fff;"><?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?></h1>
        </div>
        <div id="auth-container" class="position-relative">
          <?php if (!isset($_SESSION['auth_user'])): ?>
            <button id="auth-trigger" type="button" class="d-flex align-items-center gap-2 rounded-pill border border-info bg-dark px-3 py-1 text-uppercase fw-semibold text-info" style="font-size:11px;">
              <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 20.118a7.5 7.5 0 0115 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.5-1.632z" />
              </svg>
              Iniciar sesión / Registrarse
            </button>
            <div id="auth-menu" class="position-absolute end-0 mt-2 z-3 d-none w-100 rounded-4 border bg-dark p-2 shadow">
              <button type="button" data-auth-open="login" class="w-100 rounded-3 border bg-dark px-3 py-2 text-start text-xs fw-semibold text-light">Iniciar sesión</button>
              <button type="button" data-auth-open="register" class="mt-2 w-100 rounded-3 border bg-dark px-3 py-2 text-start text-xs fw-semibold text-light">Registrarse</button>
            </div>
          <?php else: ?>
            <div class="d-flex align-items-center gap-2 rounded-pill border border-info bg-dark px-3 py-1 text-info fw-semibold" style="font-size:13px;">
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
        <div class="mt-4 rounded-3 border px-3 py-2 small <?php echo $flashClasses; ?>">
          <?php echo htmlspecialchars($authFlash["message"], ENT_QUOTES, "UTF-8"); ?>
        </div>
      <?php endif; ?>

      <div id="menu-overlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background:rgba(12,21,34,0.7);backdrop-filter:blur(4px);z-index:1040;"></div>
      <nav id="menu-panel" class="position-fixed start-50 top-0 translate-middle-x d-none w-100" style="max-width:420px;max-height:calc(100vh - 96px);overflow-y:auto;border-radius:1.5rem;border:2px solid #22d3ee;background:rgba(14,23,34,0.97);padding:1.5rem;box-shadow:0 0 32px #22d3ee, 0 0 8px #2dd4bf;z-index:1050;">
        <button id="menu-close" class="btn btn-outline-info rounded-circle position-absolute end-0 top-0 m-3 d-flex align-items-center justify-content-center" style="width:40px;height:40px;box-shadow:0 0 12px #22d3ee;z-index:1060;" aria-label="Cerrar menú">
          <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" fill="currentColor" class="bi bi-x" viewBox="0 0 16 16">
            <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/>
          </svg>
        </button>
        <div class="d-flex align-items-center justify-content-between">
          <p class="small text-uppercase text-secondary mb-0" style="letter-spacing:0.35em;">Menu</p>
        </div>
        <div class="mt-4 d-grid gap-2">
          <a href="/" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Inicio</a>
          <a href="/populares" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Juegos populares</a>
          <a href="/juegos" class="btn btn-dark border rounded-3 px-4 py-3 fw-semibold">Juegos</a>
          <?php if (isset($_SESSION['auth_user'])): ?>
            <?php if (($_SESSION['auth_user']['rol'] ?? '') === 'admin'): ?>
              <hr class="my-2 border-slate-700">
              <a href="/admin/dashboard" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Dashboard</a>
              <a href="/admin/juegos" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Juegos</a>
              <a href="/admin/monedas" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Monedas</a>
              <a href="/admin/pedidos" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Pedidos</a>
              <a href="/admin/usuarios" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Usuarios</a>
              <a href="/admin/cupones" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Cupones</a>
              <a href="/admin/configuracion" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Configuración</a>
              <a href="/admin/dashboard" class="btn btn-admin border rounded-3 px-4 py-3 fw-semibold">Ir al Admin</a>
            <?php endif; ?>
            <a href="/logout" class="btn btn-danger border rounded-3 px-4 py-3 fw-semibold">Cerrar sesión</a>
          <?php endif; ?>
        </div>
      </nav>

      <div id="auth-modal" class="position-fixed top-0 start-0 w-100 h-100 z-5 d-none d-flex align-items-center justify-content-center px-4">
        <div class="position-absolute top-0 start-0 w-100 h-100" style="background:rgba(12,21,34,0.7);backdrop-filter:blur(4px);" data-auth-close></div>
        <div class="position-relative z-1 w-100" style="max-width:420px;border-radius:1.5rem;border:1px solid #334155;background:rgba(14,23,34,0.95);padding:1.5rem;box-shadow:0 0 2rem rgba(0,0,0,0.5);animation:fadeUp 320ms ease-out both;">
          <button type="button" data-auth-close class="absolute right-4 top-4 rounded-full border border-slate-800 bg-slate-950/70 p-2 text-slate-200 transition hover:border-cyan-400/70 hover:text-cyan-200" aria-label="Cerrar">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div id="auth-login" class="d-grid gap-4">
            <div>
              <p class="small text-uppercase text-secondary mb-0" style="letter-spacing:0.35em;">Cuenta de usuario</p>
              <h2 class="mt-2" style="font-family:'Oxanium',sans-serif;font-size:2rem;font-weight:600;">Iniciar sesión</h2>
            </div>
            <form action="/login.php" method="post" class="d-grid gap-4" novalidate>
              <input type="hidden" name="tenant" value="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, "UTF-8"); ?>" />
              <div class="d-grid gap-3">
                <label class="form-label small text-secondary">Correo electrónico</label>
                <input type="email" name="email" autocomplete="email" class="form-control rounded-3 bg-dark text-light border" placeholder="nombre@correo.com" />
                <label class="form-label small text-secondary">Contraseña</label>
                <div class="relative">
                  <input type="password" name="password" autocomplete="current-password" class="form-control rounded-3 bg-dark text-light border pe-5" placeholder="Ingresa tu contraseña" id="login-password" />
                  <button type="button" tabindex="-1" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-info" style="padding:0;" onclick="togglePassword('login-password', this)">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12.001C3.226 16.273 7.322 19.5 12 19.5c1.658 0 3.237-.336 4.677-.947M6.228 6.228A9.956 9.956 0 0112 4.5c4.677 0 8.773 3.227 10.065 7.499a10.523 10.523 0 01-4.293 5.774M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                      <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                    </svg>
                  </button>
                </div>
              </div>
              <button type="submit" class="btn btn-info w-100 rounded-3 px-4 py-2 fw-semibold text-uppercase">Iniciar sesión</button>
              <a href="/reset.php" class="d-block w-100 text-center small fw-semibold text-info">¿Has olvidado la contraseña?</a>
            </form>
            <button type="button" data-auth-switch="register" class="btn btn-link w-100 small fw-semibold text-info">¿No tienes una cuenta? Regístrate ahora</button>
          </div>

          <div id="auth-register" class="d-none d-grid gap-4">
            <div>
              <p class="small text-uppercase text-secondary mb-0" style="letter-spacing:0.35em;">Cuenta</p>
              <h2 class="mt-2" style="font-family:'Oxanium',sans-serif;font-size:2rem;font-weight:600;">Crear cuenta</h2>
              <p class="mt-1 small text-secondary">Regístrate para empezar a operar en <?php echo htmlspecialchars($brandName, ENT_QUOTES, "UTF-8"); ?>.</p>
            </div>
            <form id="registro-form" class="d-grid gap-4" novalidate autocomplete="off">
              <input type="hidden" id="tenant" value="<?php echo htmlspecialchars($tenantSlugAttr, ENT_QUOTES, 'UTF-8'); ?>" />
              <div class="d-grid gap-3">
                <label class="form-label small text-secondary">Nombre completo</label>
                <input type="text" id="nombre" autocomplete="name" class="form-control rounded-3 bg-dark text-light border" placeholder="Ej. Juan Pérez" required />
                <label class="form-label small text-secondary">Correo electrónico</label>
                <input type="email" id="correo" autocomplete="email" class="form-control rounded-3 bg-dark text-light border" placeholder="nombre@correo.com" required />
                <label class="form-label small text-secondary">Número de teléfono</label>
                <input type="tel" id="telefono" autocomplete="tel" class="form-control rounded-3 bg-dark text-light border" placeholder="+58 412 0000000" />
                <label class="form-label small text-secondary">Contraseña</label>
                <div class="relative">
                  <input type="password" id="contrasena" autocomplete="new-password" class="form-control rounded-3 bg-dark text-light border pe-5" placeholder="Crea una contraseña segura" required />
                  <button type="button" tabindex="-1" class="btn btn-link position-absolute end-0 top-50 translate-middle-y text-info" style="padding:0;" onclick="togglePassword('contrasena', this)">
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
              <button type="submit" id="registro-btn" class="btn btn-info w-100 rounded-3 px-4 py-2 fw-semibold text-uppercase">Registrarse ahora</button>
            </form>
            <script src="/registro.js"></script>
            <button type="button" data-auth-switch="login" class="btn btn-link w-100 small fw-semibold text-info">¿Ya tienes una cuenta? Inicia sesión</button>
          </div>
        </div>
      </div>
