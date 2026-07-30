<?php
declare(strict_types=1);

use Refugio\Support\Env;

$config = require dirname(__DIR__) . '/bootstrap.php';
$type = (string) ($_GET['tipo'] ?? 'privacidade');
$pages = [
    'privacidade' => [
        'title' => 'Política de Privacidade',
        'description' => 'Saiba como o Refúgio do Cuscuzeiro coleta, utiliza, compartilha, protege e elimina dados pessoais.',
    ],
    'termos' => [
        'title' => 'Termos de Serviço',
        'description' => 'Condições de uso do site, do sistema de reservas e dos canais digitais do Refúgio do Cuscuzeiro.',
    ],
    'exclusao-de-dados' => [
        'title' => 'Exclusão de Dados do Usuário',
        'description' => 'Instruções para solicitar a exclusão de dados pessoais e revogar o acesso do aplicativo Refugio_Site.',
    ],
    'cancelamento' => [
        'title' => 'Política de Cancelamento',
        'description' => 'Condições gerais de cancelamento das reservas do Refúgio do Cuscuzeiro.',
    ],
    'regras' => [
        'title' => 'Regras da Propriedade',
        'description' => 'Regras gerais aplicáveis às hospedagens no Refúgio do Cuscuzeiro.',
    ],
];

if (!isset($pages[$type])) {
    http_response_code(404);
    $type = 'privacidade';
}

$contactEmail = Env::get('ADMIN_EMAIL', 'refugiodocuscuzeiro@gmail.com');
if (!filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
    $contactEmail = 'refugiodocuscuzeiro@gmail.com';
}
$heading = $pages[$type]['title'];
$title = $heading;
$metaDescription = $pages[$type]['description'];
$robots = 'index,follow';
$canonical = base_url('politicas/' . $type);
$updatedAt = '30 de julho de 2026';

