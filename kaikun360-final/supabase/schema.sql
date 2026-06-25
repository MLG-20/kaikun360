-- Kaikun 360 — schéma de départ Supabase/PostgreSQL
-- À faire relire par l'équipe technique, le juriste et le fiscaliste avant production.

create extension if not exists pgcrypto;

create type public.user_profile as enum ('client','proprietaire','prestataire','diaspora','entreprise','admin');
create type public.admin_role as enum ('super_admin','system_admin','business_manager','validation_agent','customer_agent','accountant','compliance','auditor');
create type public.offer_universe as enum ('immobilier','tourisme','transport','construction','gestion_locative','team_building','colonies','services');
create type public.offer_status as enum ('draft','kyc_pending','review_pending','published','suspended','archived','rejected');
create type public.reservation_status as enum ('quote','awaiting_payment','confirmed','service_in_progress','completed','cancelled','disputed');
create type public.payment_status as enum ('initiated','pending','confirmed','service_in_progress','eligible_for_payout','paid_out','failed','disputed','refunded','tax_reserved');
create type public.incident_priority as enum ('p1','p2','p3','p4');

create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  profile_type public.user_profile not null default 'client',
  full_name text not null,
  phone text,
  email text,
  city text,
  country text default 'Sénégal',
  is_active boolean not null default true,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.admin_memberships (
  user_id uuid primary key references public.profiles(id) on delete cascade,
  role public.admin_role not null,
  mfa_required boolean not null default true,
  last_access_review_at timestamptz,
  created_at timestamptz not null default now()
);

