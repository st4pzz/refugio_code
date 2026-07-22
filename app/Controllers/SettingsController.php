<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Repositories\FinancialRepository;
use Refugio\Repositories\MarketingRepository;
use Refugio\Services\AuditService;
use Refugio\Services\AuthorizationService;
use Refugio\Support\Csrf;
use Refugio\Support\Env;
use RuntimeException;
use Throwable;

final class SettingsController
{
    private \PDO $db;

    public function __construct(private array $config)
    {
    }

    public function index(): void
    {
        AuthorizationService::requirePermission('configuracoes.view');
        $this->boot();
        $marketing = (new MarketingRepository($this->db))->integrations();
        $webhook = $this->db->query('SELECT status,recebido_em,processado_em FROM whatsapp_webhook_eventos ORDER BY id DESC LIMIT 1')->fetch() ?: null;
        $templateCount = (int) $this->db->query("SELECT COUNT(*) FROM whatsapp_templates WHERE status='APPROVED'")->fetchColumn();
        $financial = new FinancialRepository($this->db);
        $accounts = $financial->accounts();
        $categories = $financial->categories();
        $users = $this->db->query("SELECT u.id,u.nome,u.email,u.ativo,GROUP_CONCAT(p.codigo ORDER BY p.codigo SEPARATOR ', ') perfis FROM usuarios_admin u LEFT JOIN usuarios_admin_perfis up ON up.usuario_id=u.id LEFT JOIN perfis_admin p ON p.id=up.perfil_id GROUP BY u.id ORDER BY u.nome")->fetchAll();
        $profiles = $this->db->query('SELECT id,codigo,nome,descricao FROM perfis_admin ORDER BY id')->fetchAll();
        $whatsapp = [
            'configured' => Env::get('WHATSAPP_PHONE_NUMBER_ID') !== '' && Env::get('WHATSAPP_ACCESS_TOKEN') !== '',
            'phone_id' => self::mask(Env::get('WHATSAPP_PHONE_NUMBER_ID')),
            'business_id' => self::mask(Env::get('WHATSAPP_BUSINESS_ACCOUNT_ID')),
            'verify_token' => Env::get('WHATSAPP_VERIFY_TOKEN') !== '',
            'app_secret' => Env::get('WHATSAPP_APP_SECRET') !== '',
        ];
        require BASE_PATH . '/app/Views/admin/settings.php';
    }

    public function assignProfile(): never
    {
        AuthorizationService::requirePermission('configuracoes.sensitive');
        $this->boot();
        try {
            Csrf::verify($_POST['_csrf'] ?? null);
            $userId = (int) ($_POST['usuario_id'] ?? 0);
            $profileId = (int) ($_POST['perfil_id'] ?? 0);
            if ($userId <= 0 || $profileId <= 0) throw new RuntimeException('Usuario ou perfil invalido.');
            $profile = $this->db->prepare('SELECT codigo FROM perfis_admin WHERE id=?');
            $profile->execute([$profileId]);
            $newCode = $profile->fetchColumn();
            if (!$newCode) throw new RuntimeException('Perfil nao encontrado.');
            $current = $this->db->prepare("SELECT COUNT(*) FROM usuarios_admin_perfis up JOIN perfis_admin p ON p.id=up.perfil_id WHERE up.usuario_id=? AND p.codigo='SUPER_ADMIN'");
            $current->execute([$userId]);
            if ((int) $current->fetchColumn() > 0 && $newCode !== 'SUPER_ADMIN') {
                $superAdmins = (int) $this->db->query("SELECT COUNT(DISTINCT up.usuario_id) FROM usuarios_admin_perfis up JOIN perfis_admin p ON p.id=up.perfil_id JOIN usuarios_admin u ON u.id=up.usuario_id WHERE p.codigo='SUPER_ADMIN' AND u.ativo=1")->fetchColumn();
                if ($superAdmins <= 1) throw new RuntimeException('Nao e permitido remover o ultimo SUPER_ADMIN ativo.');
            }
            $this->db->beginTransaction();
            $this->db->prepare('DELETE FROM usuarios_admin_perfis WHERE usuario_id=?')->execute([$userId]);
            $this->db->prepare('INSERT INTO usuarios_admin_perfis (usuario_id,perfil_id,atribuido_por) VALUES (?,?,?)')->execute([$userId,$profileId,(int) $_SESSION['admin_id']]);
            $this->db->commit();
            if ($userId === (int) $_SESSION['admin_id']) unset($_SESSION['admin_permissions'], $_SESSION['admin_roles']);
            (new AuditService($this->db))->record('CONFIGURACOES','ATRIBUIR_PERFIL','usuarios_admin',$userId,null,['perfil_id'=>$profileId,'perfil'=>$newCode]);
            flash('success','Perfil atualizado.');
        } catch (Throwable $error) {
            if (isset($this->db) && $this->db->inTransaction()) $this->db->rollBack();
            flash('error',$error->getMessage());
        }
        redirect(base_url('admin/configuracoes/integracoes'));
    }

    private static function mask(string $value): string
    {
        if ($value === '') return 'Não configurado';
        return strlen($value) <= 6 ? 'Configurado' : str_repeat('•', max(4, strlen($value) - 4)) . substr($value, -4);
    }

    private function boot(): void
    {
        if (isset($this->db)) return;
        $this->db = Database::connection();
    }
}