require BASE_PATH . '/app/Views/public/_top.php';
?>
<article class="reservation-card policy-page">
    <p class="eyebrow">Refúgio do Cuscuzeiro · Analândia/SP</p>
    <h1><?= e($heading) ?></h1>
    <p><small>Última atualização: <?= e($updatedAt) ?></small></p>

    <nav class="policy-nav" aria-label="Documentos legais">
        <a href="<?= e(base_url('politicas/privacidade')) ?>">Privacidade</a>
        <a href="<?= e(base_url('politicas/termos')) ?>">Termos</a>
        <a href="<?= e(base_url('politicas/exclusao-de-dados')) ?>">Exclusão de dados</a>
    </nav>

    <?php if ($type === 'privacidade'): ?>
        <h2>1. Quem controla os dados</h2>
        <p>O controlador é o <strong>Refúgio do Cuscuzeiro</strong>, serviço de hospedagem localizado em Analândia, São Paulo. Dúvidas e solicitações sobre privacidade podem ser enviadas para <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>.</p>

        <h2>2. Dados tratados</h2>
        <p>Conforme o recurso utilizado, podemos tratar nome, e-mail, telefone, CPF quando necessário, datas e participantes da hospedagem, mensagens, consentimentos, dados de pré-check-in, informações de pagamento fornecidas pelo responsável, comprovantes enviados, avaliações e registros técnicos de segurança.</p>
        <p>Quando as integrações com Meta estão habilitadas, também podemos receber identificadores empresariais, contas de anúncio, páginas, métricas de campanha e eventos do WhatsApp necessários para prestar atendimento. Senhas de redes sociais não são recebidas nem armazenadas pelo Refúgio.</p>

        <h2>3. Finalidades</h2>
        <ul>
            <li>analisar solicitações, verificar disponibilidade e administrar reservas;</li>
            <li>emitir cobranças, identificar pagamentos e preparar a hospedagem;</li>
            <li>prestar atendimento por e-mail e WhatsApp quando autorizado;</li>
            <li>cumprir obrigações legais, contábeis, fiscais e de segurança;</li>
            <li>medir o desempenho de campanhas e melhorar a comunicação, quando as integrações correspondentes estiverem ativas;</li>
            <li>prevenir fraude, abuso e acesso não autorizado.</li>
        </ul>

        <h2>4. Compartilhamento</h2>
        <p>Os dados são compartilhados somente quando necessário com provedores de hospedagem, e-mail, armazenamento e comunicação; com a Meta para WhatsApp e integrações empresariais autorizadas; com autoridades quando houver obrigação legal; e com profissionais essenciais à execução da hospedagem. Não vendemos dados pessoais.</p>

        <h2>5. Cookies, atribuição e segurança</h2>
        <p>O site pode usar cookies de sessão, identificadores de segurança e parâmetros de campanha para manter o painel autenticado, preservar preferências, evitar abuso e identificar a origem de uma solicitação. Tokens e credenciais de integrações são protegidos e não são exibidos publicamente.</p>

        <h2>6. Retenção</h2>
        <p>Mantemos os dados pelo tempo necessário à reserva, ao atendimento, à prevenção de fraude e ao cumprimento de obrigações legais. Depois desse período, os dados são eliminados ou anonimizados de forma segura. Registros financeiros ou de auditoria que precisem ser preservados por lei podem permanecer com acesso restrito.</p>

        <h2>7. Seus direitos</h2>
        <p>Você pode solicitar confirmação de tratamento, acesso, correção, informação sobre compartilhamento, portabilidade quando aplicável, revogação de consentimento, oposição e exclusão dos dados que não precisem ser preservados. Consulte as <a href="<?= e(base_url('politicas/exclusao-de-dados')) ?>">instruções de exclusão de dados</a>.</p>

        <h2>8. Atualizações</h2>
        <p>Esta política pode ser atualizada para refletir mudanças no serviço ou na legislação. A versão vigente e sua data permanecem publicadas nesta página.</p>
    <?php elseif ($type === 'termos'): ?>
        <h2>1. Aceitação</h2>
        <p>Ao utilizar o site, enviar uma solicitação de reserva ou interagir com nossos canais digitais, você concorda com estes termos e com a <a href="<?= e(base_url('politicas/privacidade')) ?>">Política de Privacidade</a>.</p>

        <h2>2. Solicitação e confirmação da reserva</h2>
        <p>O envio do formulário cria uma <strong>solicitação</strong>, não uma reserva confirmada. A confirmação ocorre somente após análise de disponibilidade, apresentação das condições, identificação do pagamento exigido e comunicação expressa do Refúgio.</p>

        <h2>3. Informações e pagamento</h2>
        <p>O responsável deve fornecer informações verdadeiras e manter seus contatos atualizados. Valores, prazo, Pix, política de cancelamento e demais condições são apresentados antes do pagamento. Em caso de divergência, o cliente deve interromper o pagamento e entrar em contato.</p>

        <h2>4. Uso dos canais digitais</h2>
        <p>É proibido tentar acessar áreas restritas, interferir no funcionamento do sistema, enviar arquivos maliciosos, utilizar dados de terceiros sem autorização ou praticar fraude. Links privados de reserva, pagamento, contrato e pré-check-in não devem ser compartilhados.</p>

        <h2>5. Serviços de terceiros</h2>
        <p>Alguns recursos dependem de serviços como hospedagem, e-mail, WhatsApp e plataformas de anúncios. Cada fornecedor pode aplicar seus próprios termos. O Refúgio não solicita a senha da conta Meta, Facebook ou Instagram do usuário.</p>

        <h2>6. Disponibilidade e alterações</h2>
        <p>Podemos realizar manutenção, corrigir falhas e atualizar recursos. Estes termos podem ser alterados quando o serviço ou a legislação mudar; a versão vigente será publicada nesta página.</p>

        <h2>7. Contato</h2>
        <p>Para dúvidas sobre estes termos, escreva para <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>.</p>
    <?php elseif ($type === 'exclusao-de-dados'): ?>
        <h2>Como solicitar a exclusão</h2>
        <p>Você pode solicitar a exclusão dos dados associados ao site, às reservas, ao WhatsApp ou ao aplicativo Meta <strong>Refugio_Site</strong> enviando um e-mail para <a href="mailto:<?= e($contactEmail) ?>?subject=Exclus%C3%A3o%20de%20dados%20-%20App%20Meta"><?= e($contactEmail) ?></a> com o assunto <strong>“Exclusão de dados – App Meta”</strong>.</p>
        <p>Informe o e-mail ou telefone utilizado no atendimento e, se disponível, o código da reserva. Não envie senha, token de acesso, dados bancários completos ou cópia de documento no primeiro contato. Poderemos solicitar uma confirmação proporcional para evitar que terceiros apaguem seus dados.</p>

        <h2>Revogar o acesso pela Meta</h2>
        <p>Se você autorizou o aplicativo por uma conta Meta/Facebook, também pode revogar o acesso nas configurações da sua conta, na área de aplicativos e sites ou integrações empresariais. A revogação impede novos acessos, mas não substitui a solicitação acima quando você também deseja apagar dados já armazenados pelo Refúgio.</p>

        <h2>O que acontece depois</h2>
        <ol>
            <li>Confirmaremos o recebimento e forneceremos uma referência para acompanhamento.</li>
            <li>Localizaremos os dados vinculados aos identificadores confirmados.</li>
            <li>Excluiremos ou anonimizaremos dados que não precisem ser preservados.</li>
            <li>Informaremos a conclusão ou justificaremos eventual retenção obrigatória.</li>
        </ol>
        <p>Dados necessários ao cumprimento de obrigação legal, defesa de direitos, prevenção de fraude ou registros financeiros podem ser mantidos de forma restrita pelo período aplicável. Os demais dados serão eliminados ou anonimizados.</p>

        <h2>Prazo e contato</h2>
        <p>Responderemos ao pedido em prazo razoável e informaremos qualquer etapa adicional necessária. Dúvidas podem ser enviadas ao mesmo endereço: <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a>.</p>
    <?php elseif ($type === 'cancelamento'): ?>
        <p>As condições específicas de cancelamento são apresentadas na aprovação da solicitação e na página de pagamento. Elas podem variar conforme antecedência, datas especiais, custos assumidos e condições expressamente aceitas.</p>
        <p>Para cancelar, entre em contato imediatamente pelo canal utilizado na reserva. Reembolsos, créditos ou retenções serão analisados conforme as condições aceitas e a legislação aplicável.</p>
    <?php else: ?>
        <p>Respeite o limite de hóspedes, horários combinados, vizinhança, áreas de lazer e orientações de segurança. Eventos, visitantes adicionais, animais e equipamentos especiais exigem autorização prévia.</p>
        <p>As regras completas e específicas da hospedagem são apresentadas antes da confirmação e no processo de pré-check-in. Danos ou descumprimentos podem gerar cobrança conforme as condições aceitas e a legislação aplicável.</p>
    <?php endif; ?>

    <p><a class="secondary-button inline" href="<?= e(base_url()) ?>">Voltar ao site</a></p>
</article>
<?php require BASE_PATH . '/app/Views/public/_bottom.php'; ?>
