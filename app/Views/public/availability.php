<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta name="robots" content="index,follow">
    <title>Verificar disponibilidade | Refúgio do Cuscuzeiro</title>
    <meta name="description" content="Consulte no calendário as datas disponíveis para se hospedar no Refúgio do Cuscuzeiro, em Analândia/SP.">
    <link rel="canonical" href="https://www.refugiodocuscuzeiro.com.br/disponibilidade/">
    <link rel="icon" type="image/png" href="<?= e(base_url('assets/images/logo_refugio.png')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@700&display=swap">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/availability.css?v=1')) ?>">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="availability-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(base_url()) ?>" aria-label="Refúgio do Cuscuzeiro — página inicial">
            <img src="<?= e(base_url('assets/images/logo_crema.webp')) ?>" alt="" width="66" height="66">
        </a>
        <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="availability-menu" aria-label="Abrir menu"><span></span><span></span><span></span></button>
        <nav aria-label="Navegacao principal">
            <ul class="menu" id="availability-menu">
                <li><a href="<?= e(base_url('a-chacara')) ?>">A Chácara</a></li>
                <li><a href="<?= e(base_url('galeria-de-fotos')) ?>">Fotos</a></li>
                <li><a href="<?= e(base_url('analandia/')) ?>">Analândia</a></li>
                <li><a href="<?= e(base_url('blog/')) ?>">Blog</a></li>
                <li><a class="current" href="<?= e(base_url('disponibilidade/')) ?>" aria-current="page">Verificar disponibilidade</a></li>
                <li><a class="nav-cta" href="<?= e(base_url('reserva/solicitar')) ?>">Reserva direta</a></li>
            </ul>
        </nav>
    </div>
</header>

<main id="conteudo" class="availability-main">
    <section class="availability-intro">
        <p class="eyebrow">Planeje sua estadia</p>
        <h1>Verificar disponibilidade</h1>
        <p>Consulte as datas livres para o Refúgio do Cuscuzeiro. Dias marcados com <strong>X</strong> não estão disponíveis.</p>
    </section>

    <section class="calendar-card" data-availability-calendar data-endpoint="<?= e(base_url('api/disponibilidade')) ?>" aria-labelledby="calendar-title">
        <div class="calendar-toolbar">
            <button class="month-button" type="button" data-previous-month aria-label="Ver mês anterior">&#8592;</button>
            <h2 id="calendar-title" data-month-title>Carregando calendário...</h2>
            <button class="month-button" type="button" data-next-month aria-label="Ver próximo mês">&#8594;</button>
        </div>
        <div class="calendar-status" data-calendar-status role="status" aria-live="polite">Consultando as reservas...</div>
        <div class="calendar-weekdays" aria-hidden="true">
            <span>Seg</span><span>Ter</span><span>Qua</span><span>Qui</span><span>Sex</span><span>Sab</span><span>Dom</span>
        </div>
        <div class="calendar-grid" data-calendar-grid role="grid" aria-label="Disponibilidade por dia"></div>
        <div class="calendar-legend" aria-label="Legenda do calendário">
            <span><i class="legend-swatch available" aria-hidden="true"></i>Livre</span>
            <span><i class="legend-swatch unavailable" aria-hidden="true">X</i>Indisponível</span>
            <span><i class="legend-swatch past" aria-hidden="true"></i>Data passada</span>
        </div>
        <p class="calendar-note">O calendário é atualizado com as reservas e bloqueios do sistema. A disponibilidade pode mudar até a confirmação da reserva.</p>
        <a class="primary-action" href="<?= e(base_url('reserva/solicitar')) ?>">Solicitar reserva direta</a>
    </section>
</main>

<footer><p>Refúgio do Cuscuzeiro · Analândia/SP</p><p><a href="<?= e(base_url('politicas/privacidade')) ?>">Privacidade</a> · <a href="<?= e(base_url('politicas/termos')) ?>">Termos</a></p></footer>
<script src="<?= e(base_url('assets/js/availability.js?v=1')) ?>" defer></script>
</body>
</html>
