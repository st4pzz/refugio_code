<?php
declare(strict_types=1);

namespace Refugio\Marketing;

use RuntimeException;

final class MarketingHttpClient
{
    public function request(string $method,string $url,array $headers=[],array $query=[],?array $body=null,int $maxRetries=3):array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('A extensao cURL e obrigatoria para integracoes de marketing.');
        if($query)$url.=(str_contains($url,'?')?'&':'?').http_build_query($query,'','&',PHP_QUERY_RFC3986);
        for($attempt=0;$attempt<=$maxRetries;$attempt++){
            $curl=curl_init($url);$allHeaders=array_merge(['Accept: application/json'],$headers);$options=[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>45,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_HTTPHEADER=>$allHeaders,CURLOPT_CUSTOMREQUEST=>$method];
            if($body!==null){$options[CURLOPT_POSTFIELDS]=json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$allHeaders[]='Content-Type: application/json';$options[CURLOPT_HTTPHEADER]=$allHeaders;}
            curl_setopt_array($curl,$options);$response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$transport=curl_error($curl);curl_close($curl);
            if($response!==false&&$status<300){$decoded=json_decode($response,true);if(!is_array($decoded))throw new RuntimeException('A API de marketing retornou JSON invalido.');return$decoded;}
            if(($status===429||$status>=500||$response===false)&&$attempt<$maxRetries){usleep((int)(250000*(2**$attempt)));continue;}
            $decoded=is_string($response)?json_decode($response,true):null;$message=is_array($decoded)?($decoded['error']['message']??$decoded['message']??$decoded['error']['message_description']??'requisicao recusada'):($transport?:'requisicao recusada');throw new RuntimeException('Falha na API de marketing (HTTP '.$status.'): '.mb_substr((string)$message,0,500));
        }
        throw new RuntimeException('Falha na API de marketing apos novas tentativas.');
    }

    public function form(string $url,array $fields,array $headers=[]):array
    {
        if(!function_exists('curl_init'))throw new RuntimeException('A extensao cURL e obrigatoria.');$curl=curl_init($url);curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30,CURLOPT_HTTPHEADER=>array_merge(['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],$headers),CURLOPT_POSTFIELDS=>http_build_query($fields,'','&',PHP_QUERY_RFC3986)]);$response=curl_exec($curl);$status=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);$decoded=is_string($response)?json_decode($response,true):null;if(!is_array($decoded)||$status>=300)throw new RuntimeException('Falha na troca de credenciais OAuth (HTTP '.$status.').');return$decoded;
    }
}
