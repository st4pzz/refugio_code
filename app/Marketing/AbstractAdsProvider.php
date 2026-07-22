<?php
declare(strict_types=1);

namespace Refugio\Marketing;

use Refugio\Services\EncryptionService;
use RuntimeException;

abstract class AbstractAdsProvider implements MarketingProviderInterface
{
    protected MarketingHttpClient $http; protected array $config; protected string $accessToken; protected string $refreshToken;
    public function __construct(protected array $integration,?MarketingHttpClient $http=null,?EncryptionService $encryption=null)
    {
        $this->http=$http??new MarketingHttpClient();$this->config=json_decode((string)($integration['config_json']??'{}'),true)?:[];
        if(isset($integration['access_token']))$this->accessToken=(string)$integration['access_token'];else{$encryption??=new EncryptionService();$this->accessToken=$encryption->decrypt($integration['access_token_encrypted']??null);$this->refreshToken=$encryption->decrypt($integration['refresh_token_encrypted']??null);}
        $this->refreshToken??='';
    }
    public function connect(array $credentials):array{return$credentials;}
    public function disconnect():void{$this->accessToken='';$this->refreshToken='';}
    public function syncInsights(string $start,string $end,?string $cursor=null):array{return$this->getInsights($start,$end,$cursor);}
    public static function microsToDecimal(int|string|null $value):?string{return self::decimalFromMicros($value);}
    protected function accountId():string{$id=(string)($this->integration['conta_externa_id']??'');if($id==='')throw new RuntimeException('Selecione uma conta de anuncios.');return$id;}
    protected static function decimalFromMicros(int|string|null $micros):?string{if($micros===null||$micros==='')return null;$negative=str_starts_with((string)$micros,'-');$digits=ltrim((string)$micros,'-');if(!ctype_digit($digits))return null;$digits=str_pad($digits,7,'0',STR_PAD_LEFT);$whole=substr($digits,0,-6);$fraction=rtrim(substr($digits,-6),'0');return($negative?'-':'').$whole.($fraction!==''?'.'.$fraction:'');}
    protected static function decimalFromMinor(int|string|null $minor):?string{if($minor===null||$minor==='')return null;$negative=str_starts_with((string)$minor,'-');$digits=ltrim((string)$minor,'-');if(!ctype_digit($digits))return null;$digits=str_pad($digits,3,'0',STR_PAD_LEFT);return($negative?'-':'').substr($digits,0,-2).'.'.substr($digits,-2);}
    protected static function number(mixed $value):?string{return$value===null||$value===''?null:(is_numeric($value)?(string)$value:null);}
    protected static function page(array $items,?string $cursor=null):array{return['items'=>$items,'next_cursor'=>$cursor];}
}
