# Railway Deployment Checklist

Use this checklist to ensure your Swapie Backend is properly configured and deployed on Railway.

## Pre-Deployment Setup

### Code & Git
- [ ] Code is committed to Git repository
- [ ] `.env` file is in `.gitignore` (check `.gitignore` file exists)
- [ ] `config/database.php` has environment variable support
- [ ] `config/config.php` loads environment variables

### Local Testing
- [ ] Backend runs locally: `php -S localhost:8000`
- [ ] API endpoints respond (test `/`)
- [ ] Database connection works locally
- [ ] All dependencies are documented in `composer.json`

## Railway Project Setup

### 1. Railway Account & Project
- [ ] Railway account created at [railway.app](https://railway.app)
- [ ] GitHub account connected to Railway
- [ ] Repository connected to Railway project
- [ ] Web service created for your backend

### 2. MySQL Database
- [ ] MySQL database provisioned in Railway
- [ ] Database is accessible (green status in dashboard)
- [ ] Note down all connection credentials:
  - [ ] DB_HOST: _______________
  - [ ] DB_PORT: _______________
  - [ ] DB_USER: _______________
  - [ ] DB_PASSWORD: _______________
  - [ ] DB_NAME: _______________

### 3. Environment Variables Configuration

In Railway Variables tab, set these:

**Database Configuration**
- [ ] `DB_HOST` = mysql.railway.internal (or your host)
- [ ] `DB_PORT` = 3306
- [ ] `DB_NAME` = railway
- [ ] `DB_USER` = root
- [ ] `DB_PASSWORD` = (from MySQL credentials)

**Application Configuration**
- [ ] `JWT_SECRET` = (strong random 32+ chars)
- [ ] `APP_ENV` = production
- [ ] `APP_DEBUG` = false

**CORS Configuration**
- [ ] `ALLOWED_ORIGINS` = https://your-frontend.com,http://localhost:5173

**Optional (if needed)**
- [ ] `SUPABASE_URL` = (if using Supabase)
- [ ] `SUPABASE_SERVICE_KEY` = (if using Supabase)

## Post-Deployment Initialization

### Database Schema
- [ ] Database schema imported (`database/schema.sql`)
  - [ ] Tables created in MySQL
  - [ ] Admin user created (if applicable)
  - [ ] Sample data loaded (if applicable)

### API Testing
- [ ] Test basic API endpoint: `curl https://your-url.railway.app/`
- [ ] Test with OPTIONS request (CORS preflight)
- [ ] Test protected endpoints (auth required)
- [ ] Test database queries (should work)
- [ ] Check logs for errors in Railway dashboard

### File Uploads
- [ ] Understand ephemeral filesystem limitation
- [ ] Plan for cloud storage if file uploads needed
- [ ] Update upload endpoints if using cloud storage
- [ ] Test file operations

## Production Hardening

### Security
- [ ] JWT_SECRET is unique and strong (>32 characters)
- [ ] APP_DEBUG is set to `false`
- [ ] ALLOWED_ORIGINS only includes your domains
- [ ] Sensitive files not in git (`.env`, uploaded files)
- [ ] `.htaccess` protecting sensitive directories
- [ ] HTTP headers properly configured (security headers)

### Performance
- [ ] Database indexes are optimized
- [ ] API response times acceptable
- [ ] No N+1 query problems
- [ ] Caching configured (if applicable)

### Monitoring & Logging
- [ ] Error logging enabled (logs saved)
- [ ] Railway logs monitored in dashboard
- [ ] Set up alerts for deployment failures
- [ ] Consider external logging service (Sentry, etc.)

### Database
- [ ] Database backups enabled in Railway
- [ ] Backup retention suitable for production
- [ ] Test restore procedure
- [ ] Connection timeout values appropriate

### Frontend Integration
- [ ] Frontend API URL updated to Railway URL
- [ ] CORS configuration matches frontend domain
- [ ] Authentication flow tested end-to-end
- [ ] Cookie/token handling works correctly
- [ ] Error messages properly displayed

## Ongoing Operations

### Monitoring
- [ ] Check Railway dashboard regularly
- [ ] Review logs for errors
- [ ] Monitor database usage
- [ ] Track API response times

### Maintenance
- [ ] Document deployment process
- [ ] Keep `.env.example` updated with new vars
- [ ] Regular backups of critical data
- [ ] Update dependencies regularly

### Updates & Deployments
- [ ] New deployments tested locally first
- [ ] Database migrations handled safely
- [ ] Rollback plan in place for critical updates
- [ ] Team aware of deployment schedule

## Common Issues & Solutions

### Database Connection Errors
- [ ] Verify `DB_HOST` - use `mysql.railway.internal` for internal connection
- [ ] Check `DB_PASSWORD` is correctly escaped if it has special characters
- [ ] Ensure MySQL service is running (green status)
- [ ] Test connection using Railway CLI: `railway shell`

### 502 Bad Gateway
- [ ] Check Procfile exists and is correct
- [ ] Verify PHP version in `.php-version`
- [ ] Check Laravel/framework compatibility

### CORS Errors
- [ ] Add frontend domain to `ALLOWED_ORIGINS` variable
- [ ] Frontend URL must match exactly (https://example.com ≠ https://example.com/)
- [ ] Restart web service after updating

### Files Don't Persist
- [ ] Remember Railway filesystem is ephemeral
- [ ] Move to cloud storage (S3, Supabase, etc.)
- [ ] Update upload endpoints for cloud storage

### API Can't Find Routes
- [ ] Verify `.htaccess` file is present
- [ ] Check `RewriteEngine On` is enabled
- [ ] Ensure `index.php` exists in root

## Emergency Procedures

### Rolling Back
1. Go to Railway project dashboard
2. Click on Web Service
3. Find previous deployment in history
4. Click "Deploy" next to previous version

### Accessing Database Directly
```bash
railway shell
mysql -u root -p$DB_PASSWORD -h $DB_HOST -P $DB_PORT $DB_NAME
```

### Viewing Live Logs
```bash
railway logs  # Using Railway CLI
# Or view in dashboard: Logs tab
```

### Restarting Services
- [ ] Restart web service in Railway dashboard
- [ ] Restart MySQL in Railway dashboard
- [ ] All connections recreated automatically

## Sign-Off

- **Deployment Date:** _______________
- **Deployed By:** _______________
- **Infrastructure:** Railway
- **Frontend URL:** _______________
- **API URL:** _______________
- **Notes:** ________________________________________

## Additional Resources

- Railway Docs: https://docs.railway.app
- troubleshooting: https://docs.railway.app/troubleshooting
- MySQL in Railway: https://docs.railway.app/databases/mysql
- Environment Variables: https://docs.railway.app/develop/variables

---

Last Updated: February 7, 2026
