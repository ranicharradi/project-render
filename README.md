# php-render-app

Minimal PHP app ready to deploy on Render (via Docker).

## Local run

```bash
php -S 127.0.0.1:8080 -t public
```

Then open `http://127.0.0.1:8080/` and `http://127.0.0.1:8080/healthz`.

## Render

- Push this repo to GitHub
- In Render Dashboard: **New** → **Web Service** → connect the repo
- Choose **Environment: Docker**
- Deploy

