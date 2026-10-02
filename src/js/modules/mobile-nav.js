function mobileNav() {
  const navBtn = document.querySelector(".mobile-nav-btn");
  const nav = document.querySelector(".mobile-nav");
  const menuIcon = document.querySelector(".nav-icon");

  if (!navBtn || !nav || !menuIcon) return;

  const navLinks = document.querySelectorAll(".mobile-nav__link");
  const subToggles = nav.querySelectorAll(".mobile-nav__toggle");

  const directSubmenu = (item) =>
    Array.from(item.children).find((child) =>
      child.classList.contains("mobile-nav__sub"),
    );

  const resizeOpenParents = (submenu, heightDifference) => {
    let parentSubmenu = submenu.parentElement.closest(".mobile-nav__sub");

    while (parentSubmenu) {
      const parentItem = parentSubmenu.parentElement;
      if (parentItem.classList.contains("mobile-nav__item--open")) {
        const currentHeight = parentSubmenu.getBoundingClientRect().height;
        parentSubmenu.style.maxHeight = `${Math.max(0, currentHeight + heightDifference)}px`;
      }
      parentSubmenu = parentItem.closest(".mobile-nav__sub");
    }
  };

  const setSubmenu = (toggle, open) => {
    const item = toggle.parentElement;
    const submenu = directSubmenu(item);
    if (!submenu) return;

    const currentHeight = submenu.getBoundingClientRect().height;
    const targetHeight = open ? submenu.scrollHeight : 0;

    item.classList.toggle("mobile-nav__item--open", open);
    toggle.setAttribute("aria-expanded", String(open));
    submenu.setAttribute("aria-hidden", String(!open));
    submenu.inert = !open;
    submenu.style.maxHeight = `${targetHeight}px`;
    resizeOpenParents(submenu, targetHeight - currentHeight);
  };

  // a closed menu stays out of the keyboard and screen reader order
  const setOpen = (open) => {
    nav.classList.toggle("mobile-nav--open", open);
    menuIcon.classList.toggle("nav-icon--active", open);
    document.body.classList.toggle("no-scroll", open);
    nav.inert = !open;
    navBtn.setAttribute("aria-expanded", String(open));
    navBtn.setAttribute("aria-label", open ? "Aizvērt izvēlni" : "Atvērt izvēlni");
  };

  const closeMenu = () => {
    setOpen(false);
    Array.from(subToggles).reverse().forEach((toggle) => setSubmenu(toggle, false));
  };

  subToggles.forEach((toggle) => setSubmenu(toggle, false));
  setOpen(false);

  navBtn.onclick = function () {
    if (nav.classList.contains("mobile-nav--open")) {
      closeMenu();
    } else {
      setOpen(true);
    }
  };

  subToggles.forEach((toggle) => {
    toggle.addEventListener("click", () => {
      const open = !toggle.parentElement.classList.contains("mobile-nav__item--open");
      setSubmenu(toggle, open);
    });
  });

  let resizeFrame;
  window.addEventListener("resize", () => {
    window.cancelAnimationFrame(resizeFrame);
    resizeFrame = window.requestAnimationFrame(() => {
      Array.from(subToggles)
        .reverse()
        .forEach((toggle) => {
          const item = toggle.parentElement;
          if (!item.classList.contains("mobile-nav__item--open")) return;
          const submenu = directSubmenu(item);
          submenu.style.maxHeight = `${submenu.scrollHeight}px`;
        });
    });
  });

  navLinks.forEach((link) => {
    link.addEventListener("click", closeMenu);
  });

  document.addEventListener("click", (e) => {
    const clickedInsideNav = nav.contains(e.target);
    const clickedOnBtn = navBtn.contains(e.target);

    if (!clickedInsideNav && !clickedOnBtn) {
      closeMenu();
    }
  });

  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && nav.classList.contains("mobile-nav--open")) {
      closeMenu();
      navBtn.focus();
    }
  });
}

export default mobileNav;
