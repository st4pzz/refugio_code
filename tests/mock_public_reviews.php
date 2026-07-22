<?php
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(404);
    exit;
}
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'items' => [
        ['nome_exibicao'=>'Maria S.','nota_geral'=>5,'comentario'=>'Lugar tranquilo, muito limpo e com uma vista incrível.','resposta_administrador'=>'Obrigado pela visita!','checkout'=>'2026-07-12'],
        ['nome_exibicao'=>'Hóspede anônimo','nota_geral'=>4,'comentario'=>'A família aproveitou muito a estadia e as opções de lazer.','resposta_administrador'=>null,'checkout'=>'2026-06-20'],
    ],
    'count' => 2,
    'average' => 4.5,
], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
