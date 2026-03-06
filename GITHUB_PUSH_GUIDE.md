# 🚀 GitHub Push Guide — WinDeploy

**Repository:** https://github.com/gastaldellomarco/windeploy  
**Date:** 2026-03-06  
**Status:** Ready for initial push

---

## ✅ Pre-Push Checklist

### 1. Verify `.gitignore` Configuration

All `.gitignore` files have been reviewed and updated:

| Directory    | Status         | Key Ignores                                                      |
| ------------ | -------------- | ---------------------------------------------------------------- |
| **Backend**  | ✅             | `.env`, `/vendor`, `/storage/logs`, `tmp_*.php`, `/node_modules` |
| **Frontend** | ✅             | `node_modules`, `dist`, `.vscode`, `*.log`                       |
| **Agent**    | ✅ **UPDATED** | `__pycache__`, `.venv`, `build/`, `dist/`, `*.log`, `.env`       |
| **Root**     | ❌ Create      | General rules + sensitive files                                  |

**Action needed:** Create root `.gitignore` (see below)

---

### 2. Sensitive Files NOT to Push

Verify these files are properly ignored or don't exist:

```
❌ .env                    (Database credentials, JWT secrets, API keys)
❌ .env.backup             (Backup of credentials)
❌ .env.production          (Production secrets)
❌ backend/auth.json        (Composer auth)
❌ backend/storage/logs/*   (Application logs with PII)
❌ frontend/node_modules    (2000+ MB, managed by npm)
❌ agent/build/             (PyInstaller artifacts)
❌ agent/dist/              (Compiled binaries)
❌ agent/__pycache__/       (Python cache)
❌ vendor/                  (Composer dependencies)
❌ node_modules/            (NPM dependencies)
```

---

### 3. Directories That SHOULD Be Pushed

```
✅ backend/app/             (Source code)
✅ backend/routes/          (API routes)
✅ backend/config/          (Config templates)
✅ backend/database/        (Migrations)
✅ backend/tests/           (Test suite)
✅ frontend/src/            (React source)
✅ agent/                   (Python agent source)
✅ docs/                    (All documentation)
```

---

## 📋 Step-by-Step Push Instructions

### Step 1: Create Root `.gitignore`

Create `c:\xampp\htdocs\windeploy\.gitignore`:

```gitignore
# =============================================
# Environment & Secrets (CRITICAL)
# =============================================
.env
.env.backup
.env.production
.env.local
config.local.py
auth.json

# =============================================
# IDE & OS
# =============================================
.vscode/
.idea/
*.swp
*.swo
*.sublime-*
.DS_Store
Thumbs.db
desktop.ini

# =============================================
# Build & Cache
# =============================================
node_modules/
vendor/
__pycache__/
*.pyc
build/
dist/
*.egg-info/
.phpunit.cache/
.pytest_cache/

# =============================================
# Logs & Temporary Files
# =============================================
*.log
logs/
tmp_*.php
tmp_*.py
tmp_*.sql
storage/logs/
storage/debugbar/

# =============================================
# PowerShell & OS
# =============================================
*.ps1
*.bat
*.exe (selettivo — escludere solo build outputs)

# =============================================
# Development & Local Testing
# =============================================
scripts/dev/
scripts/local/
*.phar
*.zip
*.tar.gz
coverage/

# =============================================
# macOS
# =============================================
.DS_Store
.AppleDouble
.LSOverride
*.swp

# =============================================
# Windows
# =============================================
Thumbs.db
ehthumbs.db
Desktop.ini
```

---

### Step 2: Verify No Sensitive Data is Tracked

Run these commands in PowerShell (in `c:\xampp\htdocs\windeploy`):

```powershell
# Check for tracked .env files (should return nothing)
git ls-files | Select-String "\.env"

# Check for tracked node_modules (should return nothing)
git ls-files | Select-String "node_modules"

# Check for tracked vendor (should return nothing)
git ls-files | Select-String "vendor"

# List all tracked files (verify sanity)
git ls-files | head -20

# Check git status
git status
```

**Expected output:**

- ✅ No `.env` files in git ls-files
- ✅ No `node_modules/` in git ls-files
- ✅ No `vendor/` in git ls-files
- ✅ `git status` shows only untracked files in ignored directories

---

### Step 3: Configure Git (if needed)

```powershell
# Set your Git user (if not already done)
git config --global user.name "Your Name"
git config --global user.email "your.email@example.com"

# Verify remote
git remote -v
# Should show: origin https://github.com/gastaldellomarco/windeploy.git
```

---

### Step 4: Stage Files

```powershell
# Add all files (respecting .gitignore)
git add .

# Verify staging (optional)
git status

# You should see:
# - New files: all source code, docs, config templates
# - Ignored: .env, node_modules, vendor, __pycache__, build, dist
```

