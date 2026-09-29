function mobileNav() {
  const navBtn = document.querySelector(".mobile-nav-btn");
  const nav = document.querySelector(".mobile-nav");
  const menuIcon = document.querySelector(".nav-icon");
  const navLinks = document.querySelectorAll(".mobile-nav__link");
  const subToggles = nav.querySelectorAll(".mobile-nav__toggle");

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
    nav.querySelectorAll(".mobile-nav__item--open").forEach((item) => {
      item.classList.remove("mobile-nav__item--open");
    });
    subToggles.forEach((toggle) => toggle.setAttribute("aria-expanded", "false"));
  };

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
      const open = toggle.parentElement.classList.toggle("mobile-nav__item--open");
      toggle.setAttribute("aria-expanded", String(open));
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
