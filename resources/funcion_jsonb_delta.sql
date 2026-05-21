-- =============================================================
-- SETUP: reemplazar %%AUDIT_SCHEMA%% por el nombre del schema
-- de auditoría del proyecto antes de ejecutar.
-- Ejemplo: sed -i 's/%%AUDIT_SCHEMA%%/mi_proyecto_audit/g' funcion_jsonb_delta.sql
-- =============================================================
-- DROP FUNCTION %%AUDIT_SCHEMA%%.jsonb_delta(in jsonb, in jsonb, out jsonb);

CREATE OR REPLACE FUNCTION %%AUDIT_SCHEMA%%.jsonb_delta(json_left jsonb, json_right jsonb, OUT json_out jsonb)
 RETURNS jsonb
 LANGUAGE plpgsql
 IMMUTABLE PARALLEL SAFE
AS $function$
BEGIN

--IF json_left IS NULL OR json_right IS NULL THEN
--    RAISE EXCEPTION 'Non-null inputs required';
--END IF
--;

WITH
    base as
(
SELECT
    key
,   CASE
        WHEN a.value IS DISTINCT FROM b.value THEN jsonb_build_object('left', a.value, 'right', b.value)
        ELSE NULL
    END as changes
FROM jsonb_each_text(json_left) a
FULL OUTER JOIN jsonb_each_text(json_right) b using (key)
)
SELECT
    jsonb_object_agg(key,changes)
INTO json_out
FROM base
WHERE
    changes IS NOT NULL
;

json_out := coalesce(json_out, '{}');

END;
$function$
;
