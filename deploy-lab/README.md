# O. 3ternet lab stack

This directory deploys a dedicated lab stack for `164.92.220.248`.

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

## Deploy

```bash
cp .env.lab.example .env.lab
docker compose -p sowwwl-o-lab --env-file .env.lab -f docker-compose.lab.yml up --build -d
```

## Simulate a sleeping land

```bash
docker stop sowwwl-o-lab-pocket-1
```

Wake it again:

```bash
docker start sowwwl-o-lab-pocket-1
```

## See also

- `../3TERNET_ARCHITECTURE.md`
- `../3TERNET_LAB_BOOTSTRAP.md`
