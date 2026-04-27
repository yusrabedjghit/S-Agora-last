# Railway Quick Start Guide

## 1. Prepare Your Repository

Make sure your backend code is in a Git repository:

```bash
cd backend
git init
git add .
git commit -m "Initial commit: Swapie Backend API"
```

## 2. Create Railway Project

1. Go to [railway.app](https://railway.app)
2. Sign in or create an account
3. Click **New Project**
4. Select **Deploy from GitHub**
5. Connect your GitHub account
6. Select your repository
7. Railway will auto-detect it's a PHP project

## 3. Add MySQL Database

Once your project is created:

1. Click **+ New** in your project
2. Select **MySQL**
3. Railway will provision a MySQL database
4. Go to the **Variables** tab to see connection details

## 4. Set Environment Variables on Railway

In your Railway project dashboard:

1. Click on the **Web Service** (your PHP app)
2. Go to **Variables** tab
3. Add these environment variables:

```
DB_HOST=mysql.railway.internal
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASSWORD=<copy from MySQL plugin variables>

JWT_SECRET=<generate a random 32+ character string>
ALLOWED_ORIGINS=https://your-frontend-domain.com,http://localhost:5173

APP_ENV=production
APP_DEBUG=false
```

**To find MySQL credentials:**
- Click on **MySQL** in your project
- Go to **Variables** tab
- Copy `MYSQL_HOST`, `MYSQL_PORT`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_DB`

## 5. Generate JWT Secret

Generate a secure random string for JWT_SECRET:

```bash
# On Linux/Mac
openssl rand -base64 32

# On Windows (PowerShell)
[Convert]::ToBase64String((1..32 | ForEach-Object {Get-Random -Maximum 256})) | Select-Object -First 1
```

## 6. Initialize Database

After deployment, you need to run the schema to create tables:

### Option A: Using Railway CLI

```bash
npm install -g @railway/cli
railway login
cd backend
railway shell
mysql -u root -p$DB_PASSWORD -h $DB_HOST -P $DB_PORT $DB_NAME < database/schema.sql
exit
```

### Option B: Using phpMyAdmin or another MySQL client

1. Connect to your Railway MySQL using the credentials
2. Execute the SQL from `database/schema.sql`

### Option C: Via cron job (set up later)

Create an initialization script that runs once.

## 7. Verify Deployment

1. In Railway dashboard, check if your service is running (green status)
2. Click on the Web Service
3. Copy the public domain URL
4. Test API:
   ```bash
   curl https://your-deployment.railway.app/
   ```
5. Check logs in Railway dashboard for any errors

## 8. Connect Your Frontend

Update your frontend's API URL to point to your Railway backend:

```javascript
// In your frontend config or API file
const API_BASE_URL = 'https://your-deployment.railway.app';
```

## Complete Variable Setup Example

```
DB_HOST=mysql.railway.internal
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASSWORD=YourGeneratedPasswordHere

JWT_SECRET=1a2b3c4d5e6f7g8h9i0j1k2l3m4n5o6p

ALLOWED_ORIGINS=https://frontend.example.com,https://app.example.com,http://localhost:5173

APP_ENV=production
APP_DEBUG=false
```

## Troubleshooting

### Site returns 500 error
- Check logs in Railway dashboard
- Verify database connectivity
- Ensure schema.sql was properly executed

### Database connection refused
- Verify DB_HOST value (should be `mysql.railway.internal` for internal connection)
- Check credentials match MySQL plugin variables
- Ensure MySQL service is running (green status in dashboard)

### File uploads not working
- Railway's filesystem is ephemeral (files lost on restart)
- Move to cloud storage like S3 or Supabase
- Update upload endpoints accordingly

### CORS errors
- Update `ALLOWED_ORIGINS` environment variable
- Include your frontend domain
- Restart web service for changes to take effect

## Auto-Deploy on Git Push

Railway auto-deploys when you push to your main branch:

```bash
git add .
git commit -m "Update: new feature"
git push origin main
```

Monitor deployment in Railway dashboard.

## Support Resources

- Railway Docs: https://docs.railway.app
- Railway Support: https://railway.app/support
- Railway Status: https://status.railway.app
- Discord Community: https://discord.gg/railway

## Next Steps

1. ✅ Deploy backend on Railway
2. Set up file storage (AWS S3 or Supabase)
3. Configure email service
4. Set up monitoring/logging
5. Enable database backups in Railway
6. Configure custom domain (optional)
7. Set up CI/CD pipeline (if needed)
8. Performance optimization and caching
