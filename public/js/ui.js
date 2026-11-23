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
  document.querySelectorAll('[data-pass-rules]').forEach(list => {
    const input = document.getElementById(list.dataset.passRules);
    const confirmation = input.form.elements.namedItem('password_confirmation');
    const minimum = Number(list.dataset.minLength);
    const rules = {
      len: v => [...v].length >= minimum,
      up: v => /\p{Lu}/u.test(v),
      low: v => /\p{Ll}/u.test(v),
      num: v => /\p{N}/u.test(v),
      sym: v => /[\p{Z}\p{S}\p{P}]/u.test(v),
      match: v => v !== '' && v === confirmation.value,
    };
    const update = () => {
      list.querySelectorAll('[data-rule]').forEach(item => {
        item.classList.toggle('ok', rules[item.dataset.rule](input.value));
      });
    };
    input.addEventListener('input', update);
    confirmation.addEventListener('input', update);
    update();
  });

  // Si el login se abre dentro del iframe del portal (sesión caducada), sale del iframe
  if (document.body.classList.contains('guest') && window.self !== window.top) {
    window.top.location.href = window.location.href;
  }

  // Modales (con datos opcionales para precargar el formulario de edición)
  document.querySelectorAll('[data-open]').forEach(b =>
    b.addEventListener('click', () => {
      const modal = document.getElementById(b.dataset.open);
      modal.classList.add('open');
      if (b.dataset.action) {
        const form = modal.querySelector('form');
        form.action = b.dataset.action;
        form.querySelector('[name=name]').value  = b.dataset.name  || '';
        form.querySelector('[name=email]').value = b.dataset.email || '';
      }
    }));
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

// Cuenta regresiva: data-countdown = segundos restantes
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-countdown]').forEach(box => {
    const total = parseInt(box.dataset.total || box.dataset.countdown, 10) || 300;
    const end   = Date.now() + parseInt(box.dataset.countdown, 10) * 1000;
    const text  = box.querySelector('[data-countdown-text]');
    const label = box.querySelector('[data-countdown-label]');
    const bar   = box.querySelector('.timer-bar i');

    const tick = () => {
      const left = Math.max(0, Math.ceil((end - Date.now()) / 1000));
      text.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
      if (bar) bar.style.width = (left / total * 100) + '%';
      box.classList.toggle('warn', left <= 60 && left > 0);

      if (left === 0) {
        clearInterval(id);
        if (box.dataset.onEnd === 'reload') {
          location.reload();   // el servidor cierra la sesión y aplica el bloqueo
        } else {
          // El bloqueo terminó: el contador y su aviso se desvanecen y desaparecen
          box.classList.add('hide');
          setTimeout(() => {
            let prev = box.previousElementSibling;
            while (prev && prev.classList.contains('alert')) {
              const del = prev; prev = prev.previousElementSibling; del.remove();
            }
            box.remove();
          }, 700);
        }
      }
    };
    const id = setInterval(tick, 250);
    tick();
  });
});
