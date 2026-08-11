document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy-text]');
  if (!button) return;
  event.preventDefault();
  const form = button.closest('form');
  try {
    await navigator.clipboard.writeText(button.dataset.copyText || '');
    form.submit();
  } catch {
    alert('コピーできませんでした。HTTPSで開いているか確認してください。');
  }
});

document.querySelectorAll('[data-select-all]').forEach((button) => {
  button.addEventListener('click', () => {
    const inputs = [...document.querySelectorAll(button.dataset.selectAll)];
    const shouldCheck = inputs.some((input) => !input.checked);
    inputs.forEach((input) => {
      input.checked = shouldCheck;
    });
    button.textContent = shouldCheck ? '全選択を解除' : (button.dataset.selectLabel || '全選択');
  });
});

const openMenu = () => {
  document.body.classList.add('menu-open');
  document.querySelector('[data-menu-open]')?.setAttribute('aria-expanded', 'true');
};
const closeMenu = () => {
  document.body.classList.remove('menu-open');
  document.querySelector('[data-menu-open]')?.setAttribute('aria-expanded', 'false');
};
document.querySelector('[data-menu-open]')?.addEventListener('click', openMenu);
document.querySelectorAll('[data-menu-close]').forEach((button) => button.addEventListener('click', closeMenu));
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') closeMenu();
});
