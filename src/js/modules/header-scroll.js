function headerScroll() {
  const header = document.getElementById("header");
  if (!header) return;

  let scheduled = false;

  const updateHeader = () => {
    header.classList.toggle("header--elevated", window.scrollY > 24);
    scheduled = false;
  };

  window.addEventListener(
    "scroll",
    () => {
      if (scheduled) return;
      scheduled = true;
      window.requestAnimationFrame(updateHeader);
    },
    { passive: true },
  );

  updateHeader();
}

export default headerScroll;
