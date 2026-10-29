<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Services\QuoteService;
use Refugio\Services\RateLimiter;
use Refugio\Support\Csrf;
use Refugio\Support\Security;
use Throwable;

final class PublicQuoteController
{
    public function calculate():never
    {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: private, no-store');
        try{
            Csrf::verify($_POST['_csrf']??($_SERVER['HTTP_X_CSRF_TOKEN']??null));$db=Database::connection();
            (new RateLimiter($db))->assertAllowed('quote|'.Security::clientIp(),30,3600);
            $checkin = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['checkin'] ?? ''));
            $checkout = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) ($_POST['checkout'] ?? ''));
            if (!$checkin || $checkin->format('Y-m-d') !== ($_POST['checkin'] ?? '') || $checkin < new \DateTimeImmutable('today') || !$checkout || $checkout->format('Y-m-d') !== ($_POST['checkout'] ?? '') || $checkout <= $checkin) throw new \RuntimeException('Escolha datas válidas: entrada a partir de hoje e saída após a entrada.');
            $availability = new \Refugio\Services\AvailabilityService($db);
            if ($availability::hasConflicts($availability->conflicts($checkin->format('Y-m-d'), $checkout->format('Y-m-d')))) throw new \RuntimeException('Estas datas não estão disponíveis. Escolha outro período.');
            $availability->assertFreshExternalCalendars();
            $result=(new QuoteService($db))->calculate(['checkin'=>$_POST['checkin']??'','checkout'=>$_POST['checkout']??'','guests'=>$_POST['guests']??'','pets'=>$_POST['pets']??0,'coupon'=>$_POST['coupon']??''],true);
            echo json_encode(['ok'=>true,'quote'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
        }catch(Throwable $error){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);exit;}
    }
}
