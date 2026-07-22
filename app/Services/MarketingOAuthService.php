<?php
declare(strict_types=1);

namespace Refugio\Services;

use Refugio\Marketing\MarketingHttpClient;
use Refugio\Support\Env;
use RuntimeException;

final class MarketingOAuthService
{
    private MarketingHttpClient $http;
    public function __construct(private array $config){$this->http=new MarketingHttpClient();}

    public function authorizationUrl(string $provider):string
    {
        $provider=strtoupper($provider);$this->assertConfigured($provider);$state=bin2hex(random_bytes(32));$_SESSION['_marketing_oauth'][$state]=['provider'=>$provider,'expires'=>time()+600];$redirect=$this->callback($provider);
        return match($provider){
            'META'=>'https://www.facebook.com/'.rawurlencode(Env::get('META_API_VERSION','v24.0')).'/dialog/oauth?'.http_build_query(['client_id'=>Env::get('META_APP_ID'),'redirect_uri'=>$redirect,'state'=>$state,'scope'=>'ads_read,business_management'],'','&',PHP_QUERY_RFC3986),
            'GOOGLE'=>'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query(['client_id'=>Env::get('GOOGLE_ADS_CLIENT_ID'),'redirect_uri'=>$redirect,'response_type'=>'code','scope'=>'https://www.googleapis.com/auth/adwords','access_type'=>'offline','prompt'=>'consent','include_granted_scopes'=>'true','state'=>$state],'','&',PHP_QUERY_RFC3986),
            'TIKTOK'=>'https://ads.tiktok.com/marketing_api/auth?'.http_build_query(['app_id'=>Env::get('TIKTOK_ADS_APP_ID'),'redirect_uri'=>$redirect,'state'=>$state],'','&',PHP_QUERY_RFC3986),
            default=>throw new RuntimeException('Provedor OAuth invalido.'),
        };
    }

    public function exchange(string $provider,string $code,string $state):array
    {
        $provider=strtoupper($provider);$this->assertConfigured($provider);$stored=$_SESSION['_marketing_oauth'][$state]??null;unset($_SESSION['_marketing_oauth'][$state]);if(!$stored||($stored['provider']??'')!==$provider||($stored['expires']??0)<time())throw new RuntimeException('Estado OAuth invalido ou expirado.');if($code==='')throw new RuntimeException('O provedor nao retornou o codigo de autorizacao.');$redirect=$this->callback($provider);
        return match($provider){'META'=>$this->meta($code,$redirect),'GOOGLE'=>$this->google($code,$redirect),'TIKTOK'=>$this->tiktok($code,$redirect),default=>throw new RuntimeException('Provedor OAuth invalido.')};
    }

    private function meta(string$code,string$redirect):array
    {
        $r=$this->http->request('GET','https://graph.facebook.com/'.rawurlencode(Env::get('META_API_VERSION','v24.0')).'/oauth/access_token',[],['client_id'=>Env::get('META_APP_ID'),'client_secret'=>Env::get('META_APP_SECRET'),'redirect_uri'=>$redirect,'code'=>$code]);$token=(string)($r['access_token']??'');if($token==='')throw new RuntimeException('A Meta nao retornou access token.');$long=$this->http->request('GET','https://graph.facebook.com/'.rawurlencode(Env::get('META_API_VERSION','v24.0')).'/oauth/access_token',[],['grant_type'=>'fb_exchange_token','client_id'=>Env::get('META_APP_ID'),'client_secret'=>Env::get('META_APP_SECRET'),'fb_exchange_token'=>$token]);return['access_token'=>(string)($long['access_token']??$token),'refresh_token'=>'','expires_at'=>isset($long['expires_in'])?date('Y-m-d H:i:s',time()+(int)$long['expires_in']):null];
    }
    private function google(string$code,string$redirect):array{$r=$this->http->form('https://oauth2.googleapis.com/token',['client_id'=>Env::get('GOOGLE_ADS_CLIENT_ID'),'client_secret'=>Env::get('GOOGLE_ADS_CLIENT_SECRET'),'code'=>$code,'grant_type'=>'authorization_code','redirect_uri'=>$redirect]);return['access_token'=>(string)($r['access_token']??''),'refresh_token'=>(string)($r['refresh_token']??''),'expires_at'=>date('Y-m-d H:i:s',time()+(int)($r['expires_in']??3600))];}
    private function tiktok(string$code,string$redirect):array{$r=$this->http->request('POST','https://business-api.tiktok.com/open_api/'.rawurlencode(Env::get('TIKTOK_API_VERSION','v1.3')).'/oauth2/access_token/',[],[],['app_id'=>Env::get('TIKTOK_ADS_APP_ID'),'secret'=>Env::get('TIKTOK_ADS_APP_SECRET'),'auth_code'=>$code]);$d=$r['data']??$r;if((int)($r['code']??0)!==0||empty($d['access_token']))throw new RuntimeException('TikTok nao retornou access token.');return['access_token'=>(string)$d['access_token'],'refresh_token'=>(string)($d['refresh_token']??''),'expires_at'=>isset($d['access_token_expires_in'])?date('Y-m-d H:i:s',time()+(int)$d['access_token_expires_in']):null];}
    private function assertConfigured(string$provider):void{$keys=match($provider){'META'=>['META_APP_ID','META_APP_SECRET'],'GOOGLE'=>['GOOGLE_ADS_CLIENT_ID','GOOGLE_ADS_CLIENT_SECRET','GOOGLE_ADS_DEVELOPER_TOKEN'],'TIKTOK'=>['TIKTOK_ADS_APP_ID','TIKTOK_ADS_APP_SECRET'],default=>throw new RuntimeException('Provedor OAuth invalido.')};foreach($keys as$key)if(Env::get($key)==='')throw new RuntimeException($key.' nao configurado.');}
    private function callback(string$provider):string{$key=match($provider){'META'=>'META_REDIRECT_URI','GOOGLE'=>'GOOGLE_ADS_REDIRECT_URI','TIKTOK'=>'TIKTOK_ADS_REDIRECT_URI',default=>''};$configured=$key!==''?Env::get($key):'';return$configured!==''?$configured:rtrim((string)$this->config['url'],'/').'/admin/configuracoes/integracoes/'.strtolower($provider).'/callback';}
}
