-- =============================================================
-- SETUP: reemplazar %%AUDIT_SCHEMA%% por el nombre del schema
-- de auditoría del proyecto antes de ejecutar.
-- Ejemplo: sed -i 's/%%AUDIT_SCHEMA%%/mi_proyecto_audit/g' funcion_activy_all_tables.sql
-- =============================================================
-- Función que obtiene la cantidad de Altas, Bajas y Modificaciones de cada tabla de auditoría
-- para un rango de fechas.
-- Se crea en el schema de auditoría (%%AUDIT_SCHEMA%%).

-- DROP FUNCTION IF EXISTS %%AUDIT_SCHEMA%%.activy_all_tables(date, date, text, text, int4);

CREATE OR REPLACE FUNCTION %%AUDIT_SCHEMA%%.activy_all_tables(
    in_fecha_desde date DEFAULT CURRENT_DATE,
    in_fecha_hasta date DEFAULT CURRENT_DATE,
    in_audit_schema text DEFAULT '%%AUDIT_SCHEMA%%'::text,
    in_usuario_schema text DEFAULT 'public'::text,
    in_sede integer DEFAULT 0
)
RETURNS TABLE(audit_table text, insert integer, update integer, delete integer)
LANGUAGE plpgsql
AS $function$
DECLARE
    dynamic_query text = '';
    r_row         record;
    filtro_sede   text = '';
    join_usuario  text = '';
BEGIN
    IF in_sede > 0 THEN
        filtro_sede = ' and u.sede_id = ''' || in_sede || '''';
        join_usuario = 'LEFT JOIN ' || in_usuario_schema || '.usuario u on at.audit_iduserapp = u.id';
    END IF;

    FOR r_row IN SELECT table_schema || '.' || table_name qualified_table_name
                 FROM information_schema.tables
                 WHERE table_schema = '' || in_audit_schema || ''
    LOOP
        dynamic_query := dynamic_query ||
            format('UNION SELECT ''' ||
                r_row.qualified_table_name || ''' as audit_table, ' ||
                '(SELECT count(*)::int FROM %s at ' || join_usuario || ' WHERE audit_action = ''I'' and audit_timestamp >= ''' || in_fecha_desde || ' 00:00'' and audit_timestamp <= ''' || in_fecha_hasta || ' 23:59''' || filtro_sede || ') as insert, ' ||
                '(SELECT count(*)::int FROM %s at ' || join_usuario || ' WHERE audit_action = ''U'' and audit_timestamp >= ''' || in_fecha_desde || ' 00:00'' and audit_timestamp <= ''' || in_fecha_hasta || ' 23:59''' || filtro_sede || ') as update, ' ||
                '(SELECT count(*)::int FROM %s at ' || join_usuario || ' WHERE audit_action = ''D'' and audit_timestamp >= ''' || in_fecha_desde || ' 00:00'' and audit_timestamp <= ''' || in_fecha_hasta || ' 23:59''' || filtro_sede || ') as delete'
            , r_row.qualified_table_name, r_row.qualified_table_name, r_row.qualified_table_name) || E'\n';
    END LOOP;

    dynamic_query := SUBSTRING(dynamic_query, 7) || 'ORDER BY 1;';

    RETURN QUERY EXECUTE dynamic_query;
END;
$function$;
