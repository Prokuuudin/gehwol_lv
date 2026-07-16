import ScrollReveal from "scrollreveal";

ScrollReveal({
  distance: "24px",
  duration: 700,
  easing: "cubic-bezier(0.22, 1, 0.36, 1)",
  viewFactor: 0.15,
});

function scrollRevealFunc() {
  if (window.matchMedia("(prefers-reduced-motion: reduce)").matches) {
    return;
  }

  const headerAnimatedElements = Array.from(
    document.querySelectorAll(".header .logo, .header__nav, .mobile-nav-btn"),
  );

  headerAnimatedElements.forEach((element, index) => {
    element.animate(
      [
        { opacity: 0, transform: "translateY(-14px)" },
        { opacity: 1, transform: "translateY(0)" },
      ],
      {
        delay: 80 + index * 70,
        duration: 600,
        easing: "cubic-bezier(0.22, 1, 0.36, 1)",
        fill: "both",
      },
    );
  });

  ScrollReveal().reveal(`.hero__title, .hero__slogan`, {
    delay: 220,
    distance: "0px",
    duration: 650,
    scale: 0.98,
    opacity: 0,
  });

  ScrollReveal().reveal(`.title-2`, {
    delay: 100,
    origin: "top",
    distance: "18px",
  });

  // .about__row--reverse исключён — до клика "Lasīt tālāk" у него max-height:0,
  // а нулевая по высоте область никогда не пересечёт viewFactor, из-за чего
  // элементы остались бы с opacity:0 навсегда.
  ScrollReveal().reveal(`.about__row:not(.about__row--reverse) .about__image`, {
    delay: 120,
    origin: "left",
  });

  ScrollReveal().reveal(
    `.about__row:not(.about__row--reverse) .about__content`,
    {
      delay: 150,
      origin: "bottom",
      distance: "18px",
    },
  );

  // Внутренние элементы свайпера (.swiper-slide и т.п.) сюда не включать —
  // IntersectionObserver не учитывает clip карусели, слайды на краю
  // никогда не пересекают viewFactor и остаются с opacity:0 навсегда.
  ScrollReveal().reveal(`.products__card, .btn-link`, {
    delay: 120,
    interval: 80,
    origin: "bottom",
    distance: "20px",
  });

  ScrollReveal().reveal(`.about__how-step`, {
    delay: 130,
    interval: 80,
    origin: "bottom",
    distance: "16px",
  });

  ScrollReveal().reveal(`.footer__info, .footer__contacts, .footer__meta`, {
    delay: 140,
    interval: 100,
    origin: "bottom",
    distance: "18px",
  });
}

export default scrollRevealFunc;
