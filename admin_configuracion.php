<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/includes/store_config.php';
require_once __DIR__ . '/includes/home_gallery.php';
require_once __DIR__ . '/includes/payment_methods.php';

$activeTab = defined('ADMIN_CONFIG_ACTIVE_TAB') ? ADMIN_CONFIG_ACTIVE_TAB : ($_GET['tab'] ?? 'correo');
if (!in_array($activeTab, ['correo', 'cabecera', 'sociales', 'galeria', 'metodos-pago'], true)) {
    $activeTab = 'correo';
}

home_gallery_ensure_table();
payment_methods_ensure_table();
$cfg = store_config_all();
$logoTienda = trim((string) ($cfg['logo_tienda'] ?? ''));
$galleryItems = home_gallery_all();
$paymentCurrencies = payment_methods_currency_options();
$galleryEditId = isset($_GET['editar_galeria']) ? intval($_GET['editar_galeria']) : 0;
$galleryEditItem = $galleryEditId > 0 ? home_gallery_find($galleryEditId) : null;
$galleryForm = [
    'titulo' => $galleryEditItem['titulo'] ?? '',
    'descripcion1' => $galleryEditItem['descripcion1'] ?? '',
    'descripcion2' => $galleryEditItem['descripcion2'] ?? '',
    'url' => $galleryEditItem['url'] ?? '',
    'abrir_nueva_pestana' => !empty($galleryEditItem['abrir_nueva_pestana']),
    'destacado' => !empty($galleryEditItem['destacado']),
    'imagen' => $galleryEditItem['imagen'] ?? '',
];
  $paymentMethods = payment_methods_all();
  $paymentMethodEditId = isset($_GET['editar_metodo_pago']) ? intval($_GET['editar_metodo_pago']) : 0;
  $paymentMethodEditItem = $paymentMethodEditId > 0 ? payment_methods_find($paymentMethodEditId) : null;
  $paymentMethodForm = [
    'nombre' => $paymentMethodEditItem['nombre'] ?? '',
    'datos' => $paymentMethodEditItem['datos'] ?? '',
    'moneda_id' => isset($paymentMethodEditItem['moneda_id']) ? (int) $paymentMethodEditItem['moneda_id'] : 0,
    'referencia_digitos' => isset($paymentMethodEditItem['referencia_digitos']) ? max(0, (int) $paymentMethodEditItem['referencia_digitos']) : 0,
    'activo' => !array_key_exists('activo', $paymentMethodEditItem ?? []) ? true : !empty($paymentMethodEditItem['activo']),
  ];
