<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;

final class ContractModelPdfService
{
    private const BLANK = '________________________________';

    public function __construct(private PDO $db, private ?PdfRenderer $pdfRenderer = null)
    {
    }

    public function generate(): string
    {
        $templateService = new ContractTemplateService($this->db);
        $version = $templateService->activeVersion();
        $variables = self::modelVariables((new PropertySettingsService($this->db))->values());
        $variables['contract_version'] = (string) $version['version_no'];
        $variables['document_hash'] = hash(
            'sha256',
            'CONTRACT_MODEL|' . (string) $version['content_hash'] . '|' . json_encode($variables, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
        );

        $body = $templateService->render((string) $version['body_html'], $variables);
        $notice = '<div style="margin:0 0 5mm;padding:3mm;border:1px solid #b48a4a;background:#f6f1e7;text-align:center">'
            . '<strong>MODELO DE CONTRATO — SEM DADOS DE CLIENTE</strong><br>'
            . '<span style="font-size:8pt">Campos da reserva e do locatário deverão ser preenchidos na emissão do contrato definitivo.</span>'
            . '</div>';
        $html = ContractPdfTemplate::render(
            'Modelo de contrato de locação temporária',
            $notice . $body,
            (string) $variables['document_hash'],
            $variables
        );

        $directory = BASE_PATH . '/tmp/contracts';
        $path = $directory . '/contrato-modelo-' . bin2hex(random_bytes(8)) . '.pdf';
        ($this->pdfRenderer ??= new PdfRenderer())->render($html, $path);
        return $path;
    }

    public static function modelVariables(array $settings): array
    {
        $blank = self::BLANK;
        $maxGuests = max(1, (int) ($settings['MAX_GUESTS'] ?? 10));
        $setting = static fn(string $key, string $fallback = self::BLANK): string => trim((string) ($settings[$key] ?? '')) ?: $fallback;

        $guestRows = [];
        for ($index = 0; $index < $maxGuests; $index++) {
            $guestRows[] = ['full_name' => '', 'cpf' => '', 'birth_date' => '', 'phone' => ''];
        }

        return [
            'owner_full_name' => $setting('OWNER_FULL_NAME'),
            'owner_nationality' => $setting('OWNER_NATIONALITY'),
            'owner_marital_status' => $setting('OWNER_MARITAL_STATUS'),
            'owner_profession' => $setting('OWNER_PROFESSION'),
            'owner_rg' => $setting('OWNER_RG'),
            'owner_cpf' => $setting('OWNER_CPF'),
            'owner_address' => $setting('OWNER_ADDRESS'),
            'owner_phone' => $setting('OWNER_PHONE'),
            'owner_email' => $setting('OWNER_EMAIL'),
            'guest_full_name' => $blank,
            'guest_nationality' => $blank,
            'guest_marital_status' => $blank,
            'guest_profession' => $blank,
            'guest_rg' => $blank,
            'guest_cpf' => $blank,
            'guest_address' => $blank,
            'guest_phone' => $blank,
            'guest_email' => $blank,
            'property_name' => $setting('PROPERTY_NAME'),
            'property_full_address' => $setting('PROPERTY_FULL_ADDRESS'),
            'checkin_at' => '____/____/________ às ____:____',
            'checkout_at' => '____/____/________ às ____:____',
            'number_of_nights' => '____',
            'total_amount' => 'R$ ' . $blank,
            'rental_amount' => 'R$ ' . $blank,
            'cleaning_fee' => 'R$ ' . $blank,
            'extra_guest_amount' => 'R$ ' . $blank,
            'pet_fee_amount' => 'R$ ' . $blank,
            'other_charges' => 'R$ ' . $blank,
            'deposit_amount' => 'R$ ' . $blank,
            'deposit_due_at' => '____/____/________',
            'balance_amount' => 'R$ ' . $blank,
            'balance_due_at' => '____/____/________',
            'payment_method' => $setting('PAYMENT_METHOD'),
            'unauthorized_visitor_fee' => $setting('UNAUTHORIZED_VISITOR_FEE', 'R$ ' . $blank),
            'security_deposit_description' => 'conforme condições da reserva definitiva',
            'cancellation_policy' => $setting('CANCELLATION_POLICY'),
            'quiet_hours' => $setting('QUIET_HOURS'),
            'pets_policy' => !empty($settings['PETS_ALLOWED'])
                ? 'Permitidos até ' . (int) ($settings['MAX_PETS'] ?? 0) . ' pet(s), mediante comunicação.'
                : 'Não permitidos.',
            'contract_forum_city' => $setting('CONTRACT_FORUM_CITY'),
            'contract_city' => $setting('CONTRACT_CITY'),
            'contract_date_long' => '____ de __________________ de ______',
            'checkin_time' => $setting('DEFAULT_CHECKIN_TIME'),
            'checkout_time' => $setting('DEFAULT_CHECKOUT_TIME'),
            'max_guests' => $maxGuests,
            'emergency_contact' => $setting('EMERGENCY_CONTACT'),
            'security_deposit_amount' => 'R$ ' . $blank,
            'security_deductions' => $blank,
            'security_balance' => $blank,
            'security_return_date' => $blank,
            'inventory_rows' => ContractTemplateService::genericRows([], ['item', 'quantity', 'condition', 'value', 'notes'], 'A preencher na vistoria.'),
            'guest_rows' => ContractTemplateService::guestRows($guestRows, $maxGuests),
            'vehicle_rows' => ContractTemplateService::genericRows([], ['driver_name', 'make_model', 'color', 'plate'], 'A preencher.'),
            'contract_number' => 'MODELO',
            'contract_version' => 'MODELO',
            'document_hash' => 'MODELO',
        ];
    }
}
