export default function aboutToggle() {
  const button = document.getElementById("about-toggle");
  if (!button) return;

  const panel = document.getElementById(button.getAttribute("aria-controls"));
  if (!panel) return;

  const textMore = button.dataset.textMore || button.textContent;
  const textLess = button.dataset.textLess || button.textContent;

  let isExpanded = false;

  panel.addEventListener("transitionend", (event) => {
    if (event.propertyName === "max-height" && isExpanded) {
      // снимаем фиксированную высоту, чтобы контент мог свободно
      // реагировать на ресайз/смену брейкпоинта без обрезки
      panel.style.maxHeight = "none";
    }
  });

  button.addEventListener("click", () => {
    isExpanded = !isExpanded;

    if (isExpanded) {
      panel.style.maxHeight = `${panel.scrollHeight}px`;
    } else {
      panel.style.maxHeight = `${panel.scrollHeight}px`;
      panel.offsetHeight; // форсируем reflow, чтобы transition стартовал не от "none"
      panel.style.maxHeight = "0px";
    }

    panel.classList.toggle("is-expanded", isExpanded);
    panel.setAttribute("aria-hidden", String(!isExpanded));
    button.setAttribute("aria-expanded", String(isExpanded));
    button.textContent = isExpanded ? textLess : textMore;
  });
}
