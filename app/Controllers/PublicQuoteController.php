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
            $result=(new QuoteService($db))->calculate(['checkin'=>$_POST['checkin']??'','checkout'=>$_POST['checkout']??'','guests'=>$_POST['guests']??'','pets'=>$_POST['pets']??0,'coupon'=>$_POST['coupon']??''],true);
            echo json_encode(['ok'=>true,'quote'=>$result],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
        }catch(Throwable $error){http_response_code(422);echo json_encode(['ok'=>false,'message'=>$error->getMessage()],JSON_UNESCAPED_UNICODE);exit;}
    }
}