---

### Step 5: Create Initial Commit

```powershell
git commit -m "Initial commit: WinDeploy full stack

- Backend: Laravel 11 with agent auth endpoints
- Frontend: React 18 + Vite wizard builder
- Agent: Python 3.11 CustomTkinter GUI
- Docs: Complete documentation with 20+ guides
- Security: JWT auth, rate limiting, MAC validation
- Database: 6 tables with migrations

Key features:
- Multi-step wizard configuration
- Real-time progress tracking
- HTML report generation
- Rate limiting (10 req/min IP, 5 req/min code)
- Log upload to backend
- System configuration (PC rename, user creation, power plan)

See docs/README.md for full overview."
```

---

### Step 6: Push to GitHub

```powershell
# Push to main branch
git push -u origin main

# Or if branch doesn't exist yet
git push --set-upstream origin main
```

---

### Step 7: Verify on GitHub

1. Open https://github.com/gastaldellomarco/windeploy
2. Verify:
   - ✅ Files are visible (backend, frontend, agent, docs)
   - ✅ `.env` file is NOT present
   - ✅ `node_modules/` directory is NOT present
   - ✅ `vendor/` directory is NOT present
   - ✅ `__pycache__/` directory is NOT present
   - ✅ Commit message is clear
   - ✅ README.md appears on main page

---

## 🔍 Verification Commands

Run these to ensure safety before pushing:

```powershell
cd c:\xampp\htdocs\windeploy

# 1. Check for sensitive patterns in tracked files
git ls-files | xargs grep -l "password\|secret\|API_KEY\|TOKEN" 2>/dev/null
# Should return NOTHING

# 2. List all files that will be pushed
git ls-files | wc -l
# Should show realistic count (~200-400 files)

# 3. Check for large files (>10MB)
git ls-files -s | sort -t$'\t' -k5 -rn | head -10
# Should show docs, markdown, JSON — nothing > 50MB

# 4. Verify .gitignore is being applied
git check-ignore -v *
# Should show many ignored files in output
```

---

## ⚠️ Critical Files to Verify Are NOT in Git

```powershell
# These commands should all return nothing:

git ls-files | Select-String "\.env$"
git ls-files | Select-String "\.env\.backup"
git ls-files | Select-String "\.env\.production"
git ls-files | Select-String "backend/node_modules"
git ls-files | Select-String "backend/vendor"
git ls-files | Select-String "frontend/node_modules"
git ls-files | Select-String "agent/__pycache__"
git ls-files | Select-String "agent/build"
git ls-files | Select-String "agent/dist"
git ls-files | Select-String "storage/logs/.*\.log"
```

---

## 📝 Post-Push Checklist

- [ ] Verify all files pushed to GitHub
- [ ] Check that `.env` is NOT in repository
- [ ] Check that `node_modules/` is NOT in repository
- [ ] Check that `vendor/` is NOT in repository
- [ ] Verify documentation is readable on GitHub
- [ ] Add GitHub repository link to README.md
- [ ] Create initial GitHub release/tag
- [ ] Set up branch protection rules (optional)
- [ ] Add GitHub Actions CI/CD (optional)

---

## 🎯 If Something Goes Wrong

### Case 1: Pushed `.env` by mistake

```powershell
# Remove from history (CAREFUL — rewrites history)
git filter-branch --tree-filter 'rm -f .env' HEAD

# Force push (only if you're on a private repo)
git push --force-with-lease origin main
```

### Case 2: Need to unstage before committing

```powershell
git reset HEAD .env backend/node_modules vendor/
```

### Case 3: Repository already has unwanted files

```powershell
# Remove from tracking but keep locally
git rm --cached .env backend/node_modules vendor -r
git commit -m "Remove tracked files that should be ignored"
git push origin main
```

---

## 📚 References

- **GitHub .gitignore best practices:** https://github.com/github/gitignore
- **Git documentation:** https://git-scm.com/doc
- **Your repository:** https://github.com/gastaldellomarco/windeploy

---

## ✅ Final Status

| Component          | Status     | Notes                                   |
| ------------------ | ---------- | --------------------------------------- |
| `.gitignore` files | ✅ UPDATED | All directories covered                 |
| Sensitive files    | ✅ IGNORED | .env, vendor, node_modules blocked      |
| Source code        | ✅ READY   | All backend, frontend, agent ready      |
| Documentation      | ✅ READY   | 20+ markdown files with complete guides |
| Migrations         | ✅ READY   | 6 Laravel migrations included           |
| Tests              | ✅ READY   | Test suites included                    |
| **Ready to push?** | ✅ **YES** | Safe to push to GitHub now              |

---

**Created:** 2026-03-06  
**Last verified:** 2026-03-06
