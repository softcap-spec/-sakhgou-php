// сахгоу.рф — client scripts
document.addEventListener('DOMContentLoaded', () => {
  // Mobile menu
  const btn = document.querySelector('.mobile-menu-btn');
  const nav = document.querySelector('.nav');
  if (btn && nav) {
    btn.addEventListener('click', (e) => { e.stopPropagation(); nav.classList.toggle('open'); });
    document.addEventListener('click', (e) => { if (!nav.contains(e.target) && !btn.contains(e.target)) nav.classList.remove('open'); });
  }
});
