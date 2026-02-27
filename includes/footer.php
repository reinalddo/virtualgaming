<?php
$menuScript = <<<"SCRIPT"
<script>
  const menuToggle = document.getElementById("menu-toggle");
  const menuOverlay = document.getElementById("menu-overlay");
  const menuPanel = document.getElementById("menu-panel");
  const menuClose = document.getElementById("menu-close");

  const openMenu = () => {
    menuOverlay.classList.remove("hidden");
    menuPanel.classList.remove("hidden");
  };

  const closeMenu = () => {
    menuOverlay.classList.add("hidden");
    menuPanel.classList.add("hidden");
  };

  menuToggle.addEventListener("click", openMenu);
  menuClose.addEventListener("click", closeMenu);
  menuOverlay.addEventListener("click", closeMenu);
</script>
SCRIPT;
?>
    </div>
  </div>
  <?php echo $menuScript; ?>
  <?php
  if (!empty($pageScripts) && is_array($pageScripts)) {
    foreach ($pageScripts as $script) {
      echo $script;
    }
  }
  ?>
</body>
</html>
