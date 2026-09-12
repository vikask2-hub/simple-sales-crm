# Simple Sales CRM

[![Live demo](https://img.shields.io/badge/Live_Demo-tech4projects.online-2563eb?style=for-the-badge)](https://tech4projects.online/crm)
[![PHP](https://img.shields.io/badge/PHP-8.2%2B-777bb4?logo=php)](https://www.php.net/)

A deliberately simple sales workspace that keeps leads, activities, follow-ups, and conversions connected without the overhead of a large CRM.

## Product highlights

- Owner and BDE workspaces with scoped records
- Lead CRUD, pipeline status, ownership, notes, and follow-up dates
- Activity logging with calls, meetings, messages, and outcomes
- Dashboard metrics for pipeline value, overdue work, and conversion
- Team administration for owners
- Responsive Bootstrap interface with CSRF protection and server-side validation

## Stack

PHP 8.2+ · PDO · MySQL/SQLite · Bootstrap 5 · Vanilla JavaScript

## Run locally

```bash
git clone https://github.com/vikask2-hub/simple-sales-crm.git
cd simple-sales-crm
export CRM_DSN='sqlite:database/local.sqlite'
export CRM_BASE_URL=''
php database/install.php
php -S 127.0.0.1:8000 -t public
```

Open `http://127.0.0.1:8000`. Demo mode exposes one-click Owner and BDE access.

## Quality and security

The code uses prepared PDO queries, strict session settings, CSRF tokens, password hashing, output escaping, ownership checks, and environment-only database configuration. Production credentials and runtime databases are excluded.

---

Built by [Vikas Kaithia](https://github.com/vikask2-hub) · [View the complete product portfolio](https://tech4projects.online/)
