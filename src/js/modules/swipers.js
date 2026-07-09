import Swiper from "swiper";
import { Autoplay } from "swiper/modules";
import "swiper/css";

const newsSwiperConfig = {
  modules: [Autoplay],
  rewind: true,
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

export default function initAllSwipers() {
  createNewsSwiper(".swiper-news");
}