create table public.provider_kyc (
  id uuid primary key default gen_random_uuid(),
  profile_id uuid not null references public.profiles(id) on delete cascade,
  legal_name text not null,
  national_id_number text,
  rccm text,
  ninea text,
  payment_account_name text,
  payment_account_masked text,
  identity_verified boolean not null default false,
  business_verified boolean not null default false,
  insurance_verified boolean not null default false,
  payment_account_verified boolean not null default false,
  risk_level text not null default 'medium' check (risk_level in ('low','medium','high')),
  status text not null default 'pending' check (status in ('pending','approved','suspended','rejected')),
  approved_by uuid references public.profiles(id),
  approved_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.offers (
  id uuid primary key default gen_random_uuid(),
  reference text unique not null,
  owner_id uuid not null references public.profiles(id),
  universe public.offer_universe not null,
  title text not null,
  slug text unique not null,
  description text not null,
  city text not null,
  address_text text,
  latitude numeric(9,6),
  longitude numeric(9,6),
  price_xof bigint,
  price_unit text,
  capacity integer,
  verification_level text default 'unverified' check (verification_level in ('unverified','field_verified','document_verified','enhanced_verified')),
  status public.offer_status not null default 'draft',
  availability jsonb not null default '{}'::jsonb,
  attributes jsonb not null default '{}'::jsonb,
  approved_by uuid references public.profiles(id),
  approved_at timestamptz,
  published_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.offer_media (
  id uuid primary key default gen_random_uuid(),
  offer_id uuid not null references public.offers(id) on delete cascade,
  storage_path text not null,
  media_type text not null default 'image' check (media_type in ('image','video','document')),
  alt_text text,
  captured_at timestamptz,
  is_primary boolean not null default false,
  sort_order integer not null default 0,
  created_at timestamptz not null default now()
);

create table public.reservations (
  id uuid primary key default gen_random_uuid(),
  reference text unique not null,
  client_id uuid not null references public.profiles(id),
  offer_id uuid references public.offers(id),
  status public.reservation_status not null default 'quote',
  service_start_at timestamptz,
  service_end_at timestamptz,
  total_xof bigint not null default 0,
  commission_xof bigint not null default 0,
  tax_reserve_xof bigint not null default 0,
  provider_payable_xof bigint not null default 0,
  cancellation_terms text,
  service_proof jsonb not null default '{}'::jsonb,
  notes text,
  created_by uuid references public.profiles(id),
  confirmed_at timestamptz,
  completed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.payment_transactions (
  id uuid primary key default gen_random_uuid(),
  reference text unique not null,
  reservation_id uuid not null references public.reservations(id),
  psp_name text not null,
  psp_transaction_id text,
  method text not null,
  amount_xof bigint not null,
  currency char(3) not null default 'XOF',
  status public.payment_status not null default 'initiated',
  webhook_verified boolean not null default false,
  idempotency_key text unique not null,
  raw_psp_response jsonb,
  confirmed_at timestamptz,
  created_at timestamptz not null default now(),
  updated_at timestamptz not null default now()
);

create table public.ledger_entries (
  id uuid primary key default gen_random_uuid(),
  payment_id uuid not null references public.payment_transactions(id),
  reservation_id uuid not null references public.reservations(id),
  account_code text not null check (account_code in ('provider_payable','kaikun_revenue','tax_reserved','psp_fee','dispute_reserve','refund')),
  direction text not null check (direction in ('debit','credit')),
  amount_xof bigint not null check (amount_xof >= 0),
  is_spendable boolean not null default false,
  memo text,
  created_at timestamptz not null default now()
);

create table public.payouts (
  id uuid primary key default gen_random_uuid(),
  batch_reference text not null,
  payment_id uuid not null references public.payment_transactions(id),
  provider_id uuid not null references public.profiles(id),
  amount_xof bigint not null,
  destination_masked text not null,
  status text not null default 'draft' check (status in ('draft','prepared','approved','sent','succeeded','failed','reversed')),
  prepared_by uuid references public.profiles(id),
  approved_by uuid references public.profiles(id),
  prepared_at timestamptz,
  approved_at timestamptz,
  sent_at timestamptz,
  psp_payout_id text,
  created_at timestamptz not null default now(),
  constraint payout_double_control check (approved_by is null or prepared_by is distinct from approved_by)
);

create table public.incidents (
  id uuid primary key default gen_random_uuid(),
  reference text unique not null,
  priority public.incident_priority not null,
  title text not null,
  description text,
  reservation_id uuid references public.reservations(id),
  payment_id uuid references public.payment_transactions(id),
  assigned_to uuid references public.profiles(id),
  payout_frozen boolean not null default false,
  status text not null default 'open' check (status in ('open','investigating','waiting_party','resolved','closed')),
  resolution text,
  created_at timestamptz not null default now(),
  resolved_at timestamptz
);

create table public.audit_logs (
  id bigserial primary key,
  actor_id uuid references public.profiles(id),
  action text not null,
  entity_type text not null,
  entity_id text not null,
  before_data jsonb,
  after_data jsonb,
  ip_address inet,
  user_agent text,
  created_at timestamptz not null default now()
);

create index offers_universe_status_idx on public.offers(universe,status);
create index offers_city_idx on public.offers(city);
create index reservations_client_idx on public.reservations(client_id,created_at desc);
create index payment_status_idx on public.payment_transactions(status,created_at desc);
create index payout_status_idx on public.payouts(status,created_at desc);
create index incident_priority_idx on public.incidents(priority,status);

alter table public.profiles enable row level security;
alter table public.offers enable row level security;
alter table public.offer_media enable row level security;
alter table public.reservations enable row level security;
alter table public.payment_transactions enable row level security;
alter table public.provider_kyc enable row level security;

create policy "published offers are public" on public.offers for select using (status='published');
create policy "users read own profile" on public.profiles for select using (auth.uid()=id);
create policy "users update own profile" on public.profiles for update using (auth.uid()=id) with check (auth.uid()=id);
create policy "clients read own reservations" on public.reservations for select using (auth.uid()=client_id);
create policy "clients read own payments" on public.payment_transactions for select using (
  exists(select 1 from public.reservations r where r.id=reservation_id and r.client_id=auth.uid())
);
create policy "owners manage own draft offers" on public.offers for all using (auth.uid()=owner_id and status in ('draft','kyc_pending','review_pending')) with check (auth.uid()=owner_id);

-- Les opérations administratives sensibles doivent passer par des fonctions serveur
-- utilisant le service role, avec contrôle du rôle, MFA, audit et double validation.
