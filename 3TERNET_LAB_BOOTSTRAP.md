# 3ternet Lab Bootstrap

Use this document to turn `164.92.220.248` into the first dedicated `3ternet` lab for O.

This machine is not production.
It is the transition space between:

- the live shore node on `161.35.157.37`
- the future pocket land on Raspberry Pi 5

## Mission

The lab droplet should let us test:

- a separate O. stack
- a fake pocket land before the real Pi
- `Signal` deferred delivery logic
- `aZa` live routing experiments
- `present / asleep / roaming` states

without touching the live domains.

## Target identity

- droplet IP: `164.92.220.248`
- role name: `o-3ternet-lab`
- VPS path: `/opt/o-3ternet-lab`
- compose project: `sowwwl-o-lab`

## Domains to attach

Start with:

- `lab.sowwwl.cloud`
- `api.lab.sowwwl.cloud`
- `pocket.lab.sowwwl.cloud`

Do not point `sowwwl.com` or `0wlslw0.com` here.

## What lives on this droplet

The lab stack has:

- `caddy`
- `app` = shore-lab O. app
- `pocket` = fake pocket land, same image but separate runtime volume
- `db` = MySQL lab image with embedded init SQL
- `api`

This lets us test:

- public lab behavior at `lab.sowwwl.cloud`
- fake pocket routing at `pocket.lab.sowwwl.cloud`
- direct container stop/start to simulate absence before the real Pi exists

## Step 1 — connect to the droplet

From your Mac:

```bash
ssh root@164.92.220.248
```

## Step 2 — install the base packages

Inside the DigitalOcean console or SSH session:

```bash
apt-get update
apt-get install -y git curl nano rsync docker-compose-plugin
```

If Docker is not installed yet:

```bash
curl -fsSL https://get.docker.com | sh
apt-get install -y docker-compose-plugin
```

## Step 3 — clone the repo cleanly

```bash
mkdir -p /opt
cd /opt
rm -rf /opt/o-3ternet-lab
git clone https://github.com/sowwwl-source/o.git /opt/o-3ternet-lab
cd /opt/o-3ternet-lab
git checkout main
git pull --ff-only origin main
```

## Step 4 — prepare the lab env file

```bash
cd /opt/o-3ternet-lab/deploy-lab
cp .env.lab.example .env.lab
nano .env.lab
```

Replace at least:

- `ACME_EMAIL`
- `DB_PASS`
- `DB_ROOT_PASSWORD`
- `AZA_API_TOKEN`
- `SOWWWL_ADMIN_PIN`
- `SOWWWL_MAGIC_LINK_SECRET`
- `SOWWWL_ADMIN_EMAIL`

Keep these values aligned with the lab domains:

- `SOWWWL_PUBLIC_ORIGIN=https://lab.sowwwl.cloud`
- `SOWWWL_AZA_DIRECT_ORIGIN=https://lab.sowwwl.cloud`
- `SOWWWL_POCKET_PUBLIC_ORIGIN=https://pocket.lab.sowwwl.cloud`

## Step 5 — point DNS before TLS

In Cloudflare, create:

- `A lab.sowwwl.cloud -> 164.92.220.248`
- `A api.lab.sowwwl.cloud -> 164.92.220.248`
- `A pocket.lab.sowwwl.cloud -> 164.92.220.248`

For the first issuance, use `DNS only` if needed.

## Step 6 — deploy the lab stack

```bash
cd /opt/o-3ternet-lab/deploy-lab
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d
```

The DB init files are built into the lab DB image from `deploy-lab/db/init/`.
Do not add bind mounts for `init.sql`; the lab should remain clone-and-build.

## Step 7 — verify the containers

```bash
cd /opt/o-3ternet-lab/deploy-lab
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml ps
docker logs --tail=80 sowwwl-o-lab-caddy-1
docker logs --tail=80 sowwwl-o-lab-app-1
docker logs --tail=80 sowwwl-o-lab-pocket-1
```

### If Docker or Caddy refuses to start

Use this before retrying the deploy:

```bash
cd /opt/o-3ternet-lab/deploy-lab

systemctl start docker || service docker start || snap start docker
docker version

ss -tlnp | grep -E ':80 |:443 ' || true
docker ps -a --format 'table {{.Names}}\t{{.Ports}}\t{{.Status}}'

docker rm -f sowwwl-o-lab-caddy-1 2>/dev/null || true
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml config >/tmp/sowwwl-o-lab.compose.yml
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d
```

If another container owns `80/443`, stop there and identify it first:

```bash
docker ps -a --format '{{.ID}} {{.Names}} {{.Ports}}' | grep -E '0.0.0.0:(80|443)|:::(80|443)'
```

Only remove containers that clearly belong to an abandoned lab/test stack.

## Step 8 — verify the public lab routes

Inside the droplet:

```bash
curl -I https://lab.sowwwl.cloud
curl -I https://lab.sowwwl.cloud/0wlslw0
curl -I https://lab.sowwwl.cloud/signal
curl -I https://pocket.lab.sowwwl.cloud
curl -I https://api.lab.sowwwl.cloud/healthz
```

## Step 9 — verify the app internals

```bash
docker exec sowwwl-o-lab-app-1 sh -lc 'ls -la /var/www/html/0wlslw0.php /var/www/html/signal.php /var/www/html/aza.php /var/www/html/echo.php'
docker exec sowwwl-o-lab-app-1 sh -lc 'printenv | grep -E "SOWWWL_(PUBLIC_ORIGIN|AZA_DIRECT_ORIGIN|ADMIN_PIN)"'
docker exec sowwwl-o-lab-pocket-1 sh -lc 'printenv | grep -E "SOWWWL_(PUBLIC_ORIGIN|POCKET_PUBLIC_ORIGIN)"'
```

## Step 10 — first presence simulation

Before the real Pi exists, the `pocket` container is the fake pocket land.

### Present

```bash
docker start sowwwl-o-lab-pocket-1
curl -I https://pocket.lab.sowwwl.cloud
```

### Asleep

```bash
docker stop sowwwl-o-lab-pocket-1
curl -I https://pocket.lab.sowwwl.cloud
```

### Present again

```bash
docker start sowwwl-o-lab-pocket-1
curl -I https://pocket.lab.sowwwl.cloud
```

This is crude, but useful.
It lets the infrastructure learn absence before the Pi is in the loop.

## Step 11 — keep the lab isolated

Never do these things on the lab:

- copy the production DB
- point `sowwwl.com` or `0wlslw0.com` here
- reuse the production `.env.production`
- attach real user archives

The lab must remain disposable.

## Step 12 — first successful milestone

The lab is considered real when all of this works:

1. `lab.sowwwl.cloud` serves the app
2. `pocket.lab.sowwwl.cloud` serves the fake pocket app
3. `api.lab.sowwwl.cloud/healthz` responds
4. the `pocket` container can be stopped and restarted independently
5. `Signal` and `aZa` can be exercised without touching production

## Step 13 — the first real 3ternet experiment

Once the lab stack is stable, the next move is not another deploy.

The next move is:

- add a tiny presence registry
- mark a land `present` or `asleep`
- make `0wlslw0` say the truth about that state
- make `Signal` queue an envelope when the target is absent

That is the first real `3ternet` behavior.

## Step 14 — later replacement by the Raspberry Pi

When the Pi is ready:

- the `pocket` container stops being the fake land
- the lab droplet becomes the relay / transition space
- the live shore node stays on `161.35.157.37`
- the Pi becomes the primary carried shard

At that point, `164.92.220.248` becomes a bridge, not a destination.
