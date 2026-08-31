-- Recupera imagens e audios antigos cujo webhook foi salvo como DESCONHECIDA.
SET NAMES utf8mb4;

UPDATE mensagens
SET tipo = CASE
        WHEN JSON_TYPE(JSON_EXTRACT(payload_json,'$.image')) = 'OBJECT' THEN 'IMAGEM'
        WHEN JSON_TYPE(JSON_EXTRACT(payload_json,'$.audio')) = 'OBJECT' THEN 'AUDIO'
        ELSE tipo
    END,
    media_id = COALESCE(
        media_id,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.image.id')),
        JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.audio.id'))
    ),
    media_mime = COALESCE(
        media_mime,
        JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.image.mime_type')),
        JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.audio.mime_type'))
    ),
    texto = COALESCE(texto, JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.image.caption')))
WHERE tipo = 'DESCONHECIDA'
  AND (
      JSON_TYPE(JSON_EXTRACT(payload_json,'$.image')) = 'OBJECT'
      OR JSON_TYPE(JSON_EXTRACT(payload_json,'$.audio')) = 'OBJECT'
  );

INSERT IGNORE INTO jobs (tipo,payload_json,chave_unica,prioridade,max_tentativas)
SELECT 'WHATSAPP_MEDIA',JSON_OBJECT('message_id',m.id),CONCAT('whatsapp-media:',m.external_message_id),30,6
FROM mensagens m
WHERE m.tipo IN ('IMAGEM','AUDIO')
  AND m.media_id IS NOT NULL
  AND m.media_path IS NULL;
