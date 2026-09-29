import { createNewsSwiper } from "./swipers.js";

export default function newsTabs() {
  const tablist = document.querySelector(".news__tabs");
  if (!tablist) return;

  const tabs = Array.from(tablist.querySelectorAll(".news__tab"));

  // only the selected tab is in the Tab order; arrows move between tabs (WAI-ARIA tabs pattern)
  tabs.forEach((tab) => {
    tab.tabIndex = tab.classList.contains("news__tab--active") ? 0 : -1;
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

      tabs.forEach((current) => {
        const isActive = current === tab;
        current.classList.toggle("news__tab--active", isActive);
        current.setAttribute("aria-selected", String(isActive));
        current.tabIndex = isActive ? 0 : -1;

        const panel = document.getElementById(
          current.getAttribute("aria-controls"),
        );
        if (!panel) return;

        // hidden снимается до создания Swiper — иначе ширина слайдов = 0
        panel.hidden = !isActive;

        const swiper = createNewsSwiper(`#${panel.id}`);
        if (!swiper || !swiper.params.autoplay.enabled) return;

        if (isActive) {
          swiper.autoplay.start();
        } else {
          swiper.autoplay.stop();
        }
      });
    });
  });
}
