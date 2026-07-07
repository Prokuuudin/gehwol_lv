import Swiper from "swiper";
import { Autoplay } from "swiper/modules";
import "swiper/css";

function initNewsSwiper() {
  const container = document.querySelector(".swiper-news");
  if (!container) return;

  return new Swiper(container, {
    modules: [Autoplay],
    loop: false,
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
  });
}

export default function initAllSwipers() {
  initNewsSwiper();
}
