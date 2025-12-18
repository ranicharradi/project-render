# php-render-app

Minimal PHP app ready to deploy on Render (via Docker).

## Local run

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open:

- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/healthz`
- `http://127.0.0.1:8080/api/time`
- `http://127.0.0.1:8080/request`

## Render

- Push this repo to GitHub
- In Render Dashboard: **New** → **Web Service** → connect the repo
- Choose **Environment: Docker**
- Deploy
