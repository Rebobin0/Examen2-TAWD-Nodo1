-- =====================================================================
-- BANCO CENTRAL (Nodo 1) - Funciones para el panel Laravel
-- Ejecutar DESPUES de banco_central.sql. Es re-ejecutable.
--
-- Todas exigen ser administrador (es_admin) y se llaman con el JWT
-- del usuario que inicio sesion en el panel:
--   POST /rest/v1/rpc/<funcion>
--   apikey: <anon key>      Authorization: Bearer <access_token>
-- =====================================================================

-- Lista de sucursales y cajeros (sin el hash del API Key)
create or replace function public.admin_nodos()
returns jsonb
language plpgsql
stable
security definer
set search_path = public, pg_temp
as $$
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;

  return coalesce((
    select jsonb_agg(to_jsonb(f))
      from (
        select n.id, n.tipo, n.nombre, n.responsable, n.efectivo_disponible,
               n.estado, n.api_key_prefijo, n.created_at,
               (select count(*) from public.transactions t where t.nodo_id = n.id) as operaciones
          from public.nodes n
         order by n.tipo desc, n.created_at
      ) f
  ), '[]'::jsonb);
end;
$$;

-- Editar nombre, responsable, efectivo disponible y estado de un nodo
create or replace function public.admin_actualizar_nodo(
  p_nodo_id             uuid,
  p_nombre              text,
  p_responsable         text,
  p_efectivo_disponible numeric,
  p_estado              text
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;
  if coalesce(trim(p_nombre), '') = '' then
    raise exception 'NOMBRE_REQUERIDO' using errcode = 'PT400';
  end if;
  if p_efectivo_disponible is null or p_efectivo_disponible < 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;
  if coalesce(p_estado, '') not in ('activo', 'inactivo') then
    raise exception 'ESTADO_INVALIDO' using errcode = 'PT400';
  end if;

  update public.nodes
     set nombre = trim(p_nombre),
         responsable = nullif(trim(coalesce(p_responsable, '')), ''),
         efectivo_disponible = round(p_efectivo_disponible, 2),
         estado = p_estado
   where id = p_nodo_id
  returning * into v_nodo;

  if not found then
    raise exception 'NODO_NO_ENCONTRADO' using errcode = 'PT404';
  end if;

  return to_jsonb(v_nodo) - 'api_key_hash';
end;
$$;

-- Activar o desactivar un nodo sin tocar lo demas
create or replace function public.admin_cambiar_estado(p_nodo_id uuid, p_estado text)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;
  if coalesce(p_estado, '') not in ('activo', 'inactivo') then
    raise exception 'ESTADO_INVALIDO' using errcode = 'PT400';
  end if;

  update public.nodes set estado = p_estado where id = p_nodo_id
  returning * into v_nodo;

  if not found then
    raise exception 'NODO_NO_ENCONTRADO' using errcode = 'PT404';
  end if;

  return to_jsonb(v_nodo) - 'api_key_hash';
end;
$$;

-- Historial global con filtros y paginacion.
-- Devuelve { total, monto_total, filas: [...] }
create or replace function public.admin_historial(
  p_nodo_id uuid        default null,
  p_tipo    text        default null,
  p_cuenta  text        default null,
  p_desde   timestamptz default null,
  p_hasta   timestamptz default null,
  p_limite  int         default 50,
  p_offset  int         default 0
)
returns jsonb
language plpgsql
stable
security definer
set search_path = public, pg_temp
as $$
declare
  v_total bigint;
  v_monto numeric;
  v_filas jsonb;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;

  select count(*), coalesce(sum(t.monto), 0)
    into v_total, v_monto
    from public.transactions t
   where (p_nodo_id is null or t.nodo_id = p_nodo_id)
     and (p_tipo   is null or t.tipo = p_tipo)
     and (p_cuenta is null or p_cuenta in (t.cuenta_origen, t.cuenta_destino))
     and (p_desde  is null or t."timestamp" >= p_desde)
     and (p_hasta  is null or t."timestamp" <  p_hasta);

  select coalesce(jsonb_agg(to_jsonb(f) order by f."timestamp" desc, f.id desc), '[]'::jsonb)
    into v_filas
    from (
      select t.id, t."timestamp", t.tipo, t.monto, t.cuenta_origen, t.cuenta_destino,
             t.nodo_id, n.nombre as nodo_nombre, n.tipo as nodo_tipo
        from public.transactions t
        join public.nodes n on n.id = t.nodo_id
       where (p_nodo_id is null or t.nodo_id = p_nodo_id)
         and (p_tipo   is null or t.tipo = p_tipo)
         and (p_cuenta is null or p_cuenta in (t.cuenta_origen, t.cuenta_destino))
         and (p_desde  is null or t."timestamp" >= p_desde)
         and (p_hasta  is null or t."timestamp" <  p_hasta)
       order by t."timestamp" desc, t.id desc
       limit least(greatest(coalesce(p_limite, 50), 1), 5000)
      offset greatest(coalesce(p_offset, 0), 0)
    ) f;

  return jsonb_build_object('total', v_total, 'monto_total', v_monto, 'filas', v_filas);
end;
$$;

-- Reporte administrativo de operaciones en un periodo.
-- Devuelve { sistema, resumen, por_nodo: [...], por_dia: [...] }
create or replace function public.admin_reporte(
  p_desde timestamptz default null,
  p_hasta timestamptz default null,
  p_zona  text        default 'America/Mexico_City'
)
returns jsonb
language plpgsql
stable
security definer
set search_path = public, pg_temp
as $$
declare
  v_sistema  jsonb;
  v_resumen  jsonb;
  v_por_nodo jsonb;
  v_por_dia  jsonb;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;

  select jsonb_build_object(
           'sucursales',     (select count(*) from public.nodes where tipo = 'sucursal'),
           'cajeros',        (select count(*) from public.nodes where tipo = 'cajero'),
           'nodos_activos',  (select count(*) from public.nodes where estado = 'activo'),
           'efectivo_total', (select coalesce(sum(efectivo_disponible), 0) from public.nodes),
           'cuentas',        (select count(*) from public.users_accounts),
           'saldo_total',    (select coalesce(sum(saldo), 0) from public.users_accounts)
         )
    into v_sistema;

  select jsonb_build_object(
           'operaciones',          count(*),
           'monto_total',          coalesce(sum(t.monto), 0),
           'retiros',              count(*) filter (where t.tipo = 'retiro'),
           'monto_retiros',        coalesce(sum(t.monto) filter (where t.tipo = 'retiro'), 0),
           'depositos',            count(*) filter (where t.tipo = 'deposito'),
           'monto_depositos',      coalesce(sum(t.monto) filter (where t.tipo = 'deposito'), 0),
           'transferencias',       count(*) filter (where t.tipo = 'transferencia'),
           'monto_transferencias', coalesce(sum(t.monto) filter (where t.tipo = 'transferencia'), 0)
         )
    into v_resumen
    from public.transactions t
   where (p_desde is null or t."timestamp" >= p_desde)
     and (p_hasta is null or t."timestamp" <  p_hasta);

  select coalesce(jsonb_agg(to_jsonb(f) order by f.operaciones desc, f.nombre), '[]'::jsonb)
    into v_por_nodo
    from (
      select n.id as nodo_id, n.nombre, n.tipo, n.responsable, n.estado, n.efectivo_disponible,
             count(t.id)                                                        as operaciones,
             count(t.id) filter (where t.tipo = 'retiro')                       as retiros,
             coalesce(sum(t.monto) filter (where t.tipo = 'retiro'), 0)         as monto_retiros,
             count(t.id) filter (where t.tipo = 'deposito')                     as depositos,
             coalesce(sum(t.monto) filter (where t.tipo = 'deposito'), 0)       as monto_depositos,
             count(t.id) filter (where t.tipo = 'transferencia')                as transferencias,
             coalesce(sum(t.monto) filter (where t.tipo = 'transferencia'), 0)  as monto_transferencias
        from public.nodes n
        left join public.transactions t
               on t.nodo_id = n.id
              and (p_desde is null or t."timestamp" >= p_desde)
              and (p_hasta is null or t."timestamp" <  p_hasta)
       group by n.id
    ) f;

  select coalesce(jsonb_agg(to_jsonb(f) order by f.dia desc), '[]'::jsonb)
    into v_por_dia
    from (
      select (t."timestamp" at time zone p_zona)::date                          as dia,
             count(*)                                                           as operaciones,
             coalesce(sum(t.monto) filter (where t.tipo = 'retiro'), 0)         as monto_retiros,
             coalesce(sum(t.monto) filter (where t.tipo = 'deposito'), 0)       as monto_depositos,
             coalesce(sum(t.monto) filter (where t.tipo = 'transferencia'), 0)  as monto_transferencias
        from public.transactions t
       where (p_desde is null or t."timestamp" >= p_desde)
         and (p_hasta is null or t."timestamp" <  p_hasta)
       group by 1
    ) f;

  return jsonb_build_object(
    'sistema', v_sistema,
    'resumen', v_resumen,
    'por_nodo', v_por_nodo,
    'por_dia', v_por_dia
  );
end;
$$;

-- Permisos: solo usuarios autenticados (dentro se verifica es_admin)
revoke all on function public.admin_nodos()                                              from public, anon;
revoke all on function public.admin_actualizar_nodo(uuid, text, text, numeric, text)     from public, anon;
revoke all on function public.admin_cambiar_estado(uuid, text)                           from public, anon;
revoke all on function public.admin_historial(uuid, text, text, timestamptz, timestamptz, int, int) from public, anon;
revoke all on function public.admin_reporte(timestamptz, timestamptz, text)              from public, anon;

grant execute on function public.admin_nodos()                                           to authenticated, service_role;
grant execute on function public.admin_actualizar_nodo(uuid, text, text, numeric, text)  to authenticated, service_role;
grant execute on function public.admin_cambiar_estado(uuid, text)                        to authenticated, service_role;
grant execute on function public.admin_historial(uuid, text, text, timestamptz, timestamptz, int, int) to authenticated, service_role;
grant execute on function public.admin_reporte(timestamptz, timestamptz, text)           to authenticated, service_role;

-- Avisar a la API de Supabase que hay funciones nuevas
notify pgrst, 'reload schema';
