document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-copy], [data-copy-all]');
  if (!button) return;
  let text = '';
  if (button.hasAttribute('data-copy-all')) {
    text = [...document.querySelectorAll('.post textarea')].map((t) => t.value).join('\n\n---\n\n');
  } else {
    text = button.closest('.post')?.querySelector('textarea')?.value || '';
  }
  if (!text) return;
  await navigator.clipboard.writeText(text);
  const old = button.textContent;
  button.textContent = 'コピーしました';
  setTimeout(() => { button.textContent = old; }, 1400);
});
