# O. 3ternet lab stack

This directory deploys a dedicated lab stack for `164.92.220.248`.

Preferred checkout path on the droplet is `/opt/o-3ternet-lab`.
If `/opt` is mounted read-only on the chosen image, use `/root/o-3ternet-lab` instead.

It exists to test:

- a lab copy of O.
- a fake pocket land before the real Raspberry Pi
- presence simulation
- deferred delivery behavior
- routed archive access

It should not replace the live shore node.

## Domains

Use only lab domains:

- `lab.sowwwl.cloud`
- `api.lab.sowwwl.cloud`
- `pocket.lab.sowwwl.cloud`

## Services

- `caddy`
- `app` = shore-lab app
- `pocket` = fake pocket land
- `db`
- `api`

The `pocket` service is intentionally a stand-in.
Later it should be replaced by a Pi-backed route or tunnel target.

## Files

- `docker-compose.lab.yml`
- `Caddyfile`
- `.env.lab.example`
- `db/Dockerfile`
- `db/init/*.sql`

The lab DB image embeds its init SQL files.
This avoids fragile bind mounts to `/opt/o-3ternet-lab/init.sql` on the droplet.

## Deploy

```bash
cp .env.lab.example .env.lab
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d
```

For subsequent updates on the lab droplet:

```bash
cd /opt/o-3ternet-lab
bash scripts/deploy_lab_update.sh
```

If the lab checkout lives under `/root`:

```bash
cd /root/o-3ternet-lab
bash scripts/deploy_lab_update.sh --root /root/o-3ternet-lab
```

## Recovery checklist

Use this on the DigitalOcean console when the lab is half-up, when Caddy cannot bind `80/443`, or when Docker says it cannot reach `/var/run/docker.sock`.

```bash
cd /opt/o-3ternet-lab/deploy-lab

echo "=== docker daemon ==="
systemctl status docker --no-pager || true
systemctl start docker || service docker start || snap start docker
docker version

echo "=== ports 80/443 ==="
ss -tlnp | grep -E ':80 |:443 ' || true
docker ps -a --format 'table {{.Names}}\t{{.Ports}}\t{{.Status}}'

echo "=== stop old lab caddy only ==="
docker rm -f sowwwl-o-lab-caddy-1 2>/dev/null || true

echo "=== compose config ==="
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml config >/tmp/sowwwl-o-lab.compose.yml

echo "=== deploy lab ==="
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml ps
```

If `80/443` are still occupied by a non-lab container, identify it before removing it:

```bash
docker ps -a --format '{{.ID}} {{.Names}} {{.Ports}}' | grep -E '0.0.0.0:(80|443)|:::(80|443)'
```

Do not remove a container unless it clearly belongs to the disposable lab or an abandoned test stack.

## Verify

```bash
curl -I https://lab.sowwwl.cloud
curl -I https://lab.sowwwl.cloud/0wlslw0
curl -I https://lab.sowwwl.cloud/aza
curl -I 'https://lab.sowwwl.cloud/island?u=<slug-lab-connu>'
curl -I 'https://lab.sowwwl.cloud/island.php?u=<slug-lab-connu>'
curl -I https://pocket.lab.sowwwl.cloud
curl -I https://api.lab.sowwwl.cloud/healthz
```

Expected island behavior:

- `https://lab.sowwwl.cloud/island?u=<slug-lab-connu>` returns `200`
- `https://lab.sowwwl.cloud/island.php?u=<slug-lab-connu>` redirects to the canonical `/island` route

Reference QA slug for multimaterial reader checks:

- `https://lab.sowwwl.cloud/island?u=qa-multimatiere`
- expected seeded traces:
  - `Journal de rive` (`txt` → texte)
  - `Constellation des formats` (`json` → data)
  - `Wireframe source` (`fig` → design)
  - `Triangle témoin` (`gltf` → 3d)
- expected diagnostics visible in the final HTML:
  - `prévisualisation textuelle`
  - `aperçu brut`
  - `aperçu textuel`
  - `viewer 3d natif`

## Simulate a sleeping land

```bash
docker stop sowwwl-o-lab-pocket-1
```

Wake it again:

```bash
docker start sowwwl-o-lab-pocket-1
```

## Raspberry Pi sensor ingest

The lab app exposes a token-protected endpoint for the first 3ternet physical signals:

- `POST https://lab.sowwwl.cloud/ingest/sensor`
- `Authorization: Bearer $SOWWWL_PI_TOKEN`
- JSON body with at least `event`

Set a long random `SOWWWL_PI_TOKEN` in `.env.lab`, then rebuild the app:

```bash
cd /opt/o-3ternet-lab/deploy-lab
openssl rand -hex 32
nano .env.lab
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d app pocket
```

Smoke-test from the droplet:

```bash
source .env.lab
curl -sS -X POST https://lab.sowwwl.cloud/ingest/sensor \
  -H "Authorization: Bearer $SOWWWL_PI_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"event":"lab_ping","camera":"console","message":"hello plasma"}'

docker exec sowwwl-o-lab-app-1 sh -lc 'tail -n 5 /var/www/runtime/plasma/sensor-events.jsonl'
```

Run the Pi daemon:

```bash
export SOWWWL_PI_ENDPOINT=https://lab.sowwwl.cloud/ingest/sensor
export SOWWWL_PI_TOKEN=replace-with-lab-token
export SOWWWL_PI_LAND_SLUG=lab-pocket
export SOWWWL_PI_CAMERAS=0,1
python3 scripts/sowwwl-pi.py
```

## See also

- `../3TERNET_ARCHITECTURE.md`
- `../3TERNET_LAB_BOOTSTRAP.md`
