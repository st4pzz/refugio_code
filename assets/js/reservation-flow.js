(() => {
  const form = document.querySelector('[data-reservation-flow]');
  if (!form) return;
  const dates = form.querySelector('[data-reservation-dates]');
  const details = form.querySelector('[data-reservation-details]');
  const feedback = form.querySelector('[data-availability-feedback]');
  const preview = form.querySelector('[data-stay-preview]');
  const alternatives = form.querySelector('[data-reservation-alternatives]');
  const alternativePeriods = form.querySelector('[data-alternative-periods]');
  const check = form.querySelector('[data-check-availability]');
  const pay = form.querySelector('[data-proceed-payment]');
  let checked = null;
  let request = null;
  let sequence = 0;
  let busy = false;
  const track = event => window.dataLayer?.push({event, form_name: 'reservation_request'});
  const fingerprint = () => [form.elements.checkin.value, form.elements.checkout.value, form.elements.hospedes.value, form.elements.pets.value].join('|');
  const step = showDetails => {
    dates.hidden = showDetails; // Keep enabled so dates are included in the final POST.
    details.hidden = !showDetails;
    details.disabled = !showDetails;
    preview.hidden = !showDetails;
    document.querySelector('[data-reservation-title]').textContent = showDetails ? 'Sua estadia está a um passo' : 'Quando você quer vir?';
    document.querySelector('[data-reservation-intro]').textContent = showDetails ? 'Complete seus dados e continue para o pagamento Pix.' : 'Consulte as datas e o valor da sua estadia antes de preencher seus dados.';
    document.querySelectorAll('[data-step-label]').forEach(item => {
      if (item.dataset.stepLabel === (showDetails ? 'details' : 'dates')) item.setAttribute('aria-current', 'step');
      else item.removeAttribute('aria-current');
    });
  };
  const setBusy = value => {
    busy = value;
    form.setAttribute('aria-busy', String(value));
    check.disabled = value;
    pay.disabled = value || checked !== fingerprint();
    check.textContent = value ? 'Consultando calendário…' : 'Consultar disponibilidade';
    feedback.classList.toggle('is-checking', value);
  };
  const invalidate = () => {
    sequence++;
    request?.abort();
    checked = null;
    alternatives.hidden = true;
    alternativePeriods.replaceChildren();
    setBusy(false);
  };
  const displayDate = value => value.split('-').reverse().join('/');
  const validateStay = () => {
    for (const name of ['checkin', 'checkout', 'hospedes']) {
      const input = form.elements[name];
      input.setCustomValidity('');
      if (!input.reportValidity()) return false;
    }
    if (form.elements.checkout.value <= form.elements.checkin.value) {
      form.elements.checkout.setCustomValidity('A saída deve ser posterior à entrada.');
      form.elements.checkout.reportValidity();
      return false;
    }
    const pets = Number(form.elements.pets.value);
    if (!Number.isInteger(pets) || pets < 0 || pets > 20) {
      feedback.hidden = false;
      feedback.textContent = 'Informe uma quantidade válida de pets.';
      return false;
    }
    return true;
  };
  const showAlternatives = payload => {
    const options = (Array.isArray(payload.alternatives) ? payload.alternatives : []).slice(0, 3).filter(option =>
      /^\d{4}-\d{2}-\d{2}$/.test(option.checkin) && /^\d{4}-\d{2}-\d{2}$/.test(option.checkout)
      && Number.isFinite(Number(option.total)) && Number(option.total) > 0);
    step(false);
    feedback.textContent = payload.message || 'Estas datas estão ocupadas. Escolha outro período.';
    alternatives.hidden = false;
    form.querySelector('[data-alternatives-intro]').textContent = options.length
      ? 'Encontramos estas opções próximas das datas que você escolheu:'
      : 'Não encontramos opções válidas nos 30 dias antes ou depois da data escolhida. Experimente outro mês ou fale conosco.';
    alternativePeriods.replaceChildren();
    for (const option of options) {
      const card = document.createElement('article');
      card.className = 'alternative-period';
      const heading = document.createElement('h3');
      heading.textContent = `${displayDate(option.checkin)} → ${displayDate(option.checkout)}`;
      const description = document.createElement('p');
      description.textContent = `${option.nights} noite(s) · ${option.guests} hóspede(s)`;
      const price = document.createElement('strong');
      price.textContent = `Total: ${new Intl.NumberFormat('pt-BR', {style: 'currency', currency: option.currency}).format(Number(option.total))}`;
      const choose = document.createElement('button');
      choose.type = 'button'; choose.className = 'secondary-button';
      choose.textContent = 'Escolher estas datas';
      choose.setAttribute('aria-label', `Escolher de ${displayDate(option.checkin)} a ${displayDate(option.checkout)}`);
      choose.addEventListener('click', () => {
        form.elements.checkin.value = option.checkin;
        form.elements.checkout.value = option.checkout;
        const minimum = new Date(option.checkin + 'T12:00:00Z');
        minimum.setUTCDate(minimum.getUTCDate() + 1);
        form.elements.checkout.min = minimum.toISOString().slice(0, 10);
        form.elements.checkout.setCustomValidity('');
        track('reservation_alternative_selected');
        lookup(); // A suggestion is not a hold: always check again before continuing.
        feedback.focus({preventScroll: true});
      });
      card.append(heading, description, price, choose);
      alternativePeriods.append(card);
    }
    track('reservation_availability_unavailable');
    feedback.focus({preventScroll: true});
    feedback.scrollIntoView({behavior: 'smooth', block: 'center'});
  };
  const lookup = async () => {
    invalidate();
    if (!validateStay()) return;
    const wasDetails = !details.hidden;
    const current = sequence;
    const queried = fingerprint();
    request = new AbortController();
    const controller = request;
    const timeout = setTimeout(() => controller.abort(), 20000);
    feedback.hidden = false;
    feedback.textContent = 'Consultando disponibilidade e valor para sua estadia…';
    setBusy(true);
    track('reservation_availability_check');
    const body = new FormData();
    body.set('_csrf', form.elements._csrf.value);
    body.set('checkin', form.elements.checkin.value);
    body.set('checkout', form.elements.checkout.value);
    body.set('guests', form.elements.hospedes.value);
    body.set('pets', form.elements.pets.value);
    try {
      const response = await fetch(form.dataset.quoteEndpoint, {method: 'POST', body, credentials: 'same-origin', headers: {Accept: 'application/json'}, signal: controller.signal});
      const payload = await response.json();
      if (current !== sequence || queried !== fingerprint()) return;
      if (payload.code === 'DATES_UNAVAILABLE') { showAlternatives(payload); return; }
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'Não foi possível consultar estas datas. Tente novamente.');
      const quote = payload.quote;
      if (!quote || !Number.isFinite(Number(quote.total)) || Number(quote.total) <= 0) throw new Error('O valor da estadia está indisponível. Fale com a equipe.');
      checked = queried;
      form.querySelector('[data-stay-summary]').textContent = `${displayDate(form.elements.checkin.value)} → ${displayDate(form.elements.checkout.value)} · ${form.elements.hospedes.value} hóspede(s) · ${quote.nights} noite(s)`;
      form.querySelector('[data-stay-total]').textContent = `Total: ${new Intl.NumberFormat('pt-BR', {style: 'currency', currency: quote.currency}).format(Number(quote.total))}`;
      feedback.textContent = 'Datas disponíveis! Confira o valor e complete seus dados abaixo.';
      step(true);
      if (!wasDetails) {
        feedback.focus({preventScroll: true});
        feedback.scrollIntoView({behavior: 'smooth', block: 'center'});
      }
      track('reservation_availability_available');
    } catch (error) {
      if (current !== sequence) return;
      checked = null;
      feedback.textContent = error.name === 'AbortError' ? 'A consulta demorou mais que o esperado. Tente novamente.' : (error.message || 'Não foi possível consultar agora. Tente novamente.');
      const retry = document.createElement('button');
      retry.type = 'button'; retry.className = 'secondary-button'; retry.textContent = 'Tentar novamente';
      retry.addEventListener('click', lookup);
      feedback.append(document.createElement('br'), retry);
      if (wasDetails) form.querySelector('[data-stay-total]').textContent = 'Valor pendente de atualização';
      track('reservation_availability_failed');
    } finally {
      clearTimeout(timeout);
      if (current === sequence) setBusy(false);
    }
  };
  for (const name of ['checkin', 'checkout', 'hospedes']) {
    form.elements[name].addEventListener('input', () => {
      invalidate();
      form.elements.checkout.setCustomValidity('');
      feedback.hidden = true;
      step(false);
    });
  }
  form.elements.pets.addEventListener('change', lookup);
  form.querySelector('[data-edit-dates]').addEventListener('click', () => {
    invalidate(); step(false); feedback.hidden = true; form.elements.checkin.focus();
  });
  form.addEventListener('submit', event => {
    if (details.hidden || event.submitter === check) {
      event.preventDefault();
      if (!busy) lookup();
      return;
    }
    if (busy) { event.preventDefault(); return; }
    if (checked !== fingerprint()) { event.preventDefault(); lookup(); return; }
    const children = form.elements.criancas;
    children.setCustomValidity(Number(children.value) >= Number(form.elements.hospedes.value) ? 'Inclua ao menos um adulto no total de hóspedes.' : '');
    if (!form.checkValidity()) {
      event.preventDefault();
      if (!children.checkValidity() || !form.elements.pets.checkValidity()) form.querySelector('.stay-extras').open = true;
      form.reportValidity(); return;
    }
    track('reservation_form_submit_intent');
    pay.disabled = true;
    pay.textContent = 'Preparando seu Pix…';
    form.setAttribute('aria-busy', 'true');
  });
  form.elements.criancas.addEventListener('input', () => form.elements.criancas.setCustomValidity(''));
  form.addEventListener('focusin', () => track('reservation_form_start'), {once: true});
  window.addEventListener('pageshow', event => {
    if (event.persisted) { invalidate(); step(false); feedback.hidden = true; pay.textContent = 'Continuar para o Pix'; }
  });
  step(false);
  pay.disabled = true;
})();
