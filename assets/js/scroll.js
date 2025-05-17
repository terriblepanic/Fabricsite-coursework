document.addEventListener('DOMContentLoaded', () => {
  const header = document.querySelector('header');
  const scrollThreshold = 50; // порог прокрутки в px

  function onScroll() {
    if (window.scrollY > scrollThreshold) {
      header.classList.add('scrolled');
    } else {
      header.classList.remove('scrolled');
    }
  }

  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
});
