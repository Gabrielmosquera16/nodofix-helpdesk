/* NodoFix HelpDesk - comportamiento de la interfaz.
   Archivo externo (sin JavaScript en línea) para cumplir la Content-Security-Policy.
   La validación aquí es solo de experiencia de usuario; el servidor vuelve a validar todo. */
document.addEventListener('DOMContentLoaded', () => {
    // Menús desplegables
    document.querySelectorAll('[data-toggle]').forEach(btn => {
        btn.addEventListener('click', ev => {
            ev.stopPropagation();
            document.getElementById(btn.dataset.toggle)?.classList.toggle('abierto');
        });
    });
    document.addEventListener('click', () => document.getElementById('usuario-drop')?.classList.remove('abierto'));

    // Cerrar alertas
    document.querySelectorAll('.alerta .cerrar').forEach(b => b.addEventListener('click', () => b.parentElement.remove()));

    // Confirmación antes de acciones destructivas
    document.querySelectorAll('form[data-confirmar]').forEach(f => {
        f.addEventListener('submit', ev => { if (!confirm(f.dataset.confirmar)) ev.preventDefault(); });
    });

    // Enviar automáticamente selects marcados
    document.querySelectorAll('[data-autoenviar]').forEach(s => s.addEventListener('change', () => s.form.submit()));

    // Contador de caracteres en textareas
    document.querySelectorAll('textarea[data-contador]').forEach(t => {
        const c = document.createElement('span');
        c.className = 'contador-car';
        const upd = () => { c.textContent = `${t.value.length} / ${t.maxLength}`; };
        t.after(c); t.addEventListener('input', upd); upd();
    });

    // Indicador de reglas de contraseña
    const reglas = {
        len: v => v.length >= 8, may: v => /[A-Z]/.test(v), min: v => /[a-z]/.test(v),
        num: v => /\d/.test(v), esp: v => /[^A-Za-z0-9]/.test(v)
    };
    document.querySelectorAll('input[data-fuerza]').forEach(inp => {
        const lista = inp.form.querySelector('[data-reglas]');
        if (!lista) return;
        inp.addEventListener('input', () => {
            lista.querySelectorAll('li').forEach(li => li.classList.toggle('cumple', reglas[li.dataset.r](inp.value)));
        });
    });

    // Validación de formularios con mensajes visibles
    const mensaje = el => {
        if (el.validity.valueMissing) return 'Este campo es obligatorio.';
        if (el.validity.typeMismatch && el.type === 'email') return 'Escribe un correo válido.';
        if (el.validity.tooShort) return `Mínimo ${el.minLength} caracteres.`;
        if (el.validity.patternMismatch) return 'El formato no es válido.';
        if (el.dataset.igual && el.value !== el.form.elements[el.dataset.igual].value) return 'Las contraseñas no coinciden.';
        if (el.dataset.fuerza && el.value && !Object.values(reglas).every(r => r(el.value))) return 'La contraseña no cumple todas las reglas.';
        return '';
    };
    const marcar = el => {
        const m = mensaje(el);
        let span = el.parentElement.querySelector('.msg-campo');
        el.classList.toggle('invalido', !!m);
        if (m && !span) { span = document.createElement('span'); span.className = 'msg-campo'; el.after(span); }
        if (span) span.textContent = m;
        if (!m && span) span.remove();
        return !m;
    };
    document.querySelectorAll('form[data-validar]').forEach(f => {
        f.addEventListener('submit', ev => {
            let ok = true;
            f.querySelectorAll('input:not([type=hidden]):not([type=radio]), select, textarea').forEach(el => {
                if (el.disabled || el.closest('details:not([open])')) return;
                if (!marcar(el)) ok = false;
            });
            f.querySelectorAll('input[type=radio][required]').forEach(r => {
                if (!f.querySelector(`input[name="${r.name}"]:checked`)) ok = false;
            });
            if (!ok) { ev.preventDefault(); f.querySelector('.invalido')?.focus(); }
        });
        // Tras el primer intento, cada campo con error se re-evalúa mientras el usuario lo corrige
        f.querySelectorAll('input, select, textarea').forEach(el => el.addEventListener('input', () => { if (el.classList.contains('invalido')) marcar(el); }));
    });
});