?>
<style>
  .neon-card {
    background: #181f2a !important;
    border-radius: 18px !important;
    border: 2px solid #00fff7 !important;
    box-shadow: 0 0 32px #00fff733, 0 0 8px #00fff7;
    color: #00fff7;
    font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif;
  }
  .neon-card .form-label,
  .neon-card .form-check-label,
  .neon-card .form-text,
  .neon-card .table,
  .neon-card .table td,
  .neon-card .table th {
    color: #c9f9ff !important;
  }
  .neon-card .form-control,
  .neon-card .form-select {
    background: #222c3a !important;
    color: #e9fdff !important;
    border: 1px solid #00fff7 !important;
    border-radius: 12px !important;
    box-shadow: 0 0 8px #00fff733;
  }
  .neon-card .form-control:focus,
  .neon-card .form-select:focus {
    border-color: #34d399 !important;
    box-shadow: 0 0 16px #34d39999;
    outline: none;
  }
  .neon-btn {
    background: linear-gradient(90deg, #00fff7 0%, #34d399 100%);
    color: #181f2a !important;
    font-weight: bold;
    border-radius: 16px !important;
    box-shadow: 0 0 16px #00fff7, 0 0 32px #34d39999;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    border: none;
    transition: background 0.2s, box-shadow 0.2s, transform 0.2s;
  }
  .neon-btn:hover {
    background: linear-gradient(90deg, #34d399 0%, #00fff7 100%);
    box-shadow: 0 0 32px #00fff7, 0 0 16px #34d39999;
    transform: translateY(-1px);
  }
  .neon-tabs-wrap {
    border: 1px solid rgba(34, 211, 238, 0.22);
    border-radius: 20px;
    background: rgba(15, 23, 42, 0.72);
    box-shadow: inset 0 0 0 1px rgba(45, 212, 191, 0.08), 0 0 28px rgba(34, 211, 238, 0.08);
    padding: 0.5rem;
  }
  .neon-tab-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 52px;
    border: 1px solid rgba(34, 211, 238, 0.24);
    border-radius: 16px;
    background: rgba(15, 23, 42, 0.76);
    color: #9be7ff;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-decoration: none;
    transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
  }
  .neon-tab-link:hover {
    color: #d8fbff;
    border-color: rgba(45, 212, 191, 0.58);
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.14);
    transform: translateY(-1px);
  }
  .neon-tab-link.active {
    background: linear-gradient(135deg, rgba(34, 211, 238, 0.22), rgba(52, 211, 153, 0.12));
    color: #ffffff;
    border-color: rgba(34, 211, 238, 0.7);
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.22), inset 0 0 12px rgba(34, 211, 238, 0.08);
  }
  .config-section-note {
    border-radius: 16px;
    border: 1px solid rgba(34, 211, 238, 0.2);
    background: rgba(15, 23, 42, 0.55);
    color: rgba(216, 251, 255, 0.82);
    padding: 1rem;
  }
  .header-logo-preview,
  .gallery-image-preview {
    width: 100%;
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.48);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.96), rgba(30, 41, 59, 0.9));
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 18px rgba(34, 211, 238, 0.16);
  }
  .header-logo-preview {
    max-width: 128px;
    aspect-ratio: 1 / 1;
  }
  .gallery-image-preview {
    aspect-ratio: 16 / 6;
    max-width: none;
  }
  .header-logo-preview img,
  .gallery-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
  }
  .header-logo-empty,
  .gallery-image-empty {
    color: rgba(155, 231, 255, 0.72);
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
  }
  .gallery-table-wrap {
    border: 1px solid rgba(34, 211, 238, 0.2);
    border-radius: 18px;
    background: rgba(15, 23, 42, 0.58);
    padding: 1rem;
    box-shadow: 0 0 24px rgba(34, 211, 238, 0.08);
  }
  .gallery-table-wrap .table {
    margin-bottom: 0;
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(34, 211, 238, 0.04);
    --bs-table-striped-color: #e9fdff;
    --bs-table-border-color: rgba(34, 211, 238, 0.15);
  }
  .gallery-thumb {
    width: 72px;
    height: 72px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid rgba(34, 211, 238, 0.42);
    box-shadow: 0 0 14px rgba(34, 211, 238, 0.16);
    background: #0f172a;
  }
  .gallery-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
  .gallery-card-mobile {
    border-radius: 18px;
    border: 1px solid rgba(34, 211, 238, 0.28);
    background: linear-gradient(180deg, rgba(15, 23, 42, 0.92), rgba(30, 41, 59, 0.78));
    box-shadow: 0 0 22px rgba(34, 211, 238, 0.08);
    padding: 1rem;
  }
  .gallery-badge-neon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 999px;
    border: 1px solid rgba(34, 211, 238, 0.5);
    padding: 0.25rem 0.6rem;
    font-size: 0.75rem;
    font-weight: 700;
    color: #9be7ff;
    background: rgba(34, 211, 238, 0.08);
  }
