# Admin Panel - MovieMind API

## 📋 Overview

Admin panel built with Laravel Filament v3 for managing MovieMind API content.

**Access:** `/admin`  
**Authentication:** Filament Login + Basic Auth (inherited from TASK-050)

---

## 🎯 Features

### 1. Movies Management
- **CRUD Operations:** Create, Read, Update, Delete movies
- **Fields:**
  - Title (required, auto-generates slug)
  - Slug (unique, auto-generated)
  - Release Year (1800-2100)
  - Director
  - Genres (tags)
  - TMDb ID
- **Table Features:**
  - Search: title, director, slug
  - Sort: title, year, created_at
  - Filters: Release year range
  - Toggleable columns
- **Actions:** View, Edit, Delete

### 2. People Management
- **CRUD Operations:** Create, Read, Update, Delete people
- **Fields:**
  - Name (required, auto-generates slug)
  - Slug (unique, auto-generated)
  - Birth Date
  - Birthplace
  - TMDb ID
- **Table Features:**
  - Search: name, birthplace, slug
  - Sort: name, birth_date, created_at
  - Filters: Birth year range
  - Toggleable columns
- **Actions:** View, Edit, Delete

### 3. Feature Flags Management
- **View-Only Interface:** Display all feature flags from `config/pennant.php`
- **Categorized Display:**
  - Core AI Features
  - AI Quality & Safety
  - Localization
  - Experimental Features
  - Admin Features
  - API Features
  - Caching
  - Webhooks
  - Public Features
  - Security
- **Toggle Controls:** Enable/disable togglable flags (in-memory only)
- **Note:** Changes are not persisted. To persist, update `config/pennant.php`

### 4. Dashboard
- **Stats Overview Widget:**
  - Total Movies
  - Total People
  - Pending Jobs (queue)
  - Failed Jobs (last 24h)
- **Quick Links:**
  - Horizon (queue monitoring)
  - Analytics Dashboard (TASK-010)

---

## 🔐 Security

### Authentication
- **Filament Login:** Email + Password
- **Basic Auth:** Inherited from TASK-050 (`/admin/*` routes)
- **User Model:** Implements `FilamentUser` interface

### Default Admin User
```
Email: admin@moviemind.local
Password: password123
```

**⚠️ IMPORTANT:** Change default credentials in production!

---

## 🚀 Usage

### Accessing Admin Panel

1. Navigate to `/admin`
2. Login with credentials
3. Dashboard displays with stats overview

### Managing Movies

1. Click "Movies" in sidebar
2. Use "New Movie" button to create
3. Fill form (slug auto-generates from title)
4. Save

**Validation:**
- Title: required, max 255 chars
- Slug: required, unique, max 255 chars
- Release Year: numeric, 1800-2100
- TMDb ID: numeric

### Managing People

1. Click "People" in sidebar
2. Use "New Person" button to create
3. Fill form (slug auto-generates from name)
4. Save

**Validation:**
- Name: required, max 255 chars
- Slug: required, unique, max 255 chars
- Birth Date: date, max today
- TMDb ID: numeric

### Viewing Feature Flags

1. Click "Feature Flags" in sidebar
2. Browse flags by category
3. Toggle switches for togglable flags
4. Click "Save Changes" (in-memory only)

**Note:** To persist changes, manually update `config/pennant.php`

---

## 🎨 UI/UX

### Design
- **Framework:** Filament v3
- **Stack:** Livewire 3 + Tailwind CSS 3 + Alpine.js
- **Icons:** Heroicons
- **Theme:** Blue primary color
- **Dark Mode:** Enabled

### Navigation
```
📊 Dashboard
🎬 Movies
👥 People
🚩 Feature Flags
```

### Responsive
- Mobile-friendly
- Tablet-optimized
- Desktop-first

---

## 🔧 Technical Details

### Dependencies
```json
{
  "filament/filament": "^3.2",
  "livewire/livewire": "^3.0"
}
```

### Files Created
```
api/app/Providers/Filament/AdminPanelProvider.php
api/app/Filament/Resources/MovieResource.php
api/app/Filament/Resources/PersonResource.php
api/app/Filament/Pages/FeatureFlags.php
api/app/Filament/Widgets/StatsOverview.php
api/resources/views/filament/pages/feature-flags.blade.php
```

### Configuration
- **Panel ID:** `admin`
- **Path:** `/admin`
- **Brand Name:** MovieMind Admin
- **Primary Color:** Blue

---

## 📊 Monitoring

### Dashboard Metrics
- **Total Movies:** Count from `movies` table
- **Total People:** Count from `people` table (formerly `persons`)
- **Pending Jobs:** Count from `jobs` table
- **Failed Jobs:** Count from `failed_jobs` (last 24h)

### External Links
- **Horizon:** `/horizon` - Queue monitoring
- **Analytics:** `/admin/dashboard` - TASK-010 analytics

---

## 🐛 Troubleshooting

### Cannot access `/admin`
1. Check Basic Auth credentials (TASK-050)
2. Verify user exists in `users` table
3. Clear cache: `php artisan config:clear`

### Slug not auto-generating
1. Type in Title/Name field
2. Click outside field (blur event)
3. Slug should populate automatically

### Feature Flags not saving
- **Expected behavior:** Changes are in-memory only
- **To persist:** Manually update `config/pennant.php`

### Stats not updating
1. Refresh page
2. Check database connections
3. Verify tables exist: `movies`, `people`, `jobs`, `failed_jobs`

---

## 🔄 Future Enhancements

### Planned (not in TASK-009 scope)
- [ ] Persistent feature flag storage (database)
- [ ] Bulk operations for movies/people
- [ ] Advanced search filters
- [ ] Export functionality (CSV, JSON)
- [ ] Activity log
- [ ] User roles & permissions (Filament Shield)
- [ ] Relation managers (movie-person pivot)
- [ ] Image uploads (posters, photos)

---

## 📚 References

- [Filament Documentation](https://filamentphp.com/docs)
- [Filament Resources](https://filamentphp.com/docs/3.x/panels/resources)
- [Filament Pages](https://filamentphp.com/docs/3.x/panels/pages)
- [Filament Widgets](https://filamentphp.com/docs/3.x/panels/dashboard)
- [Laravel Pennant](https://laravel.com/docs/11.x/pennant)

---

**Created:** 2025-01-08  
**Task:** TASK-009  
**Status:** ✅ COMPLETED
