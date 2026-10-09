-- =====================================================================
-- BANCO CENTRAL (Nodo 1) - Supabase / PostgreSQL
-- Ejecutar completo en el SQL Editor de Supabase. Es re-ejecutable.
--
-- Contenido:
--   1. Tablas            4. Funciones internas
--   2. Inmutabilidad     5. RPC de administrador (panel Laravel)
--   3. RLS y permisos    6. RPC de nodos (sucursal / cajero)
--                        7. Permisos de ejecucion, Realtime, primer admin
--
-- Regla del sistema: nadie modifica saldos con UPDATE directo.
-- Todo movimiento pasa por las RPC de la seccion 6, que validan el API Key.
--
-- Errores: las RPC lanzan un codigo en "message" y PostgREST lo convierte
-- en estado HTTP (PT401 -> 401, PT403 -> 403, PT404 -> 404, PT409 -> 409).
-- =====================================================================


-- =====================================================================
-- 1. TABLAS
-- =====================================================================

-- Administradores del banco (usuarios de Supabase Auth con acceso al panel)
create table if not exists public.admins (
  user_id    uuid primary key references auth.users(id) on delete cascade,
  nombre     text not null default '',
  created_at timestamptz not null default now()
);

-- Nodos: sucursales y cajeros. Solo se guarda el hash del API Key.
create table if not exists public.nodes (
  id                  uuid primary key default gen_random_uuid(),
  tipo                text not null check (tipo in ('sucursal', 'cajero')),
  nombre              text not null,
  responsable         text,
  efectivo_disponible numeric(14,2) not null default 0 check (efectivo_disponible >= 0),
  estado              text not null default 'activo' check (estado in ('activo', 'inactivo')),
  api_key_hash        text not null unique,
  api_key_prefijo     text not null,
  created_at          timestamptz not null default now()
);

create table if not exists public.users_accounts (
  id               uuid primary key default gen_random_uuid(),
  numero_cuenta    text not null unique,
  nombre_titular   text not null,
  saldo            numeric(14,2) not null default 0 check (saldo >= 0),
  estado           text not null default 'activa' check (estado in ('activa', 'bloqueada')),
  nodo_apertura_id uuid references public.nodes(id),
  created_at       timestamptz not null default now()
);

create table if not exists public.transactions (
  id             bigint generated always as identity primary key,
  cuenta_origen  text references public.users_accounts(numero_cuenta),
  cuenta_destino text references public.users_accounts(numero_cuenta),
  monto          numeric(14,2) not null check (monto > 0),
  tipo           text not null check (tipo in ('retiro', 'deposito', 'transferencia')),
  nodo_id        uuid not null references public.nodes(id),
  "timestamp"    timestamptz not null default now(),
  constraint transactions_cuentas_por_tipo check (
       (tipo = 'retiro'        and cuenta_origen is not null and cuenta_destino is null)
    or (tipo = 'deposito'      and cuenta_origen is null     and cuenta_destino is not null)
    or (tipo = 'transferencia' and cuenta_origen is not null and cuenta_destino is not null
                               and cuenta_origen <> cuenta_destino)
  )
);

create index if not exists transactions_nodo_idx    on public.transactions (nodo_id, "timestamp" desc);
create index if not exists transactions_origen_idx  on public.transactions (cuenta_origen);
create index if not exists transactions_destino_idx on public.transactions (cuenta_destino);
create index if not exists users_accounts_nodo_idx  on public.users_accounts (nodo_apertura_id);


-- =====================================================================
-- 2. INMUTABILIDAD DE TRANSACCIONES
--    Ni service_role ni postgres pueden editar o borrar un registro.
-- =====================================================================

create or replace function public._transactions_inmutable()
returns trigger
language plpgsql
as $$
begin
  raise exception 'TRANSACCION_INMUTABLE' using errcode = 'PT403';
end;
$$;

drop trigger if exists transactions_no_update_delete on public.transactions;
create trigger transactions_no_update_delete
  before update or delete on public.transactions
  for each row execute function public._transactions_inmutable();

drop trigger if exists transactions_no_truncate on public.transactions;
create trigger transactions_no_truncate
  before truncate on public.transactions
  for each statement execute function public._transactions_inmutable();


-- =====================================================================
-- 3. ROW LEVEL SECURITY Y PERMISOS DE TABLA
--    anon: sin acceso a tablas (los nodos solo usan RPC).
--    authenticated: lectura si es admin; escritura limitada por columna.
-- =====================================================================

