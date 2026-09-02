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
            const isImage = ['IMAGEM', 'STICKER'].includes(message.tipo) || message.media_mime?.startsWith('image/');
            const isAudio = message.tipo === 'AUDIO' || message.media_mime?.startsWith('audio/');
            if (isImage) {
                const link = document.createElement('a');
                const image = document.createElement('img');
                link.href = message.media_url;
                link.target = '_blank';
                link.rel = 'noopener';
                image.src = message.media_url;
                image.alt = 'Imagem recebida';
                image.loading = 'lazy';
                link.append(image);
                article.append(link);
            } else if (isAudio) {
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.preload = 'metadata';
                audio.src = message.media_url;
                article.append(audio);
            } else {
                const link = document.createElement('a');
                link.href = message.media_url;
                link.target = '_blank';
                link.rel = 'noopener';
                link.append(textNode(message.media_nome || 'Abrir anexo'));
                article.append(link);
            }
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

    const aiButton = document.querySelector('[data-ai-suggest]');
    const aiFeedback = document.querySelector('[data-ai-feedback]');
    aiButton?.addEventListener('click', async () => {
        const textarea = document.querySelector('#conversation-text');
        if (!textarea || !aiButton.dataset.aiUrl) return;
        const originalLabel = aiButton.textContent;
        aiButton.disabled = true;
        aiButton.setAttribute('aria-busy', 'true');
        aiButton.textContent = 'Consultando dados…';
        if (aiFeedback) {
            aiFeedback.className = '';
            aiFeedback.textContent = 'Lendo a conversa e verificando calendário e preços atuais.';
        }
        try {
            const response = await fetch(aiButton.dataset.aiUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                },
                credentials: 'same-origin',
                body: new URLSearchParams({ _csrf: aiButton.dataset.aiCsrf || '' }),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.ok || !data.draft) {
                throw new Error(data.error || 'Não foi possível gerar o rascunho.');
            }
            textarea.value = data.draft;
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
            if (aiFeedback) {
                aiFeedback.className = data.needs_human_review ? 'warning' : 'success';
                aiFeedback.textContent = data.needs_human_review && data.review_reason
                    ? `Revise com atenção: ${data.review_reason}`
                    : 'Rascunho gerado. Revise antes de clicar em Enviar.';
            }
        } catch (error) {
            if (aiFeedback) {
                aiFeedback.className = 'error';
                aiFeedback.textContent = error instanceof Error ? error.message : 'Não foi possível gerar o rascunho.';
            }
        } finally {
            aiButton.disabled = false;
            aiButton.removeAttribute('aria-busy');
            aiButton.textContent = originalLabel;
        }
    });

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
