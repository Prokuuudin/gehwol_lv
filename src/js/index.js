import loadDynamicContent from "./modules/dynamicContent.js";
import initAllSwipers from "./modules/swipers.js";

import headerScroll from "./modules/header-scroll.js";
headerScroll();

import scrollReveal from "./modules/scrollReveal.js";
scrollReveal();

import mobileNav from "./modules/mobile-nav.js";
mobileNav();

import placeholderLinks from "./modules/placeholder-links.js";
placeholderLinks();

import newsTabs from "./modules/newsTabs.js";
newsTabs();

loadDynamicContent().finally(() => {
  initAllSwipers();
});
