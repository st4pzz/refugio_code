<?php
declare(strict_types=1);

namespace Refugio\Repositories;

use PDO;

final class FinancialRepository
{
    public function __construct(private PDO $db) {}

    public function dashboard(string $start,string $end):array
    {
        $sql="SELECT
            COALESCE((SELECT SUM(valor-valor_estornado) FROM recebimentos WHERE DATE(recebido_em) BETWEEN ? AND ?),0) recebido,
            COALESCE((SELECT SUM(valor-valor_estornado) FROM pagamentos_despesas WHERE DATE(pago_em) BETWEEN ? AND ?),0) pago,
            COALESCE((SELECT SUM(valor_original-valor_recebido+valor_estornado) FROM contas_receber WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento BETWEEN ? AND ?),0) a_receber,
            COALESCE((SELECT SUM(valor_original-valor_pago+valor_estornado) FROM contas_pagar WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento BETWEEN ? AND ?),0) a_pagar,
            COALESCE((SELECT SUM(valor_original-valor_recebido+valor_estornado) FROM contas_receber WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento<CURDATE()),0) receber_vencido,
            COALESCE((SELECT SUM(valor_original-valor_pago+valor_estornado) FROM contas_pagar WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento<CURDATE()),0) pagar_vencido";
        $stmt=$this->db->prepare($sql);$stmt->execute([$start,$end,$start,$end,$start,$end,$start,$end]);return $stmt->fetch();
    }

    public function receivables(string $start,string $end):array{$s=$this->db->prepare('SELECT cr.*,r.codigo reserva_codigo,c.nome cliente_nome,cf.nome conta_nome,cat.nome categoria_nome FROM contas_receber cr LEFT JOIN reservas r ON r.id=cr.reserva_id LEFT JOIN clientes c ON c.id=cr.cliente_id LEFT JOIN contas_financeiras cf ON cf.id=cr.conta_id LEFT JOIN categorias_financeiras cat ON cat.id=cr.categoria_id WHERE cr.vencimento BETWEEN ? AND ? ORDER BY cr.vencimento,cr.id LIMIT 300');$s->execute([$start,$end]);return $s->fetchAll();}
    public function expenses(string $start,string $end):array{$s=$this->db->prepare('SELECT cp.*,f.nome fornecedor_nome,cf.nome conta_nome,cat.nome categoria_nome FROM contas_pagar cp LEFT JOIN fornecedores f ON f.id=cp.fornecedor_id LEFT JOIN contas_financeiras cf ON cf.id=cp.conta_id LEFT JOIN categorias_financeiras cat ON cat.id=cp.categoria_id WHERE cp.vencimento BETWEEN ? AND ? ORDER BY cp.vencimento,cp.id LIMIT 300');$s->execute([$start,$end]);return $s->fetchAll();}
    public function receipts(string $start,string $end):array{$s=$this->db->prepare('SELECT re.*,cr.descricao,c.nome conta_nome FROM recebimentos re JOIN contas_receber cr ON cr.id=re.conta_receber_id JOIN contas_financeiras c ON c.id=re.conta_id WHERE DATE(re.recebido_em) BETWEEN ? AND ? ORDER BY re.recebido_em DESC LIMIT 300');$s->execute([$start,$end]);return$s->fetchAll();}
    public function expensePayments(string $start,string $end):array{$s=$this->db->prepare('SELECT pd.*,cp.descricao,c.nome conta_nome FROM pagamentos_despesas pd JOIN contas_pagar cp ON cp.id=pd.conta_pagar_id JOIN contas_financeiras c ON c.id=pd.conta_id WHERE DATE(pd.pago_em) BETWEEN ? AND ? ORDER BY pd.pago_em DESC LIMIT 300');$s->execute([$start,$end]);return$s->fetchAll();}
    public function movements(string $start,string $end):array{$s=$this->db->prepare('SELECT m.*,c.nome conta_nome FROM movimentos_financeiros m JOIN contas_financeiras c ON c.id=m.conta_id WHERE DATE(m.data_movimento) BETWEEN ? AND ? ORDER BY m.data_movimento DESC,m.id DESC LIMIT 500');$s->execute([$start,$end]);return $s->fetchAll();}
    public function cashFlow(string $start,string $end):array{$s=$this->db->prepare("SELECT data,SUM(entrada_prevista) entrada_prevista,SUM(saida_prevista) saida_prevista,SUM(entrada_realizada) entrada_realizada,SUM(saida_realizada) saida_realizada FROM (SELECT vencimento data,valor_original-valor_recebido+valor_estornado entrada_prevista,0 saida_prevista,0 entrada_realizada,0 saida_realizada FROM contas_receber WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento BETWEEN ? AND ? UNION ALL SELECT vencimento,0,valor_original-valor_pago+valor_estornado,0,0 FROM contas_pagar WHERE status IN ('PENDENTE','PARCIAL','VENCIDO') AND vencimento BETWEEN ? AND ? UNION ALL SELECT DATE(recebido_em),0,0,valor-valor_estornado,0 FROM recebimentos WHERE DATE(recebido_em) BETWEEN ? AND ? UNION ALL SELECT DATE(pago_em),0,0,0,valor-valor_estornado FROM pagamentos_despesas WHERE DATE(pago_em) BETWEEN ? AND ?) f GROUP BY data ORDER BY data");$s->execute([$start,$end,$start,$end,$start,$end,$start,$end]);return $s->fetchAll();}
    public function accounts():array{return $this->db->query('SELECT * FROM contas_financeiras WHERE ativa=1 ORDER BY nome')->fetchAll();}
    public function categories():array{return $this->db->query('SELECT * FROM categorias_financeiras WHERE ativa=1 ORDER BY tipo,nome')->fetchAll();}
    public function suppliers():array{return $this->db->query('SELECT * FROM fornecedores WHERE ativo=1 ORDER BY nome')->fetchAll();}
    public function recurrences():array{return $this->db->query('SELECT rf.*,f.nome fornecedor_nome,c.nome categoria_nome FROM recorrencias_financeiras rf LEFT JOIN fornecedores f ON f.id=rf.fornecedor_id LEFT JOIN categorias_financeiras c ON c.id=rf.categoria_id ORDER BY rf.ativa DESC,rf.proxima_competencia LIMIT 200')->fetchAll();}
    public function deposits():array{return $this->db->query('SELECT c.*,r.codigo reserva_codigo,r.nome_cliente FROM caucoes c JOIN reservas r ON r.id=c.reserva_id ORDER BY c.created_at DESC LIMIT 200')->fetchAll();}
    public function reconciliations():array{return $this->db->query('SELECT c.*,f.nome conta_nome,u.nome usuario_nome FROM conciliacoes_financeiras c JOIN contas_financeiras f ON f.id=c.conta_id LEFT JOIN usuarios_admin u ON u.id=c.conciliado_por ORDER BY c.created_at DESC LIMIT 100')->fetchAll();}
}
