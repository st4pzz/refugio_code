-- Places API: remover a antiga persistencia OAuth e qualquer copia local do Google.
DELETE FROM avaliacoes WHERE origem_plataforma='GOOGLE';

DROP TABLE IF EXISTS review_integrations;
