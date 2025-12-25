# Docker Distroless Migration (TASK-019)

## Status: ⚠️ Deferred

This document describes the planned migration from Alpine-based Docker images to Distroless images for improved security. **This migration is currently deferred** due to technical complexity.

## Why Distroless?

- **Reduced attack surface**: No shell, package manager, or unnecessary binaries
- **Smaller image size**: Only runtime dependencies
- **Better security**: Minimal base reduces vulnerabilities

## Current Architecture

The production Dockerfile uses a multi-stage build:
1. **Base stage**: Alpine-based (`php:8.3-fpm-alpine`), installs PHP, extensions, Nginx, Supervisor
2. **Builder stage**: Installs Composer dependencies
3. **Local stage**: PHP-FPM only (for docker-compose with separate Nginx)
4. **Production stage**: PHP-FPM + Nginx + Supervisor (Alpine-based)

## Challenges with Distroless Migration

### 1. Library Compatibility

- **Alpine uses musl libc**: The current base image is Alpine-based, which uses musl libc
- **Distroless uses glibc**: Distroless images are Debian-based and use glibc
- **Incompatibility**: Binaries compiled for Alpine (musl) cannot run on Distroless (glibc) without recompilation

### 2. Supervisor Dependency

- **Supervisor requires Python**: The current setup uses Supervisor to manage PHP-FPM and Nginx
- **Distroless Python variant**: Would require `distroless/python3-debian12`
- **Complexity**: Need to copy all Python dependencies and Supervisor binaries

### 3. Shared Libraries

- **PHP extensions**: Require various shared libraries (libpq, libssl, libcrypto, etc.)
- **Nginx dependencies**: Requires additional libraries
- **Copy complexity**: Need to identify and copy all required shared libraries from Alpine to Distroless

## Proposed Solution

### Option 1: Full Distroless Migration (Complex)

1. **Rebuild PHP from Debian base**: Use `php:8.3-fpm` (Debian) instead of Alpine
2. **Copy to Distroless**: Copy PHP, Nginx, Supervisor, and all dependencies
3. **Python wrapper**: Use Python script to replace bash entrypoint scripts

**Pros:**
- Full Distroless benefits (minimal attack surface)
- Best security posture

**Cons:**
- Very complex implementation
- Requires rebuilding PHP from Debian base
- Large migration effort
- Potential runtime issues

### Option 2: Minimal Alpine (Current + Optimization)

1. **Keep Alpine base**: Continue using Alpine for production
2. **Remove unnecessary tools**: Remove bash, git, curl, wget from production stage
3. **Multi-stage optimization**: Copy only runtime artifacts

**Pros:**
- Minimal changes required
- Maintains compatibility
- Easier to maintain

**Cons:**
- Still includes Alpine base (larger than Distroless)
- Less secure than Distroless

### Option 3: Hybrid Approach

1. **Local/Dev**: Keep Alpine (needs tools for development)
2. **Production**: Migrate to Distroless gradually
3. **Separate Dockerfiles**: Different Dockerfiles for different environments

**Pros:**
- Best of both worlds
- Gradual migration

**Cons:**
- More complex maintenance
- Two different base images

## Current Status

**Decision**: Defer Distroless migration due to:
1. High complexity of library compatibility
2. Supervisor dependency on Python
3. Risk of breaking production deployments
4. Limited security benefit vs. effort ratio

**Future Work**:
- Monitor Distroless ecosystem for PHP/Nginx support
- Consider alternative process managers (systemd, custom init)
- Evaluate Debian-based PHP images for easier Distroless migration

## References

- [Distroless Images](https://github.com/GoogleContainerTools/distroless)
- [Alpine vs. Distroless](https://github.com/GoogleContainerTools/distroless#why-should-i-use-distroless-images)
- [PHP on Distroless](https://github.com/GoogleContainerTools/distroless/issues/86)

---

**Last Updated**: 2025-01-27  
**Task**: TASK-019  
**Status**: ⏳ PENDING (Deferred)

