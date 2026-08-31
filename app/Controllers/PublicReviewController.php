<?php
declare(strict_types=1);

namespace Refugio\Controllers;

use Refugio\Config\Database;
use Refugio\Repositories\ReviewRepository;
use Refugio\Services\RateLimiter;
use Refugio\Services\ReviewAccessException;
use Refugio\Services\ReviewService;
use Refugio\Services\ReviewValidationException;
use Refugio\Support\Csrf;
use Refugio\Support\ReviewValidator;
use Refugio\Support\Security;
use Throwable;

final class PublicReviewController
{
    public function __construct(private array $config) {}

    public function show(string $token): void
    {
        header('Cache-Control: private, no-store, max-age=0');
        header('Referrer-Policy: no-referrer');
        try {
            $access=(new ReviewService(Database::connection(),$this->config))->access($token);
            $reservation=$access['reservation']; $invite=$access['invite'];
            $errors=$_SESSION['_review_errors']??[]; $old=$_SESSION['_review_old']??[];
            unset($_SESSION['_review_errors'],$_SESSION['_review_old']);
            require BASE_PATH.'/app/Views/reviews/form.php';
        } catch (ReviewAccessException $e) {
            $alreadyReviewed=$e->alreadyReviewed;
            http_response_code($alreadyReviewed?200:404);
            require BASE_PATH.'/app/Views/reviews/unavailable.php';
        }
    }

    public function submit(string $token): never
    {
        try {
            Csrf::verify($_POST['_csrf']??null);
            (new RateLimiter(Database::connection()))->assertAllowed('review|'.Security::clientIp().'|'.hash('sha256',$token),8,3600);
            (new ReviewService(Database::connection(),$this->config))->submit($token,$_POST);
            $_SESSION['_review_success']=true;
            redirect(base_url('avaliar/obrigado'));
        } catch (ReviewValidationException $e) {
            $_SESSION['_review_errors']=$e->errors; $_SESSION['_review_old']=$_POST;
            redirect(base_url('avaliar/'.$token));
        } catch (Throwable $e) {
            flash('error',$e instanceof ReviewAccessException?$e->getMessage():'Não foi possível enviar a avaliação. Tente novamente.');
            $_SESSION['_review_old']=$_POST;
            redirect(base_url('avaliar/'.$token));
        }
    }

    public function success(): never
    {
        if (empty($_SESSION['_review_success'])) redirect(base_url());
        unset($_SESSION['_review_success']);
        require BASE_PATH.'/app/Views/reviews/success.php';
        exit;
    }

    public function approved(): never
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=300, stale-while-revalidate=600');
        try {
            $data=(new ReviewRepository(Database::connection()))->publicData();
            echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            error_log('[avaliacoes-publicas] '.$e->getMessage());
            echo json_encode(['items'=>[],'count'=>0,'average'=>null],JSON_THROW_ON_ERROR);
        }
        exit;
    }
}