-- Es admin: usuario de Auth registrado en admins, service_role,
-- o conexion directa a Postgres (SQL Editor / Laravel con DB_CONNECTION=pgsql).
create or replace function public.es_admin()
returns boolean
language sql
stable
security definer
set search_path = public, pg_temp
as $$
  select session_user = 'postgres'
      or coalesce(auth.jwt() ->> 'role', '') = 'service_role'
      or exists (select 1 from public.admins a where a.user_id = auth.uid());
$$;

alter table public.admins         enable row level security;
alter table public.nodes          enable row level security;
alter table public.users_accounts enable row level security;
alter table public.transactions   enable row level security;

revoke all on public.admins, public.nodes, public.users_accounts, public.transactions
  from anon, authenticated;

grant select on public.admins, public.nodes, public.users_accounts, public.transactions
  to authenticated;

-- El admin puede editar datos del nodo, pero no su API Key
grant update (nombre, responsable, efectivo_disponible, estado) on public.nodes to authenticated;
-- El admin puede bloquear una cuenta, pero no tocar el saldo
grant update (nombre_titular, estado) on public.users_accounts to authenticated;

revoke update, delete, truncate on public.transactions from service_role;

drop policy if exists admins_select on public.admins;
create policy admins_select on public.admins
  for select to authenticated using (public.es_admin());

drop policy if exists nodes_select on public.nodes;
create policy nodes_select on public.nodes
  for select to authenticated using (public.es_admin());

drop policy if exists nodes_update on public.nodes;
create policy nodes_update on public.nodes
  for update to authenticated using (public.es_admin()) with check (public.es_admin());

drop policy if exists users_accounts_select on public.users_accounts;
create policy users_accounts_select on public.users_accounts
  for select to authenticated using (public.es_admin());

drop policy if exists users_accounts_update on public.users_accounts;
create policy users_accounts_update on public.users_accounts
  for update to authenticated using (public.es_admin()) with check (public.es_admin());

drop policy if exists transactions_select on public.transactions;
create policy transactions_select on public.transactions
  for select to authenticated using (public.es_admin());
-- Sin politicas de INSERT/DELETE: esas operaciones solo ocurren dentro de las RPC.


-- =====================================================================
-- 4. FUNCIONES INTERNAS (no expuestas en la API)
-- =====================================================================

create or replace function public._hash_api_key(p_api_key text)
returns text
language sql
immutable
as $$
  select encode(sha256(convert_to(p_api_key, 'UTF8')), 'hex');
$$;

create or replace function public._generar_api_key(p_tipo text)
returns text
language sql
volatile
as $$
  select 'bk_' || case when p_tipo = 'sucursal' then 'suc' else 'atm' end || '_'
      || replace(gen_random_uuid()::text, '-', '')
      || replace(gen_random_uuid()::text, '-', '');
$$;

-- Devuelve el nodo dueno del API Key o lanza error.
-- p_tipo: si se indica, exige que el nodo sea de ese tipo.
create or replace function public._validar_nodo(p_api_key text, p_tipo text default null)
returns public.nodes
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  if p_api_key is null or p_api_key = '' then
    raise exception 'API_KEY_INVALIDA' using errcode = 'PT401';
  end if;

  select * into v_nodo
    from public.nodes
   where api_key_hash = public._hash_api_key(p_api_key);

  if not found then
    raise exception 'API_KEY_INVALIDA' using errcode = 'PT401';
  end if;
  if v_nodo.estado <> 'activo' then
    raise exception 'NODO_INACTIVO' using errcode = 'PT403';
  end if;
  if p_tipo is not null and v_nodo.tipo <> p_tipo then
    raise exception 'OPERACION_NO_PERMITIDA_PARA_ESTE_NODO' using errcode = 'PT403';
  end if;

  return v_nodo;
end;
$$;

-- Bloquea la cuenta (FOR UPDATE) y valida que exista y este activa.
create or replace function public._bloquear_cuenta(p_numero_cuenta text)
returns public.users_accounts
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_cuenta public.users_accounts;
begin
  select * into v_cuenta
    from public.users_accounts
   where numero_cuenta = p_numero_cuenta
     for update;

  if not found then
    raise exception 'CUENTA_NO_ENCONTRADA' using errcode = 'PT404';
  end if;
  if v_cuenta.estado <> 'activa' then
    raise exception 'CUENTA_BLOQUEADA' using errcode = 'PT403';
  end if;

  return v_cuenta;
end;
$$;


-- =====================================================================
-- 5. RPC DE ADMINISTRADOR (panel Laravel del Banco Central)
-- =====================================================================

