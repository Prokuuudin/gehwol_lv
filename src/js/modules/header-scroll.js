function headerScroll() {
  const header = document.getElementById("header");
  if (!header) return;

  window.addEventListener(
    "scroll",
    function () {
      header.classList.toggle("header--elevated", window.scrollY > 24);
    },
    { passive: true },
  );
}

export default headerScroll;
