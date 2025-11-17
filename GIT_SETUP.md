# Git Setup Guide

## Initial Git Setup

### 1. Initialize Git Repository
```bash
cd "D:\Github Showcase\Travel Agency Portal"
git init
```

### 2. Add All Files
```bash
git add .
```

### 3. Create Initial Commit
```bash
git commit -m "Initial commit: Travel Agency Portal with Laravel backend and React frontend"
```

### 4. Add Remote Repository (if using GitHub/GitLab)
```bash
git remote add origin <your-repository-url>
git branch -M main
git push -u origin main
```

## What's Ignored

The `.gitignore` files ensure the following are **NOT** committed:

### Backend (Laravel)
- ✅ `vendor/` - Composer dependencies
- ✅ `node_modules/` - NPM dependencies
- ✅ `.env` - Environment configuration (contains secrets)
- ✅ `storage/logs/` - Log files
- ✅ `storage/framework/` - Framework cache files
- ✅ `bootstrap/cache/` - Bootstrap cache
- ✅ `public/storage` - Storage symlink

### Frontend (React)
- ✅ `node_modules/` - NPM dependencies
- ✅ `build/` or `dist/` - Production builds
- ✅ `.env` - Environment variables
- ✅ `.vite/` - Vite cache
- ✅ Log files

### Common
- ✅ IDE configuration files (`.idea/`, `.vscode/`)
- ✅ OS files (`.DS_Store`, `Thumbs.db`)
- ✅ Temporary files
- ✅ Database files (`.sqlite`, `.db`)

## What's Committed

### Backend
- ✅ Source code (`app/`, `config/`, `routes/`, etc.)
- ✅ Database migrations (`database/migrations/`)
- ✅ Seeders (`database/seeders/`)
- ✅ Models, Controllers, Middleware
- ✅ `composer.json` and `composer.lock`
- ✅ `.env.example` (template, no secrets)

### Frontend
- ✅ Source code (`src/`)
- ✅ Configuration files (`vite.config.js`, `tailwind.config.js`)
- ✅ `package.json` and `package-lock.json`
- ✅ Public assets (`public/`)
- ✅ `.env.example` (template)

### Documentation
- ✅ `README.md`
- ✅ `SETUP.md`
- ✅ `PROJECT_STRUCTURE.md`
- ✅ `FIXES.md`

## Important Notes

### ⚠️ Never Commit:
1. **`.env` files** - Contains database credentials, API keys, secrets
2. **`vendor/` or `node_modules/`** - Can be reinstalled via `composer install` / `npm install`
3. **Log files** - Can be regenerated
4. **Cache files** - Can be regenerated
5. **Uploaded files** - User-generated content (images, documents)

### ✅ Always Commit:
1. **`.env.example`** - Template for environment setup
2. **`composer.json` / `package.json`** - Dependency definitions
3. **Source code** - All application code
4. **Migrations** - Database schema
5. **Configuration templates** - Non-sensitive config files

## Environment Setup for New Developers

When cloning the repository, developers should:

1. **Backend:**
   ```bash
   cd backend
   cp .env.example .env
   composer install
   php artisan key:generate
   php artisan migrate
   ```

2. **Frontend:**
   ```bash
   cd frontend
   cp .env.example .env
   npm install
   ```

## Storage Directories

The following storage directories are tracked with `.gitkeep`:
- `backend/storage/app/public/` - Public storage
- `backend/storage/app/public/packages/` - Package images
- `backend/storage/app/public/avatars/` - User avatars

These directories will be created automatically, but the `.gitkeep` files ensure the directory structure is preserved in git.

## Pre-commit Checklist

Before committing, ensure:
- [ ] No `.env` files are included
- [ ] No `vendor/` or `node_modules/` directories
- [ ] No log files
- [ ] No cache files
- [ ] `.env.example` is up to date
- [ ] All sensitive data is removed

## Git Commands Reference

```bash
# Check what will be committed
git status

# Check what's ignored
git status --ignored

# Add specific files
git add <file>

# Commit changes
git commit -m "Your commit message"

# Push to remote
git push origin main

# Pull latest changes
git pull origin main
```