-- Crea una sucursal o cajero. Devuelve el API Key en claro UNA sola vez.
create or replace function public.admin_crear_nodo(
  p_tipo             text,
  p_nombre           text,
  p_responsable      text default null,
  p_efectivo_inicial numeric default 0
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_key  text;
  v_nodo public.nodes;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;
  if coalesce(p_tipo, '') not in ('sucursal', 'cajero') then
    raise exception 'TIPO_INVALIDO' using errcode = 'PT400';
  end if;
  if coalesce(trim(p_nombre), '') = '' then
    raise exception 'NOMBRE_REQUERIDO' using errcode = 'PT400';
  end if;
  if coalesce(p_efectivo_inicial, 0) < 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;

  v_key := public._generar_api_key(p_tipo);

  insert into public.nodes (tipo, nombre, responsable, efectivo_disponible, api_key_hash, api_key_prefijo)
  values (p_tipo, trim(p_nombre), p_responsable, round(coalesce(p_efectivo_inicial, 0), 2),
          public._hash_api_key(v_key), left(v_key, 12))
  returning * into v_nodo;

  return jsonb_build_object(
    'id', v_nodo.id,
    'tipo', v_nodo.tipo,
    'nombre', v_nodo.nombre,
    'responsable', v_nodo.responsable,
    'efectivo_disponible', v_nodo.efectivo_disponible,
    'api_key', v_key
  );
end;
$$;

-- Invalida el API Key anterior y devuelve uno nuevo.
create or replace function public.admin_regenerar_api_key(p_nodo_id uuid)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_key  text;
  v_tipo text;
begin
  if not public.es_admin() then
    raise exception 'NO_AUTORIZADO' using errcode = 'PT403';
  end if;

  select tipo into v_tipo from public.nodes where id = p_nodo_id;
  if not found then
    raise exception 'NODO_NO_ENCONTRADO' using errcode = 'PT404';
  end if;

  v_key := public._generar_api_key(v_tipo);

  update public.nodes
     set api_key_hash = public._hash_api_key(v_key),
         api_key_prefijo = left(v_key, 12)
   where id = p_nodo_id;

  return jsonb_build_object('id', p_nodo_id, 'api_key', v_key);
end;
$$;


-- =====================================================================
-- 6. RPC DE NODOS (sucursal y cajero). Todas reciben p_api_key.
-- =====================================================================

-- Prueba de conexion: datos del nodo dueno del API Key.
create or replace function public.nodo_info(p_api_key text)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  v_nodo := public._validar_nodo(p_api_key);
  return jsonb_build_object(
    'id', v_nodo.id,
    'tipo', v_nodo.tipo,
    'nombre', v_nodo.nombre,
    'responsable', v_nodo.responsable,
    'efectivo_disponible', v_nodo.efectivo_disponible,
    'estado', v_nodo.estado
  );
end;
$$;

-- Solo sucursales. El saldo inicial entra como deposito en efectivo a la sucursal.
create or replace function public.crear_cuenta(
  p_api_key        text,
  p_nombre_titular text,
  p_saldo_inicial  numeric default 0
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo   public.nodes;
  v_numero text;
  v_monto  numeric(14,2) := round(coalesce(p_saldo_inicial, 0), 2);
begin
  v_nodo := public._validar_nodo(p_api_key, 'sucursal');

  if coalesce(trim(p_nombre_titular), '') = '' then
    raise exception 'NOMBRE_REQUERIDO' using errcode = 'PT400';
  end if;
  if v_monto < 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;

  loop
    v_numero := lpad(floor(random() * 10000000000)::bigint::text, 10, '0');
    exit when not exists (select 1 from public.users_accounts where numero_cuenta = v_numero);
  end loop;

  insert into public.users_accounts (numero_cuenta, nombre_titular, saldo, nodo_apertura_id)
  values (v_numero, trim(p_nombre_titular), v_monto, v_nodo.id);

  if v_monto > 0 then
    update public.nodes
       set efectivo_disponible = efectivo_disponible + v_monto
     where id = v_nodo.id;

    insert into public.transactions (cuenta_destino, monto, tipo, nodo_id)
    values (v_numero, v_monto, 'deposito', v_nodo.id);
  end if;

  return jsonb_build_object(
    'numero_cuenta', v_numero,
    'nombre_titular', trim(p_nombre_titular),
    'saldo', v_monto,
    'estado', 'activa'
  );
end;
$$;

create or replace function public.consultar_saldo(p_api_key text, p_numero_cuenta text)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_cuenta public.users_accounts;
begin
  perform public._validar_nodo(p_api_key);

  select * into v_cuenta from public.users_accounts where numero_cuenta = p_numero_cuenta;
  if not found then
    raise exception 'CUENTA_NO_ENCONTRADA' using errcode = 'PT404';
  end if;

  return jsonb_build_object(
    'numero_cuenta', v_cuenta.numero_cuenta,
    'nombre_titular', v_cuenta.nombre_titular,
    'saldo', v_cuenta.saldo,
    'estado', v_cuenta.estado
  );
end;
$$;

-- Retiro: exige saldo en la cuenta Y efectivo en el nodo. Todo o nada.
create or replace function public.retirar(p_api_key text, p_numero_cuenta text, p_monto numeric)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo   public.nodes;
  v_cuenta public.users_accounts;
  v_monto  numeric(14,2) := round(p_monto, 2);
  v_tx     bigint;
  v_saldo  numeric(14,2);
  v_efect  numeric(14,2);
begin
  v_nodo := public._validar_nodo(p_api_key);

  if v_monto is null or v_monto <= 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;

  v_cuenta := public._bloquear_cuenta(p_numero_cuenta);

  if v_cuenta.saldo < v_monto then
    raise exception 'FONDOS_INSUFICIENTES' using errcode = 'PT409';
  end if;

  update public.nodes
     set efectivo_disponible = efectivo_disponible - v_monto
   where id = v_nodo.id and efectivo_disponible >= v_monto
  returning efectivo_disponible into v_efect;
  if not found then
    raise exception 'EFECTIVO_INSUFICIENTE_EN_NODO' using errcode = 'PT409';
  end if;

  update public.users_accounts
     set saldo = saldo - v_monto
   where id = v_cuenta.id
  returning saldo into v_saldo;

  insert into public.transactions (cuenta_origen, monto, tipo, nodo_id)
  values (p_numero_cuenta, v_monto, 'retiro', v_nodo.id)
  returning id into v_tx;

  return jsonb_build_object(
    'transaccion_id', v_tx,
    'tipo', 'retiro',
    'numero_cuenta', p_numero_cuenta,
    'monto', v_monto,
    'saldo', v_saldo,
    'efectivo_nodo', v_efect
  );
end;
$$;

create or replace function public.depositar(p_api_key text, p_numero_cuenta text, p_monto numeric)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo   public.nodes;
  v_cuenta public.users_accounts;
  v_monto  numeric(14,2) := round(p_monto, 2);
  v_tx     bigint;
  v_saldo  numeric(14,2);
  v_efect  numeric(14,2);
begin
  v_nodo := public._validar_nodo(p_api_key);

  if v_monto is null or v_monto <= 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;

  v_cuenta := public._bloquear_cuenta(p_numero_cuenta);

  update public.nodes
     set efectivo_disponible = efectivo_disponible + v_monto
   where id = v_nodo.id
  returning efectivo_disponible into v_efect;

  update public.users_accounts
     set saldo = saldo + v_monto
   where id = v_cuenta.id
  returning saldo into v_saldo;

  insert into public.transactions (cuenta_destino, monto, tipo, nodo_id)
  values (p_numero_cuenta, v_monto, 'deposito', v_nodo.id)
  returning id into v_tx;

  return jsonb_build_object(
    'transaccion_id', v_tx,
    'tipo', 'deposito',
    'numero_cuenta', p_numero_cuenta,
    'monto', v_monto,
    'saldo', v_saldo,
    'efectivo_nodo', v_efect
  );
end;
$$;

-- Transferencia entre cuentas: no mueve efectivo del nodo.
create or replace function public.transferir(
  p_api_key        text,
  p_cuenta_origen  text,
  p_cuenta_destino text,
  p_monto          numeric
)
returns jsonb
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo    public.nodes;
  v_origen  public.users_accounts;
  v_destino public.users_accounts;
  v_monto   numeric(14,2) := round(p_monto, 2);
  v_tx      bigint;
  v_saldo   numeric(14,2);
begin
  v_nodo := public._validar_nodo(p_api_key);

  if v_monto is null or v_monto <= 0 then
    raise exception 'MONTO_INVALIDO' using errcode = 'PT400';
  end if;
  if p_cuenta_origen is null or p_cuenta_destino is null or p_cuenta_origen = p_cuenta_destino then
    raise exception 'CUENTAS_INVALIDAS' using errcode = 'PT400';
  end if;

  -- Bloqueo en orden fijo para evitar deadlocks entre transferencias cruzadas
  if p_cuenta_origen < p_cuenta_destino then
    v_origen  := public._bloquear_cuenta(p_cuenta_origen);
    v_destino := public._bloquear_cuenta(p_cuenta_destino);
  else
    v_destino := public._bloquear_cuenta(p_cuenta_destino);
    v_origen  := public._bloquear_cuenta(p_cuenta_origen);
  end if;

  if v_origen.saldo < v_monto then
    raise exception 'FONDOS_INSUFICIENTES' using errcode = 'PT409';
  end if;

  update public.users_accounts set saldo = saldo - v_monto where id = v_origen.id
  returning saldo into v_saldo;
  update public.users_accounts set saldo = saldo + v_monto where id = v_destino.id;

  insert into public.transactions (cuenta_origen, cuenta_destino, monto, tipo, nodo_id)
  values (p_cuenta_origen, p_cuenta_destino, v_monto, 'transferencia', v_nodo.id)
  returning id into v_tx;

  return jsonb_build_object(
    'transaccion_id', v_tx,
    'tipo', 'transferencia',
    'cuenta_origen', p_cuenta_origen,
    'cuenta_destino', p_cuenta_destino,
    'monto', v_monto,
    'saldo_origen', v_saldo
  );
end;
$$;

-- Historial. Sin p_numero_cuenta: operaciones hechas en este nodo.
-- Con p_numero_cuenta: una sucursal ve todos los movimientos de la cuenta;
-- un cajero solo los que se hicieron en el mismo cajero.
create or replace function public.historial(
  p_api_key       text,
  p_numero_cuenta text default null,
  p_limite        int  default 100
)
returns setof public.transactions
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  v_nodo := public._validar_nodo(p_api_key);

  return query
    select t.*
      from public.transactions t
     where (p_numero_cuenta is null and t.nodo_id = v_nodo.id)
        or (p_numero_cuenta is not null
            and p_numero_cuenta in (t.cuenta_origen, t.cuenta_destino)
            and (v_nodo.tipo = 'sucursal' or t.nodo_id = v_nodo.id))
     order by t."timestamp" desc, t.id desc
     limit least(greatest(coalesce(p_limite, 100), 1), 1000);
end;
$$;

-- Solo sucursales: cuentas abiertas en la sucursal.
create or replace function public.cuentas_de_sucursal(p_api_key text)
returns setof public.users_accounts
language plpgsql
security definer
set search_path = public, pg_temp
as $$
declare
  v_nodo public.nodes;
begin
  v_nodo := public._validar_nodo(p_api_key, 'sucursal');

  return query
    select c.*
      from public.users_accounts c
     where c.nodo_apertura_id = v_nodo.id
     order by c.created_at desc;
end;
$$;


-- =====================================================================
-- 7. PERMISOS DE EJECUCION, REALTIME Y PRIMER ADMIN
-- =====================================================================

-- Internas: nadie las llama desde la API
revoke all on function public._transactions_inmutable()      from public, anon, authenticated;
revoke all on function public._hash_api_key(text)            from public, anon, authenticated;
revoke all on function public._generar_api_key(text)         from public, anon, authenticated;
revoke all on function public._validar_nodo(text, text)      from public, anon, authenticated;
revoke all on function public._bloquear_cuenta(text)         from public, anon, authenticated;

-- Admin: solo usuarios autenticados (y dentro se verifica es_admin)
revoke all on function public.admin_crear_nodo(text, text, text, numeric) from public, anon;
revoke all on function public.admin_regenerar_api_key(uuid)               from public, anon;
grant execute on function public.admin_crear_nodo(text, text, text, numeric) to authenticated, service_role;
grant execute on function public.admin_regenerar_api_key(uuid)               to authenticated, service_role;
grant execute on function public.es_admin()                                  to authenticated, service_role;

-- Nodos: se llaman con la anon key de Supabase + el API Key del nodo
grant execute on function public.nodo_info(text)                         to anon, authenticated, service_role;
grant execute on function public.crear_cuenta(text, text, numeric)       to anon, authenticated, service_role;
grant execute on function public.consultar_saldo(text, text)             to anon, authenticated, service_role;
grant execute on function public.retirar(text, text, numeric)            to anon, authenticated, service_role;
grant execute on function public.depositar(text, text, numeric)          to anon, authenticated, service_role;
grant execute on function public.transferir(text, text, text, numeric)   to anon, authenticated, service_role;
grant execute on function public.historial(text, text, int)              to anon, authenticated, service_role;
grant execute on function public.cuentas_de_sucursal(text)               to anon, authenticated, service_role;

-- Realtime: el panel del banco puede ver transacciones en vivo
do $$
begin
  alter publication supabase_realtime add table public.transactions;
exception
  when duplicate_object then null;
  when undefined_object then null;
end;
$$;

-- Primer administrador:
--   1) Crear el usuario en Authentication > Users (correo y contrasena).
--   2) Ejecutar, cambiando el correo:
--
-- insert into public.admins (user_id, nombre)
-- select id, 'Admin Banco Central' from auth.users where email = 'admin@banco.test';
