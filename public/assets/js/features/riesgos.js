(() => {
  const root = document.querySelector('[data-riesgos]');
  if (!root) return;

  const status = root.querySelector('[data-risk-status]');
  const showStatus = (message, type = 'ok') => {
    if (!status) return;
    status.hidden = false;
    status.textContent = message;
    status.dataset.state = type;
  };

  const activatePanel = (panelName) => {
    root.querySelectorAll('[data-risk-tab]').forEach((item) => item.classList.toggle('active', item.dataset.riskTab === panelName));
    root.querySelectorAll('[data-risk-panel]').forEach((panel) => panel.classList.toggle('active', panel.dataset.riskPanel === panelName));
  };

  root.querySelectorAll('[data-risk-tab]').forEach((button) => {
    button.addEventListener('click', () => {
      activatePanel(button.dataset.riskTab || 'candidatos');
    });
  });

  activatePanel('candidatos');

  root.querySelectorAll('[data-risk-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const targetPanel = button.dataset.riskOpen === 'new-risk' ? 'registro' : 'candidatos';
      root.querySelector(`[data-risk-tab="${targetPanel}"]`)?.click();
      const block = root.querySelector(`[data-risk-form-block="${button.dataset.riskOpen}"]`);
      if (!block) return;
      block.classList.toggle('collapsed');
      block.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
  });

  root.querySelectorAll('[data-risk-needs-risk]').forEach((form) => {
    const select = form.querySelector('select[name="riesgo_id"]');
    const setAction = () => {
      const id = select?.value || '';
      const template = form.dataset.riskActionTemplate || '';
      form.action = id ? template.replace('{id}', id) : '';
    };
    select?.addEventListener('change', setAction);
    setAction();
  });

  const validate = (form) => {
    const candidateState = form.querySelector('[name="estado"]')?.value || '';
    if (candidateState === 'Descartado' && !form.querySelector('[name="justificacion"]')?.value.trim()) {
      return 'Para descartar un candidato debes escribir la justificacion.';
    }
    if (candidateState === 'Requiere informacion' || candidateState === 'Requiere información') {
      const info = form.querySelector('[name="informacion_solicitada"]')?.value.trim();
      const owner = form.querySelector('[name="responsable_revision"]')?.value.trim();
      const due = form.querySelector('[name="fecha_limite_respuesta"]')?.value.trim();
      if (!info || !owner || !due) return 'Si requiere informacion, diligencia solicitud, responsable y fecha limite.';
    }
    if (candidateState === 'Relacionado con riesgo existente' && !form.querySelector('[name="riesgo_relacionado_id"]')?.value) {
      return 'Selecciona el riesgo relacionado.';
    }
    const riskId = form.querySelector('[name="riesgo_id"]')?.value;
    if (form.hasAttribute('data-risk-needs-risk') && !riskId) {
      return 'Selecciona primero un riesgo.';
    }
    const probability = form.querySelector('[name="probabilidad_inherente"]')?.value;
    const impact = form.querySelector('[name="impacto_inherente"]')?.value;
    if ((probability || impact) && (!probability || !impact)) {
      return 'Para evaluar un riesgo debes indicar probabilidad e impacto.';
    }
    return null;
  };

  root.querySelectorAll('[data-risk-form]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      const problem = validate(form);
      if (problem) {
        showStatus(problem, 'error');
        return;
      }
      if (!form.action) {
        showStatus('No hay ruta disponible para guardar. Revisa que hayas seleccionado un riesgo.', 'error');
        return;
      }
      const submit = form.querySelector('button[type="submit"], button:not([type])');
      submit?.setAttribute('disabled', 'disabled');
      showStatus('Guardando cambios...', 'loading');
      try {
        const response = await fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) throw new Error(payload.error || 'No fue posible guardar.');
        showStatus('Guardado correctamente. Actualiza la vista para recalcular todos los agrupadores.', 'ok');
        form.classList.add('saved');
      } catch (error) {
        showStatus(error.message || 'No fue posible guardar.', 'error');
      } finally {
        submit?.removeAttribute('disabled');
      }
    });
  });
})();
