# MovieMind API - Project Summary

> **For:** Job Interviews, Recruiters, Technical Presentations  
> **Last Updated:** 2026-01-22  
> **Status:** Portfolio/Demo Project

---

## 🎯 Quick Overview

**MovieMind API** is a RESTful API service that generates unique, AI-powered descriptions for movies, TV series, and actors. Unlike traditional movie databases that copy content from IMDb or TMDb, MovieMind creates original content from scratch using OpenAI's GPT models.

**Key Differentiator:** Original AI-generated content, not copied metadata.

---

## 💼 Project Details

### What is it?
A production-ready API service that:
- Generates unique movie/series descriptions using AI
- Supports multiple languages (pl-PL, en-US, etc.)
- Provides contextual styling (modern, critical, humorous)
- Manages subscriptions with rate limiting
- Integrates with external APIs (TMDB, TVmaze) for verification
- Handles async processing via Laravel Horizon

### Technology Stack
- **Backend:** Laravel 12 (PHP 8.2+)
- **Database:** PostgreSQL (production), SQLite (tests)
- **Cache/Queue:** Redis + Laravel Horizon
- **AI:** OpenAI API (gpt-4o-mini)
- **Testing:** PHPUnit (Feature + Unit), Playwright (E2E)
- **Code Quality:** Laravel Pint, PHPStan, GitLeaks
- **Deployment:** Docker Compose (local), Nginx + PHP-FPM

### Architecture
- **Pattern:** Modular Monolith with Feature-Based Scaling
- **Design Patterns:** Repository, Service Layer, Action, Event-Driven, Response Formatter
- **Architecture Style:** Layered Architecture (HTTP → Business Logic → Data Access)
- **Async Processing:** Event-Driven (Events → Listeners → Jobs → Queue)

---

## 🚀 Key Features

### Core Functionality
1. **AI Content Generation**
   - Generates unique descriptions for movies, TV series, actors
   - Supports multiple languages and contextual styles
   - Async processing via queue system

2. **Entity Management**
   - Movies, People, TV Series, TV Shows
   - Slug-based identification (`the-matrix-1999`)
   - Disambiguation for ambiguous titles
   - Bulk operations (up to 100 entities)

3. **Search & Discovery**
   - Full-text search across all entity types
   - Related content recommendations
   - Comparison between entities

4. **Subscription System**
   - Three tiers: Free, Pro, Enterprise
   - API key-based authentication
   - Plan-based rate limiting
   - Usage analytics

5. **External Integrations**
   - TMDB (movie/person verification)
   - TVmaze (TV series/show verification)
   - OpenAI (AI content generation)

### Advanced Features
- **Multilingual Support:** pl-PL, en-US, and more
- **Contextual Styling:** Modern, critical, humorous descriptions
- **Smart Caching:** Redis-based caching with TTL management
- **Async Processing:** Laravel Horizon for background jobs
- **HATEOAS:** Hypermedia links in API responses
- **Versioning:** Description versioning with archiving
- **Reporting:** User reporting system for content issues
- **Admin Panel:** Filament-based admin interface

---

## 🏗️ Architecture Highlights

### Why These Patterns?

**Thin Controllers:**
- Controllers handle only HTTP concerns (max 20-30 lines)
- Business logic delegated to Services/Actions
- **Benefit:** Easy to test, maintainable, reusable

**Service Layer:**
- Services encapsulate business logic
- Coordinate between repositories and external APIs
- **Benefit:** Centralized logic, testable, reusable

**Repository Pattern:**
- Abstracts data access layer
- Encapsulates database queries
- **Benefit:** Testable (mock repositories), flexible (swap implementations)

**Action Pattern:**
- Single business operations
- Complex workflows encapsulated
- **Benefit:** Single responsibility, composable, testable

**Event-Driven Architecture:**
- Events → Listeners → Jobs
- Decouples components
- **Benefit:** Scalable, extensible, loose coupling

---

## 📊 Technical Metrics

### Code Quality
- **Test Coverage:** 859 passing tests (Unit + Feature + E2E)
- **Code Style:** Laravel Pint (PSR-12)
- **Static Analysis:** PHPStan (level 5)
- **Security:** GitLeaks (secret detection)

### Performance
- **Caching:** Redis-based (TMDB: 6 months, TVmaze: indefinite)
- **Queue:** Laravel Horizon (async processing)
- **Database:** PostgreSQL with proper indexing
- **Rate Limiting:** Plan-based (10-1000 requests/minute)

### Scalability
- **Horizontal Scaling:** Stateless API (can scale horizontally)
- **Queue Workers:** Multiple Horizon workers
- **Database:** Read replicas supported
- **Caching:** Redis cluster support

---

## 🔧 Development Workflow

