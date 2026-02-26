# Railway deployment instructions for GoSort_Web

## Prerequisites
- PHP 8.x supported by Railway
- All dependencies included in the repo (no Composer detected)

## Deployment Steps
1. Push this repo to Railway (GitHub or CLI)
2. Railway will use the `Procfile` to start the PHP server:
   
   ```
   web: php -S 0.0.0.0:${PORT} -t .
   ```
3. Set any required environment variables in Railway dashboard (e.g., DB credentials)
4. Ensure your database is accessible from Railway

## Notes
- Remove or secure any dev/test files before deploying
- Update `.gitignore` as needed for production
- For static assets, ensure they are in the correct folders
