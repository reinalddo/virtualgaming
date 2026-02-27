<?php
$menuScript = <<<"SCRIPT"
<script>
  const menuToggle = document.getElementById("menu-toggle");
  const menuOverlay = document.getElementById("menu-overlay");
  const menuPanel = document.getElementById("menu-panel");
  const menuClose = document.getElementById("menu-close");
  const authContainer = document.getElementById("auth-container");
  const authTrigger = document.getElementById("auth-trigger");
  const authMenu = document.getElementById("auth-menu");
  const authModal = document.getElementById("auth-modal");
  const authLogin = document.getElementById("auth-login");
  const authRegister = document.getElementById("auth-register");

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

  const showAuthMenu = () => {
    if (authMenu) {
      authMenu.classList.remove("hidden");
    }
  };

  const hideAuthMenu = () => {
    if (authMenu) {
      authMenu.classList.add("hidden");
    }
  };

  const openAuthModal = (mode) => {
    if (!authModal || !authLogin || !authRegister) return;
    authModal.classList.remove("hidden");
    authModal.classList.add("flex");
    if (mode === "register") {
      authLogin.classList.add("hidden");
      authRegister.classList.remove("hidden");
    } else {
      authRegister.classList.add("hidden");
      authLogin.classList.remove("hidden");
    }
  };

  const closeAuthModal = () => {
    if (!authModal) return;
    authModal.classList.add("hidden");
    authModal.classList.remove("flex");
  };

  if (authTrigger && authMenu && authContainer) {
    authTrigger.addEventListener("click", (event) => {
      event.stopPropagation();
      if (authMenu.classList.contains("hidden")) {
        showAuthMenu();
      } else {
        hideAuthMenu();
      }
    });

    document.addEventListener("click", (event) => {
      if (!authContainer.contains(event.target)) {
        hideAuthMenu();
      }
    });
  }

  document.querySelectorAll("[data-auth-open]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      const mode = button.dataset.authOpen;
      hideAuthMenu();
      openAuthModal(mode);
    });
  });

  document.querySelectorAll("[data-auth-close]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      closeAuthModal();
    });
  });

  document.querySelectorAll("[data-auth-switch]").forEach((button) => {
    button.addEventListener("click", (event) => {
      event.preventDefault();
      openAuthModal(button.dataset.authSwitch);
    });
  });
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
