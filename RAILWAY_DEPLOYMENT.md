# Railway Deployment Guide

This guide will help you deploy the Swapie Admin Backend API to Railway.

## Prerequisites

- Railway account (sign up at [railway.app](https://railway.app))
- Git installed
- Backend code ready to push

## Step-by-Step Deployment

### 1. Create a New Project on Railway

1. Go to [Railway Dashboard](https://railway.app/dashboard)
2. Click **New Project**
3. Select **Deploy from GitHub** (recommended) or **GitHub Repo**
4. Connect your GitHub account if not already connected
5. Select your repository

### 2. Add MySQL Database

1. In your Railway project dashboard
2. Click the **+ New** button
3. Select **MySQL**
4. Railway will automatically create a MySQL database
5. Note the connection details (visible in the Variables tab)

### 3. Configure Environment Variables

In your Railway project, go to **Variables** tab and add:

```
DB_HOST=your_mysql_host
DB_PORT=3306
DB_NAME=railway
DB_USER=root
DB_PASSWORD=your_generated_password

JWT_SECRET=your-unique-secret-key-min-32-chars
APP_ENV=production
APP_DEBUG=false
```

**To find Railway MySQL credentials:**
- Open the MySQL plugin in your Railway project
- Click on the plugin name to see connection details
- Copy necessary values

### 4. Update Database Configuration

Edit `config/database.php` to use environment variables:

```php
private $remoteHost = getenv('DB_HOST') ?: "localhost";
private $remoteDatabase = getenv('DB_NAME') ?: "railway";
private $remoteUsername = getenv('DB_USER') ?: "root";
private $remotePassword = getenv('DB_PASSWORD') ?: "";
private $remotePort = getenv('DB_PORT') ?: 3306;
```

### 5. Initialize Database

After deployment:

1. SSH into Railway container or use the Railway CLI
2. Run the database schema:

```bash
mysql -h $DB_HOST -P $DB_PORT -u $DB_USER -p$DB_PASSWORD $DB_NAME < database/schema.sql
```

Or upload the schema through Railway's web interface if available.

### 6. Deploy

**Option A: GitHub Integration (Recommended)**
1. Railway auto-deploys on git push to main branch
2. Any changes pushed to GitHub automatically trigger deployment
3. Monitor deployment status in Railway dashboard

**Option B: Railway CLI**
```bash
npm i -g @railway/cli
railway login
railway up
```

### 7. Verify Deployment

1. Visit your Railway project dashboard
2. Find your web service's public URL
3. Test the API: `curl https://your-railway-url.railway.app/`
4. Check logs in Railway dashboard for any errors

## Important Notes

### Environment Variables
- **Never commit `.env` file** - use `.env.example` as template
- All sensitive data should be in Railway Variables, not in code
- Railway uses `$PORT` environment variable, make sure your server listens on it

### Database Migrations
- Run `database/schema.sql` on first deployment
- Keep migration scripts in `/database` folder
- Use Railway CLI to execute SQL: `railway exec mysql < database/schema.sql`

### CORS Configuration
- Update `ALLOWED_ORIGINS` in config.php for production domains
- Add your frontend domain to allowed origins

### File Uploads
- Railway's ephemeral filesystem means uploaded files are lost on restart
- Use cloud storage (AWS S3, Supabase) for permanent file storage
- Update upload logic in relevant API endpoints

### Logs
- Logs are saved to `/logs/error.log`
- View real-time logs in Railway dashboard
- Consider integrating with external logging service

## Troubleshooting

### Database Connection Error
- Verify credentials in Variables tab
- Ensure MySQL database is running (green status)
- Check firewall rules in Railway settings
- Restart the MySQL plugin

### 502 Bad Gateway Error
- Check application logs in Railway dashboard
- Verify PHP is properly configured
- Ensure `composer.json` has all dependencies

### API returns 500 Error
- Check error logs in `/logs/error.log`
- Verify database connection
- Ensure all required PHP extensions are enabled

## Useful Railway Commands

```bash
# View logs
railway logs

# SSH into container
railway shell

# View environment variables
railway variables

# Run a one-time command
railway exec php -v
```

## Updating Deployment

1. Write code changes locally
2. Push to GitHub: `git push origin main`
3. Railway auto-deploys on push
4. Monitor deployment in Railway dashboard
5. Check logs if deployment fails

## Production Checklist

- [ ] Update `JWT_SECRET` to a strong random value
- [ ] Set `APP_DEBUG=false` in production
- [ ] Configure `ALLOWED_ORIGINS` for your frontend domains
- [ ] Test all API endpoints after deployment
- [ ] Set up error monitoring/alerting
- [ ] Configure database backups
- [ ] Enable HTTPS (automatic with Railway)
- [ ] Test file upload functionality
- [ ] Verify database schema is correctly initialized

## Support

- Railway Docs: https://docs.railway.app
- Railway Support: https://railway.app/support
- Check Railway status: https://status.railway.app
