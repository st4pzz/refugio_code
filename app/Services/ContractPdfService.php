<?php
declare(strict_types=1);

namespace Refugio\Services;

use PDO;
use RuntimeException;

final class ContractPdfService
{
    public function __construct(private PDO $db, private string $pythonBinary = 'python')
    {
    }

    public function generate(int $contractId): array
    {
        $stmt=$this->db->prepare('SELECT * FROM reservation_contracts WHERE id=?');$stmt->execute([$contractId]);
        $contract=$stmt->fetch()?:throw new RuntimeException('Contrato não encontrado.');
        if(!in_array($contract['status'],['READY','SENT','VIEWED','SIGNED_BY_GUEST','SIGNED_BY_OWNER','FULLY_SIGNED'],true))throw new RuntimeException('O estado atual não permite gerar o PDF.');
        $directory=BASE_PATH.'/storage/contracts/'.$contractId;
        if(!is_dir($directory)&&!mkdir($directory,0750,true)&&!is_dir($directory))throw new RuntimeException('Não foi possível preparar o armazenamento do contrato.');
        $output=$directory.'/contract-'.$contract['version_no'].'.pdf';
        $payload=tempnam(sys_get_temp_dir(),'contract-pdf-');
        if($payload===false)throw new RuntimeException('Não foi possível preparar o PDF.');
        file_put_contents($payload,json_encode(['title'=>'Contrato '.$contract['contract_number'],'html'=>$contract['html_snapshot'],'document_hash'=>$contract['content_hash']],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        $command=[$this->pythonBinary,BASE_PATH.'/scripts/generate_contract_pdf.py','--input',$payload,'--output',$output];
        $descriptors=[1=>['pipe','w'],2=>['pipe','w']];
        $process=proc_open($command,$descriptors,$pipes,BASE_PATH);
        if(!is_resource($process)){@unlink($payload);throw new RuntimeException('O gerador de PDF não pôde ser iniciado.');}
        $stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);@unlink($payload);
        if($exit!==0||!is_file($output)||filesize($output)<1000){@unlink($output);throw new RuntimeException('Falha ao gerar PDF: '.mb_substr(trim((string)$stderr.(string)$stdout),0,1000));}
        $head=file_get_contents($output,false,null,0,5);if($head!=='%PDF-'){@unlink($output);throw new RuntimeException('O gerador produziu um arquivo inválido.');}
        $hash=hash_file('sha256',$output);$relative=substr(str_replace('\\','/',$output),strlen(str_replace('\\','/',BASE_PATH))+1);
        $this->db->beginTransaction();
        try{
            $this->db->prepare('UPDATE reservation_contracts SET pdf_path=?,pdf_hash=? WHERE id=?')->execute([$relative,$hash,$contractId]);
            $this->db->prepare("INSERT INTO contract_documents (contract_id,document_type,storage_path,mime_type,byte_size,sha256) VALUES (?,'UNSIGNED_PDF',?,'application/pdf',?,?) ON DUPLICATE KEY UPDATE storage_path=VALUES(storage_path),byte_size=VALUES(byte_size)")->execute([$contractId,$relative,filesize($output),$hash]);
            $this->db->prepare("INSERT INTO contract_events (contract_id,event_type,metadata_json,document_hash) VALUES (?,'PDF_GENERATED',?,?)")->execute([$contractId,json_encode(['path'=>$relative,'bytes'=>filesize($output)],JSON_THROW_ON_ERROR),$hash]);
            $this->db->commit();
        }catch(\Throwable $error){if($this->db->inTransaction())$this->db->rollBack();throw $error;}
        return ['path'=>$output,'relative_path'=>$relative,'sha256'=>$hash,'bytes'=>filesize($output)];
    }
}