</style>
<div class="container mt-5 mb-5">
  <div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
      <div class="neon-tabs-wrap mb-4">
        <div class="row g-2">
          <div class="col-12 col-md-6 col-xl">
            <a href="/admin/configuracion?tab=correo" class="neon-tab-link <?= $activeTab === 'correo' ? 'active' : '' ?>">Configuración de correo</a>
          </div>
          <div class="col-12 col-md-6 col-xl">
            <a href="/admin/configuracion?tab=cabecera" class="neon-tab-link <?= $activeTab === 'cabecera' ? 'active' : '' ?>">Datos de cabecera</a>
          </div>
          <div class="col-12 col-md-6 col-xl">
            <a href="/admin/configuracion?tab=sociales" class="neon-tab-link <?= $activeTab === 'sociales' ? 'active' : '' ?>">Redes Sociales</a>
          </div>
          <div class="col-12 col-md-6 col-xl">
            <a href="/admin/configuracion?tab=galeria" class="neon-tab-link <?= $activeTab === 'galeria' ? 'active' : '' ?>">Galería</a>
          </div>
          <div class="col-12 col-md-6 col-xl">
            <a href="/admin/configuracion?tab=metodos-pago" class="neon-tab-link <?= $activeTab === 'metodos-pago' ? 'active' : '' ?>">Métodos de Pago</a>
          </div>
        </div>
      </div>

      <div class="card neon-card mb-4">
        <div class="card-header text-center py-4" style="background: linear-gradient(90deg, #00fff7 0%, #34d399 100%); color: #181f2a; border-radius: 16px 16px 0 0;">
          <h2 class="h4 fw-bold mb-0" style="font-family: 'Oxanium', 'Montserrat', 'Arial', sans-serif; letter-spacing: 0.08em;">
            <?php if ($activeTab === 'correo'): ?>Configuración de correo corporativo<?php elseif ($activeTab === 'cabecera'): ?>Datos de cabecera<?php elseif ($activeTab === 'sociales'): ?>Redes Sociales<?php elseif ($activeTab === 'galeria'): ?>Galería principal del index<?php else: ?>Métodos de Pago<?php endif; ?>
          </h2>
        </div>
        <div class="card-body p-4">
          <?php if ($activeTab === 'correo'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="correo">
              <div class="config-section-note mb-4">Configura aquí el correo corporativo y los parámetros SMTP usados por la tienda.</div>
              <div class="mb-3">
                <label class="form-label">Correo corporativo</label>
                <input type="email" name="correo_corporativo" value="<?= htmlspecialchars($cfg['correo_corporativo'] ?? '') ?>" required class="form-control" placeholder="correo@tudominio.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP Host</label>
                <input type="text" name="smtp_host" value="<?= htmlspecialchars($cfg['smtp_host'] ?? '') ?>" required class="form-control" placeholder="smtp.tuservidor.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP User</label>
                <input type="text" name="smtp_user" value="<?= htmlspecialchars($cfg['smtp_user'] ?? '') ?>" required class="form-control" placeholder="usuario@tudominio.com">
              </div>
              <div class="mb-3">
                <label class="form-label">SMTP Password</label>
                <input type="password" name="smtp_pass" value="<?= htmlspecialchars($cfg['smtp_pass'] ?? '') ?>" class="form-control" placeholder="••••••••">
              </div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">SMTP Port</label>
                  <input type="number" name="smtp_port" value="<?= htmlspecialchars($cfg['smtp_port'] ?? 587) ?>" required class="form-control" placeholder="587">
                </div>
                <div class="col-md-6">
                  <label class="form-label">SMTP Secure</label>
                  <select name="smtp_secure" class="form-select">
                    <option value="tls" <?= ($cfg['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                    <option value="ssl" <?= ($cfg['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                  </select>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar configuración de correo</button>
            </form>
          <?php elseif ($activeTab === 'cabecera'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="config_section" value="cabecera">
              <div class="config-section-note mb-4">Controla el prefijo, nombre y logo de la tienda. El mismo logo también se usa como favicon.</div>
              <div class="row g-4 align-items-start">
                <div class="col-md-8">
                  <div class="mb-3">
                    <label class="form-label">Nombre Prefijo</label>
                    <input type="text" name="nombre_prefijo" value="<?= htmlspecialchars($cfg['nombre_prefijo'] ?? 'TIENDA') ?>" required class="form-control" placeholder="TIENDA">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Nombre Tienda</label>
                    <input type="text" name="nombre_tienda" value="<?= htmlspecialchars($cfg['nombre_tienda'] ?? 'TVirtualGaming') ?>" required class="form-control" placeholder="TVirtualGaming">
                  </div>
                  <div class="mb-3">
                    <label class="form-label">Logo tienda</label>
                    <input type="file" name="logo_tienda" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control">
                    <div class="form-text mt-2">Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 2 MB.</div>
                  </div>
                  <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" id="eliminarLogoTienda" name="eliminar_logo_tienda">
                    <label class="form-check-label" for="eliminarLogoTienda">Eliminar logo actual</label>
                  </div>
                </div>
                <div class="col-md-4">
                  <label class="form-label d-block">Vista previa del logo</label>
                  <div class="header-logo-preview">
                    <?php if ($logoTienda !== ''): ?>
                      <img src="<?= htmlspecialchars($logoTienda, ENT_QUOTES, 'UTF-8') ?>" alt="Logo de la tienda">
                    <?php else: ?>
                      <span class="header-logo-empty">Sin logo</span>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar datos de cabecera</button>
            </form>
          <?php elseif ($activeTab === 'sociales'): ?>
            <form method="post">
              <input type="hidden" name="config_section" value="sociales">
              <div class="config-section-note mb-4">Registra los enlaces oficiales de la tienda para mostrarlos o reutilizarlos desde otras secciones del sitio.</div>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Facebook</label>
                  <input type="url" name="facebook" value="<?= htmlspecialchars($cfg['facebook'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://facebook.com/tupagina">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Instagram</label>
                  <input type="url" name="instagram" value="<?= htmlspecialchars($cfg['instagram'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://instagram.com/tucuenta">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Whatsapp</label>
                  <input type="text" name="whatsapp" value="<?= htmlspecialchars($cfg['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://wa.me/58412XXXXXXX o +58 412XXXXXXX">
                </div>
                <div class="col-md-6">
                  <label class="form-label">Whatsapp Channel</label>
                  <input type="url" name="whatsapp_channel" value="<?= htmlspecialchars($cfg['whatsapp_channel'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="https://whatsapp.com/channel/...">
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4">Guardar redes sociales</button>
            </form>
          <?php elseif ($activeTab === 'galeria'): ?>
            <form method="post" enctype="multipart/form-data">
              <input type="hidden" name="config_section" value="galeria">
              <input type="hidden" name="gallery_id" value="<?= $galleryEditItem ? (int) $galleryEditItem['id'] : 0 ?>">
              <div class="config-section-note mb-4">Administra el slider principal del index. Si marcas un elemento como destacado, también aparecerá en el bloque inferior y se desmarcará cualquier otro destacado existente.</div>
              <div class="row g-4 align-items-start">
                <div class="col-12">
                  <label class="form-label d-block">Vista previa de imagen</label>
                  <div class="gallery-image-preview mb-2" id="gallery-image-preview" data-original-src="<?= htmlspecialchars($galleryForm['imagen'], ENT_QUOTES, 'UTF-8') ?>">
                    <?php if ($galleryForm['imagen'] !== ''): ?>
                      <img src="<?= htmlspecialchars($galleryForm['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="Vista previa de galería" id="gallery-image-preview-img">
                    <?php else: ?>
                      <span class="gallery-image-empty" id="gallery-image-preview-empty">Sin imagen</span>
                    <?php endif; ?>
                  </div>
                  <div class="form-text">La vista previa usa proporción horizontal para que se acerque a cómo se verá en el inicio.</div>
                </div>
                <div class="col-lg-8">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label">Título</label>
                      <input type="text" name="titulo" value="<?= htmlspecialchars($galleryForm['titulo']) ?>" required class="form-control" placeholder="Bienvenida">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Descripción 1</label>
                      <input type="text" name="descripcion1" value="<?= htmlspecialchars($galleryForm['descripcion1']) ?>" required class="form-control" placeholder="+10% en tu primera compra">
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Descripción 2</label>
                      <input type="text" name="descripcion2" value="<?= htmlspecialchars($galleryForm['descripcion2']) ?>" required class="form-control" placeholder="Usa el código START10">
                    </div>
                    <div class="col-md-7">
                      <label class="form-label">URL</label>
                      <input type="url" name="url" value="<?= htmlspecialchars($galleryForm['url']) ?>" class="form-control" placeholder="https://tusitio.com/promocion">
                      <div class="form-text">Si la dejas vacía, la imagen no tendrá enlace.</div>
                    </div>
                    <div class="col-md-5">
                      <label class="form-label">Comportamiento del enlace</label>
                      <select name="abrir_nueva_pestana" class="form-select">
                        <option value="0" <?= !$galleryForm['abrir_nueva_pestana'] ? 'selected' : '' ?>>Abrir en la misma página</option>
                        <option value="1" <?= $galleryForm['abrir_nueva_pestana'] ? 'selected' : '' ?>>Abrir en otra pestaña</option>
                      </select>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Imagen</label>
                      <input type="file" name="imagen" id="gallery-image-input" accept="image/png,image/jpeg,image/webp,image/gif" class="form-control" <?= $galleryEditItem ? '' : 'required' ?>>
                      <div class="form-text">Formatos permitidos: JPG, PNG, WEBP o GIF. Tamaño máximo: 4 MB.</div>
                    </div>
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="destacadoGaleria" name="destacado" <?= $galleryForm['destacado'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="destacadoGaleria">Marcar como destacado</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4">
                  <?php if ($galleryEditItem): ?>
                    <a href="/admin/configuracion?tab=galeria" class="btn btn-outline-info w-100 rounded-4">Cancelar edición</a>
                  <?php endif; ?>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4"><?= $galleryEditItem ? 'Actualizar elemento de galería' : 'Crear elemento de galería' ?></button>
            </form>

            <div class="mt-5">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="h5 fw-bold mb-0 text-info">Elementos registrados</h3>
                <span class="gallery-badge-neon"><?= count($galleryItems) ?> elementos</span>
              </div>
              <?php if (empty($galleryItems)): ?>
                <div class="config-section-note">Aún no hay elementos en la galería. Crea el primero para que aparezca en el slider del index.</div>
              <?php else: ?>
                <div class="gallery-table-wrap d-none d-md-block">
                  <div class="table-responsive">
                    <table class="table table-striped align-middle">
                      <thead>
                        <tr>
                          <th>Imagen</th>
                          <th>Título</th>
                          <th>Textos</th>
                          <th>URL</th>
                          <th>Destino</th>
                          <th>Destacado</th>
                          <th class="text-end">Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($galleryItems as $item): ?>
                          <tr>
                            <td>
                              <div class="gallery-thumb">
                                <img src="<?= htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                              </div>
                            </td>
                            <td class="fw-bold"><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                              <div><?= htmlspecialchars($item['descripcion1'], ENT_QUOTES, 'UTF-8') ?></div>
                              <div class="small text-secondary"><?= htmlspecialchars($item['descripcion2'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                              <?php if (!empty($item['url'])): ?>
                                <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer" class="text-info text-break"><?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?></a>
                              <?php else: ?>
                                <span class="text-secondary">Sin URL</span>
                              <?php endif; ?>
                            </td>
                            <td><?= !empty($item['abrir_nueva_pestana']) ? 'Nueva pestaña' : 'Misma página' ?></td>
                            <td><?= !empty($item['destacado']) ? '<span class="gallery-badge-neon">Sí</span>' : '<span class="text-secondary">No</span>' ?></td>
                            <td class="text-end">
                              <div class="d-inline-flex gap-2">
                                <a href="/admin/configuracion?tab=galeria&editar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-info btn-sm rounded-4">Editar</a>
                                <a href="/admin/configuracion?tab=galeria&eliminar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4" onclick="return confirm('¿Eliminar este elemento de galería?');">Eliminar</a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="d-grid gap-3 d-md-none">
                  <?php foreach ($galleryItems as $item): ?>
                    <div class="gallery-card-mobile">
                      <div class="d-flex gap-3 align-items-start">
                        <div class="gallery-thumb flex-shrink-0">
                          <img src="<?= htmlspecialchars($item['imagen'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                        <div class="flex-grow-1">
                          <div class="d-flex justify-content-between gap-2 align-items-start">
                            <h4 class="h6 fw-bold mb-1 text-info"><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></h4>
                            <?php if (!empty($item['destacado'])): ?>
                              <span class="gallery-badge-neon">Destacado</span>
                            <?php endif; ?>
                          </div>
                          <div class="small text-light"><?= htmlspecialchars($item['descripcion1'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small text-secondary"><?= htmlspecialchars($item['descripcion2'], ENT_QUOTES, 'UTF-8') ?></div>
                          <div class="small mt-2 text-info-emphasis"><?= !empty($item['url']) ? htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') : 'Sin URL' ?></div>
                          <div class="small text-secondary mt-1"><?= !empty($item['abrir_nueva_pestana']) ? 'Nueva pestaña' : 'Misma página' ?></div>
                        </div>
                      </div>
                      <div class="d-flex gap-2 mt-3">
                        <a href="/admin/configuracion?tab=galeria&editar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-info btn-sm rounded-4 flex-fill">Editar</a>
                        <a href="/admin/configuracion?tab=galeria&eliminar_galeria=<?= (int) $item['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4 flex-fill" onclick="return confirm('¿Eliminar este elemento de galería?');">Eliminar</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php else: ?>
            <form method="post">
              <input type="hidden" name="config_section" value="metodos-pago">
              <input type="hidden" name="payment_method_id" value="<?= $paymentMethodEditItem ? (int) $paymentMethodEditItem['id'] : 0 ?>">
              <div class="config-section-note mb-4">Registra los métodos de pago disponibles para transferencias, con el nombre visible al cliente y los datos exactos donde debe realizar el pago.</div>
              <div class="row g-4 align-items-start">
                <div class="col-lg-8">
                  <div class="row g-3">
                    <div class="col-12">
                      <label class="form-label">Nombre Método de Pago</label>
                      <input type="text" name="nombre_metodo_pago" value="<?= htmlspecialchars($paymentMethodForm['nombre'], ENT_QUOTES, 'UTF-8') ?>" required class="form-control" placeholder="Mercantil, Binance, Zelle">
                    </div>
                    <div class="col-12">
                      <label class="form-label">Datos Método de Pago</label>
                      <textarea name="datos_metodo_pago" rows="6" required class="form-control" placeholder="Titular, número de cuenta, correo, teléfono o cualquier dato necesario para transferir."><?= htmlspecialchars($paymentMethodForm['datos'], ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Moneda del Método</label>
                      <select name="moneda_metodo_pago" required class="form-select">
                        <option value="">Selecciona una moneda</option>
                        <?php foreach ($paymentCurrencies as $currency): ?>
                          <option value="<?= (int) $currency['id'] ?>" <?= (int) $paymentMethodForm['moneda_id'] === (int) $currency['id'] ? 'selected' : '' ?>><?= htmlspecialchars($currency['nombre'] . ' (' . $currency['clave'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Dígitos de referencia permitidos</label>
                      <input type="number" name="referencia_digitos_metodo_pago" min="0" step="1" value="<?= (int) $paymentMethodForm['referencia_digitos'] ?>" class="form-control" placeholder="0 = sin límite">
                      <div class="form-text">Si colocas `0` o lo dejas vacío, el número de referencia será sin límite. Si colocas `5`, se validarán 5 dígitos; si colocas `7`, se validarán 7, y así sucesivamente.</div>
                    </div>
                    <div class="col-12">
                      <div class="form-check">
                        <input class="form-check-input" type="checkbox" value="1" id="activoMetodoPago" name="activo_metodo_pago" <?= $paymentMethodForm['activo'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="activoMetodoPago">Método de pago activo</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-4">
                  <div class="config-section-note">
                    Usa este tab para crear, editar o desactivar cuentas receptoras como bancos, billeteras o servicios de pago.
                  </div>
                  <?php if ($paymentMethodEditItem): ?>
                    <a href="/admin/configuracion?tab=metodos-pago" class="btn btn-outline-info w-100 rounded-4 mt-3">Cancelar edición</a>
                  <?php endif; ?>
                </div>
              </div>
              <button type="submit" class="neon-btn w-100 py-3 mt-4"><?= $paymentMethodEditItem ? 'Actualizar método de pago' : 'Crear método de pago' ?></button>
            </form>

            <div class="mt-5">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h3 class="h5 fw-bold mb-0 text-info">Métodos registrados</h3>
                <span class="gallery-badge-neon"><?= count($paymentMethods) ?> métodos</span>
              </div>
              <?php if (empty($paymentMethods)): ?>
                <div class="config-section-note">Aún no hay métodos de pago registrados. Crea el primero para empezar a administrarlos.</div>
              <?php else: ?>
                <div class="gallery-table-wrap d-none d-md-block">
                  <div class="table-responsive">
                    <table class="table table-striped align-middle">
                      <thead>
                        <tr>
                          <th>Nombre</th>
                          <th>Moneda</th>
                          <th>Dígitos Ref.</th>
                          <th>Datos</th>
                          <th>Estado</th>
                          <th class="text-end">Acciones</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($paymentMethods as $method): ?>
                          <tr>
                            <td class="fw-bold"><?= htmlspecialchars($method['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(trim((string) (($method['moneda_nombre'] ?? '') . ' ' . (!empty($method['moneda_clave']) ? '(' . $method['moneda_clave'] . ')' : ''))) ?: 'Sin moneda', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($method['referencia_digitos']) ? (int) $method['referencia_digitos'] : 'Sin límite' ?></td>
                            <td style="white-space: pre-line;"><?= htmlspecialchars($method['datos'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= !empty($method['activo']) ? '<span class="gallery-badge-neon">Activo</span>' : '<span class="text-secondary">Inactivo</span>' ?></td>
                            <td class="text-end">
                              <div class="d-inline-flex gap-2 flex-wrap justify-content-end">
                                <a href="/admin/configuracion?tab=metodos-pago&editar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-info btn-sm rounded-4">Editar</a>
                                <a href="/admin/configuracion?tab=metodos-pago&toggle_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-4"><?= !empty($method['activo']) ? 'Desactivar' : 'Activar' ?></a>
                                <a href="/admin/configuracion?tab=metodos-pago&eliminar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4" onclick="return confirm('¿Eliminar este método de pago?');">Eliminar</a>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <div class="d-grid gap-3 d-md-none">
                  <?php foreach ($paymentMethods as $method): ?>
                    <div class="gallery-card-mobile">
                      <div class="d-flex justify-content-between gap-2 align-items-start">
                        <h4 class="h6 fw-bold mb-1 text-info"><?= htmlspecialchars($method['nombre'], ENT_QUOTES, 'UTF-8') ?></h4>
                        <?= !empty($method['activo']) ? '<span class="gallery-badge-neon">Activo</span>' : '<span class="text-secondary small">Inactivo</span>' ?>
                      </div>
                      <div class="small text-info-emphasis mt-2"><?= htmlspecialchars(trim((string) (($method['moneda_nombre'] ?? '') . ' ' . (!empty($method['moneda_clave']) ? '(' . $method['moneda_clave'] . ')' : ''))) ?: 'Sin moneda', ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="small text-secondary mt-1">Dígitos de referencia: <?= !empty($method['referencia_digitos']) ? (int) $method['referencia_digitos'] : 'Sin límite' ?></div>
                      <div class="small text-light mt-2" style="white-space: pre-line;"><?= htmlspecialchars($method['datos'], ENT_QUOTES, 'UTF-8') ?></div>
                      <div class="d-flex gap-2 mt-3 flex-wrap">
                        <a href="/admin/configuracion?tab=metodos-pago&editar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-info btn-sm rounded-4 flex-fill">Editar</a>
                        <a href="/admin/configuracion?tab=metodos-pago&toggle_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-secondary btn-sm rounded-4 flex-fill"><?= !empty($method['activo']) ? 'Desactivar' : 'Activar' ?></a>
                        <a href="/admin/configuracion?tab=metodos-pago&eliminar_metodo_pago=<?= (int) $method['id'] ?>" class="btn btn-outline-danger btn-sm rounded-4 flex-fill" onclick="return confirm('¿Eliminar este método de pago?');">Eliminar</a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  (() => {
    const fileInput = document.getElementById('gallery-image-input');
    const previewContainer = document.getElementById('gallery-image-preview');
    if (!fileInput || !previewContainer) {
      return;
    }

    const originalSrc = previewContainer.dataset.originalSrc || '';
    let objectUrl = null;

    const renderPreview = (src) => {
      previewContainer.innerHTML = '';
      if (!src) {
        const empty = document.createElement('span');
        empty.className = 'gallery-image-empty';
        empty.id = 'gallery-image-preview-empty';
        empty.textContent = 'Sin imagen';
        previewContainer.appendChild(empty);
        return;
      }

      const image = document.createElement('img');
      image.id = 'gallery-image-preview-img';
      image.alt = 'Vista previa de galería';
      image.src = src;
      previewContainer.appendChild(image);
    };

    const clearObjectUrl = () => {
      if (objectUrl) {
        URL.revokeObjectURL(objectUrl);
        objectUrl = null;
      }
    };

    fileInput.addEventListener('change', () => {
      const [file] = fileInput.files || [];
      clearObjectUrl();

      if (!file) {
        renderPreview(originalSrc);
        return;
      }

      if (!file.type.startsWith('image/')) {
        renderPreview(originalSrc);
        return;
      }

      objectUrl = URL.createObjectURL(file);
      renderPreview(objectUrl);
    });

    renderPreview(originalSrc);
  })();
</script>
<?php if (!defined('ADMIN_LAYOUT_EMBEDDED')) include __DIR__ . '/includes/footer.php'; ?>
