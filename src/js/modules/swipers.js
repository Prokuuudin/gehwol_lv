import Swiper from "swiper";
import { Autoplay, Navigation, Pagination } from "swiper/modules";
import "swiper/css";
import "swiper/css/navigation";
import "swiper/css/pagination";

const newsSwiperConfig = {
  modules: [Autoplay],
  loop: true,
  slidesPerView: 3,
  spaceBetween: 20,

  breakpoints: {
    0: { slidesPerView: 1 },
    760: { slidesPerView: 2 },
    1024: { slidesPerView: 3 },
  },

  autoplay: {
    delay: 2500,
    disableOnInteraction: false,
    reverseDirection: false,
    pauseOnMouseEnter: true,
  },
  speed: 700,
};

const instances = new WeakMap();

export function createNewsSwiper(selector) {
  const container = document.querySelector(selector);
  if (!container) return null;
  if (instances.has(container)) return instances.get(container);

  const swiper = new Swiper(container, newsSwiperConfig);
  instances.set(container, swiper);
  return swiper;
}

export function createProductSwipers() {
  document.querySelectorAll(".product-detail__swiper").forEach((container) => {
    if (instances.has(container)) return;
    const swiper = new Swiper(container, {
      modules: [Navigation, Pagination],
      loop: true,
      slidesPerView: 1,
      spaceBetween: 12,
      navigation: {
        prevEl: container.querySelector(".product-detail__swiper-button--prev"),
        nextEl: container.querySelector(".product-detail__swiper-button--next"),
      },
      pagination: {
        el: container.querySelector(".product-detail__swiper-pagination"),
        clickable: true,
      },
    });
    instances.set(container, swiper);
  });
}

export default function initAllSwipers() {
  createNewsSwiper(".swiper-news");
  createProductSwipers();
}