### Local Development
- **Docker Compose:** Mandatory (PostgreSQL, Redis, Nginx, PHP-FPM)
- **TDD:** Test-Driven Development (write tests first)
- **Pre-commit Hooks:** Pint, PHPStan, GitLeaks, tests

### Testing Strategy
- **Unit Tests:** Services, Actions, Helpers (fast, isolated)
- **Feature Tests:** API endpoints, integrations (comprehensive)
- **E2E Tests:** Playwright (critical user flows)

### Code Quality Tools
- **Laravel Pint:** Code formatting (PSR-12)
- **PHPStan:** Static analysis (level 5)
- **GitLeaks:** Secret detection
- **Composer Audit:** Security vulnerabilities

---

## 📈 Business Model

### Subscription Plans
- **Free:** 100 requests/month, read-only access
- **Pro:** 10,000 requests/month, AI generation, context tags
- **Enterprise:** Unlimited requests, webhooks, analytics, priority support

### Portfolio/Demo
- Currently uses local API keys (portfolio demonstration)
- Full functionality for demo purposes
- Production-ready code (requires commercial licenses for TMDB)

---

## 🎓 Learning Outcomes

### What I Learned
1. **Architecture Patterns:** Repository, Service Layer, Action, Event-Driven
2. **Laravel Best Practices:** Thin Controllers, Dependency Injection, Jobs
3. **API Design:** RESTful principles, HATEOAS, versioning
4. **Testing:** TDD, Test Pyramid, Mocking strategies
5. **DevOps:** Docker, CI/CD, deployment strategies
6. **Security:** API key management, rate limiting, secret detection

### Challenges Solved
1. **Async Processing:** Event-driven architecture for AI generation
2. **Rate Limiting:** Plan-based rate limiting with Redis
3. **External APIs:** TMDB/TVmaze integration with caching
4. **Multilingual:** Locale-based content generation
5. **Scalability:** Stateless API design for horizontal scaling

---

## 🔐 Security Features

- **API Key Authentication:** Hashed keys, secure storage
- **Rate Limiting:** Plan-based limits with Redis
- **Input Validation:** Form Requests, strict validation
- **Secret Detection:** GitLeaks in pre-commit hooks
- **SQL Injection Prevention:** Eloquent ORM (parameterized queries)
- **XSS Prevention:** Output escaping, JSON responses

---

## 📚 Documentation

### Comprehensive Documentation
- **Business:** Features, Requirements, Subscription Plans
- **Technical:** Architecture, API Specification, Deployment, Integrations
- **QA:** Test Strategy, Manual Test Plans, Automated Tests
- **Legal:** TMDB/TVmaze license requirements

### Code Documentation
- **PHPDoc:** All classes and methods documented
- **README:** Setup instructions, architecture overview
- **API Docs:** OpenAPI specification

---

## 🎯 Project Goals

### Primary Goals
1. **Portfolio Project:** Demonstrate full-stack development skills
2. **Production-Ready Code:** Clean, tested, documented
3. **Best Practices:** SOLID, DRY, TDD, Clean Architecture
4. **Real-World Features:** Subscriptions, rate limiting, async processing

### Future Enhancements
- Stripe/PayPal integration for billing
- GraphQL API
- WebSocket support for real-time updates
- Advanced analytics dashboard
- Multi-tenant support

---

## 💡 Interview Talking Points

### Architecture Decisions
- **Why Thin Controllers?** Separation of concerns, testability, reusability
- **Why Repository Pattern?** Testability, flexibility, query optimization
- **Why Event-Driven?** Scalability, loose coupling, extensibility
- **Why Service Layer?** Business logic centralization, reusability

### Technical Challenges
- **Async Processing:** How to handle long-running AI generation tasks
- **Rate Limiting:** Plan-based rate limiting with Redis sliding window
- **Caching Strategy:** Different TTLs for different data sources
- **External API Integration:** Handling rate limits, errors, retries

### Best Practices
- **TDD:** Write tests first, then implementation
- **Code Quality:** Pint, PHPStan, GitLeaks in pre-commit hooks
- **Documentation:** Comprehensive docs for all stakeholders
- **Security:** API key hashing, rate limiting, secret detection

---

## 📞 Contact & Links

- **Repository:** [GitHub](https://github.com/lukaszzychal/moviemind-api-public)
- **Documentation:** See `docs/` directory
- **API Specification:** `docs/openapi.yaml`
- **Status:** Portfolio/Demo Project (production-ready code)

---

**This project demonstrates:**
- Full-stack development (Backend API)
- Modern PHP/Laravel development
- Clean Architecture principles
- Testing strategies (TDD, Test Pyramid)
- DevOps practices (Docker, CI/CD)
- API design and documentation
- Security best practices

**Ready for production deployment** (with commercial licenses for TMDB).
