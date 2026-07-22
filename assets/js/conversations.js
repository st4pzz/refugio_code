(() => {
    const app = document.querySelector('[data-conversation-app]');
    if (!app) return;

    const stream = app.querySelector('[data-message-stream]');
    const pollUrl = app.dataset.pollUrl;
    const pollInterval = Math.max(5000, Number(app.dataset.pollInterval) || 10000);
    const textNode = (value) => document.createTextNode(value ?? '');

    const bindReply = (button) => button.addEventListener('click', () => {
        const input = document.querySelector('[data-reply-input]');
        const textarea = document.querySelector('#conversation-text');
        if (input) input.value = button.dataset.replyMessage || '';
        if (textarea) {
            textarea.placeholder = `Respondendo à mensagem #${button.dataset.replyMessage}`;
            textarea.focus();
        }
    });

    const addMessage = (message) => {
        if (!stream || stream.querySelector(`[data-message-id="${message.id}"]`)) return;
        const article = document.createElement('article');
        article.className = `message-bubble ${message.direcao === 'SAIDA' ? 'outgoing' : 'incoming'}`;
        article.dataset.messageId = message.id;

        const type = document.createElement('small');
        type.append(textNode(message.tipo));
        article.append(type);

        if (message.texto) {
            const paragraph = document.createElement('p');
            paragraph.append(textNode(message.texto));
            article.append(paragraph);
        }
        if (message.media_url) {
            const link = document.createElement('a');
            link.href = message.media_url;
            link.target = '_blank';
            link.rel = 'noopener';
            link.append(textNode(message.media_nome || 'Abrir anexo'));
            article.append(link);
        }

        const footer = document.createElement('footer');
        footer.append(textNode(`${message.created_at ?? ''} · ${message.status ?? ''}`));
        article.append(footer);
        if (message.erro) {
            const error = document.createElement('span');
            error.className = 'message-error';
            error.append(textNode(message.erro));
            article.append(error);
        }

        const reply = document.createElement('button');
        reply.type = 'button';
        reply.className = 'message-reply';
        reply.dataset.replyMessage = message.id;
        reply.textContent = 'Responder';
        bindReply(reply);
        article.append(reply);
        stream.append(article);
        stream.dataset.lastMessageId = message.id;
        stream.scrollTop = stream.scrollHeight;
    };

    document.querySelectorAll('[data-reply-message]').forEach(bindReply);
    if (stream) stream.scrollTop = stream.scrollHeight;

    const poll = async () => {
        if (!pollUrl || document.hidden) return;
        try {
            const separator = pollUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${pollUrl}${separator}after=${encodeURIComponent(stream?.dataset.lastMessageId || 0)}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            if (!response.ok) return;
            const data = await response.json();
            (data.messages || []).forEach(addMessage);
        } catch {
            // A próxima consulta curta tenta novamente sem interromper o atendimento.
        }
    };

    window.setInterval(poll, pollInterval);
    document.querySelector('[data-conversation-back]')?.addEventListener('click', () => app.classList.remove('has-selected'));
    document.querySelector('[data-copy-phone]')?.addEventListener('click', async (event) => {
        try {
            await navigator.clipboard.writeText(event.currentTarget.dataset.copyPhone);
            event.currentTarget.textContent = 'Copiado';
        } catch {
            // O navegador pode bloquear a área de transferência fora de HTTPS.
        }
    });
})();
