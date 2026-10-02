import { createNewsSwiper } from "./swipers.js";

export default function newsTabs() {
  const tablist = document.querySelector(".news__tabs");
  if (!tablist) return;

  const tabs = Array.from(tablist.querySelectorAll(".news__tab"));
  const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

  const switchTo = (selectedTab) => {
    tabs.forEach((current) => {
      const isActive = current === selectedTab;
      current.classList.toggle("news__tab--active", isActive);
      current.setAttribute("aria-selected", String(isActive));
      current.tabIndex = isActive ? 0 : -1;

      const panel = document.getElementById(current.getAttribute("aria-controls"));
      if (!panel) return;

      panel.hidden = !isActive;
      panel.setAttribute("aria-hidden", String(!isActive));
      panel.inert = !isActive;

      const swiper = createNewsSwiper(`#${panel.id}`);
      if (!swiper) return;

      if (isActive) {
        swiper.update();
      }

      if (!swiper.params.autoplay.enabled) return;
      if (isActive) swiper.autoplay.start();
      else swiper.autoplay.stop();
    });
  };

  // only the selected tab is in the Tab order; arrows move between tabs (WAI-ARIA tabs pattern)
  tabs.forEach((tab) => {
    const isActive = tab.classList.contains("news__tab--active");
    tab.tabIndex = isActive ? 0 : -1;

    const panel = document.getElementById(tab.getAttribute("aria-controls"));
    if (!panel) return;
    panel.setAttribute("aria-hidden", String(!isActive));
    panel.inert = !isActive;
  });
  tablist.addEventListener("keydown", (e) => {
    const index = tabs.indexOf(document.activeElement);
    if (index === -1) return;
    const next = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 }[e.key];
    if (next === undefined) return;
    e.preventDefault();
    const tab = tabs[(next + tabs.length) % tabs.length];
    tab.focus();
    tab.click();
  });

  tabs.forEach((tab) => {
    tab.addEventListener("click", () => {
      if (tab.classList.contains("news__tab--active")) return;

      if (!reducedMotion.matches && document.startViewTransition) {
        document.startViewTransition(() => switchTo(tab));
        return;
      }

      switchTo(tab);

      if (reducedMotion.matches) return;
      const panel = document.getElementById(tab.getAttribute("aria-controls"));
      panel?.animate(
        [
          { opacity: 0, transform: "translateY(10px)" },
          { opacity: 1, transform: "translateY(0)" },
        ],
        { duration: 300, easing: "cubic-bezier(0.22, 1, 0.36, 1)" },
      );
    });
  });
}
