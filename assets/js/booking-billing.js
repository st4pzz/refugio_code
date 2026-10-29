document.querySelectorAll('[data-booking-billing]').forEach(form => {
  const preview = () => {
    const date = form.elements.due_on.value.split('-');
    const values = [form.elements.guest_name.value || '[nome]', form.dataset.checkin, form.dataset.checkout,
      form.elements.amount.value ? new Intl.NumberFormat('pt-BR', {style: 'currency', currency: 'BRL'}).format(Number(form.elements.amount.value)) : '[valor]',
      date.length === 3 ? date.reverse().join('/') : '[vencimento]'];
    form.querySelector('[data-booking-preview]').textContent = form.dataset.template.replace(/\{\{([1-5])\}\}/g, (_, number) => values[Number(number) - 1]);
  };
  form.addEventListener('input', preview);
  form.addEventListener('submit', event => {
    if (!event.submitter?.hasAttribute('formaction')) return;
    if (form.elements.paid.checked || !form.elements.confirmed_booking.checked || !form.elements.amount.value || !form.elements.due_on.value || !form.querySelector('[name="channels[]"]:checked')) {
      event.preventDefault();
      alert('Para cobrar, informe valor e vencimento, escolha o canal e confirme o pagamento direto. Desmarque “Pagamento já conferido” somente se houver valor devido.');
    }
  });
  preview();
});
