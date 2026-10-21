document.addEventListener('DOMContentLoaded', () => {
  // Mostrar / ocultar contraseña
  document.querySelectorAll('[data-toggle-pass]').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = document.getElementById(btn.dataset.togglePass);
      input.type = input.type === 'password' ? 'text' : 'password';
      btn.textContent = input.type === 'password' ? 'Mostrar' : 'Ocultar';
    });
  });

  // Checklist en vivo de la política de contraseña
  const rules = {
    len: v => v.length >= 9,
    up:  v => /[A-Z]/.test(v),
    low: v => /[a-z]/.test(v),
    num: v => /\d/.test(v),
    sym: v => /[^A-Za-z0-9]/.test(v),
  };
  document.querySelectorAll('[data-pass-rules]').forEach(list => {
    const input = document.getElementById(list.dataset.passRules);
    const update = () => Object.entries(rules).forEach(([k, fn]) =>
      list.querySelector(`[data-rule="${k}"]`).classList.toggle('ok', fn(input.value)));
    input.addEventListener('input', update);
    update();
  });

  // Modales
  document.querySelectorAll('[data-open]').forEach(b =>
    b.addEventListener('click', () => document.getElementById(b.dataset.open).classList.add('open')));
  document.querySelectorAll('[data-close]').forEach(b =>
    b.addEventListener('click', () => b.closest('.modal').classList.remove('open')));
  document.querySelectorAll('.modal').forEach(m =>
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); }));

  // Confirmaciones
  document.querySelectorAll('form[data-confirm]').forEach(f =>
    f.addEventListener('submit', e => { if (!confirm(f.dataset.confirm)) e.preventDefault(); }));

  // Menú móvil
  const menu = document.querySelector('[data-menu]');
  if (menu) menu.addEventListener('click', () => document.body.classList.toggle('menu-open'));
});
