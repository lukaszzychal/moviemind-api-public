# Modular Monolith z Feature-Based Instance Scaling

## 📋 Spis Treści

1. [Wprowadzenie](#wprowadzenie)
2. [Koncepcja architektury](#koncepcja-architektury)
3. [Przygotowanie aplikacji](#przygotowanie-aplikacji)
4. [Wdrożenie w różnych środowiskach](#wdrożenie-w-różnych-środowiskach)
   - [Load Balancer (Nginx)](#load-balancer-nginx)
   - [Reverse Proxy (HAProxy)](#reverse-proxy-haproxy)
   - [Docker Swarm](#docker-swarm)
   - [Kubernetes](#kubernetes)
   - [On-Premise (Bare Metal/VMs)](#on-premise-bare-metalvms)
5. [Zarządzanie Feature Flags](#zarządzanie-feature-flags)
6. [Monitoring i obserwowalność](#monitoring-i-obserwowalność)
7. [Best Practices](#best-practices)

---

## Wprowadzenie

**Modular Monolith z Feature-Based Instance Scaling** to architektura skalowania, która pozwala na:

- **Modularny monolit** - aplikacja podzielona na niezależne moduły, ale wdrożona jako jedna jednostka
- **Feature Flags** - kontrola funkcji przez flagi (Laravel Pennant)
- **Selective Scaling** - skalowanie tylko wybranych modułów przez uruchamianie dodatkowych instancji z odpowiednimi flagami
- **Horizontal Scaling** - dodawanie instancji aplikacji zamiast zwiększania zasobów pojedynczej instancji

### Zalety

✅ **Elastyczne skalowanie** - skaluj tylko te moduły, które wymagają większej wydajności  
✅ **Kontrola kosztów** - uruchamiaj tylko potrzebne instancje  
✅ **Zero-downtime deployments** - wdrażaj nowe wersje stopniowo  
✅ **A/B testing** - testuj różne wersje funkcji równolegle  
✅ **Rollback** - szybkie wyłączenie problematycznych funkcji  

### Przykład użycia

```text
┌─────────────────────────────────────────┐
│  Modular Monolith Application          │
│  ┌─────────┐ ┌─────────┐ ┌─────────┐  │
│  │ Movies  │ │ People  │ │ Search  │  │
│  └─────────┘ └─────────┘ └─────────┘  │
│  Feature Flags: [A: ON, B: ON, C: ON] │
└─────────────────────────────────────────┘
         │
         ├─── Instance 1 (Flags: Movies=ON, People=ON, Search=OFF)
         ├─── Instance 2 (Flags: Movies=ON, People=OFF, Search=ON)  ← Skalowanie Search
         └─── Instance 3 (Flags: Movies=OFF, People=ON, Search=ON)  ← Skalowanie People+Search
```

---

## Koncepcja architektury

### Komponenty

1. **Aplikacja Modular Monolith**
   - Wszystkie moduły w jednej aplikacji
   - Feature flags kontrolują dostępność funkcji
   - Wspólna baza danych i cache

2. **Load Balancer / Reverse Proxy**
   - Rozdziela ruch między instancje
   - Health checks
   - Session affinity (opcjonalnie)

3. **Instancje aplikacji**
   - Każda instancja może mieć różne feature flags
   - Współdzielą bazę danych i cache
   - Niezależne skalowanie

4. **Shared Infrastructure**
   - PostgreSQL (wspólna baza danych)
   - Redis (wspólny cache i queue)
   - Storage (wspólne pliki)

### Flow requestu

```text
Client Request
    ↓
Load Balancer (routing based on feature flags)
    ↓
Instance 1 (Feature A=ON, B=OFF)
    ↓
Feature Flag Check
    ↓
If enabled → Process Request
If disabled → Return 404/503 or route to another instance
```

---

## Przygotowanie aplikacji

### 1. Konfiguracja Feature Flags per Instance

Każda instancja powinna mieć unikalny identyfikator i konfigurację feature flags.

#### Opcja A: Zmienne środowiskowe

```bash
# Instance 1
INSTANCE_ID=api-1
FEATURE_FLAGS_OVERRIDE=ai_description_generation:true,ai_bio_generation:true,public_search_advanced:false

# Instance 2
INSTANCE_ID=api-2
FEATURE_FLAGS_OVERRIDE=ai_description_generation:true,ai_bio_generation:false,public_search_advanced:true
```

#### Opcja B: Plik konfiguracyjny

```php
// config/instance-features.php
return [
    'instance_id' => env('INSTANCE_ID', 'default'),
    'features' => [
        'ai_description_generation' => env('FEATURE_AI_DESCRIPTION', true),
        'ai_bio_generation' => env('FEATURE_AI_BIO', true),
        'public_search_advanced' => env('FEATURE_ADVANCED_SEARCH', false),
    ],
];
```

### 2. Health Check Endpoint

Dodaj endpoint, który zwraca status instancji i aktywne feature flags:

```php
// routes/api.php
Route::get('/health/instance', [HealthController::class, 'instance']);
```

```php
// app/Http/Controllers/Api/HealthController.php
public function instance(): JsonResponse
{
    $activeFeatures = [];
    foreach (config('pennant.flags', []) as $name => $config) {
        $activeFeatures[$name] = Feature::active($name);
    }

    return response()->json([
        'instance_id' => env('INSTANCE_ID', 'unknown'),
        'status' => 'healthy',
        'features' => $activeFeatures,
        'timestamp' => now()->toIso8601String(),
    ]);
}
```

### 3. Middleware dla Feature Flags

Opcjonalnie: middleware, który weryfikuje dostępność funkcji przed przetworzeniem requestu:

```php
// app/Http/Middleware/FeatureFlagMiddleware.php
public function handle(Request $request, Closure $next, string $feature): Response
{
    if (!Feature::active($feature)) {
        return response()->json([
            'error' => 'Feature not available on this instance',
            'feature' => $feature,
            'instance_id' => env('INSTANCE_ID'),
        ], 503);
    }

    return $next($request);
}
```

---

## Wdrożenie w różnych środowiskach

### Load Balancer (Nginx)

#### Konfiguracja Nginx z Load Balancing

```nginx
# /etc/nginx/conf.d/moviemind-api.conf

upstream moviemind_api {
    # Instance 1 - wszystkie funkcje
    server api-1:8000 weight=3 max_fails=3 fail_timeout=30s;
    
    # Instance 2 - tylko AI generation
    server api-2:8000 weight=2 max_fails=3 fail_timeout=30s;
    
    # Instance 3 - tylko search i cache
    server api-3:8000 weight=1 max_fails=3 fail_timeout=30s;
    
    # Health check
    keepalive 32;
}

# Routing based on path
server {
    listen 80;
    server_name api.moviemind.com;

    # Health check endpoint
    location /health {
        access_log off;
        proxy_pass http://moviemind_api/up;
        proxy_set_header Host $host;
    }

    # AI generation endpoints → Instance 1, 2
    location ~ ^/api/v1/(generate|movies|people)/.* {
        proxy_pass http://moviemind_api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        
        # Prefer instances with AI features enabled
        proxy_next_upstream error timeout http_503;
    }

    # Search endpoints → Instance 1, 3
    location ~ ^/api/v1/search {
        proxy_pass http://moviemind_api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }

    # Default routing
    location / {
        proxy_pass http://moviemind_api;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

#### Docker Compose z wieloma instancjami

```yaml
version: "3.9"

services:
  nginx:
    image: nginx:1.27-alpine
    container_name: moviemind-nginx
    ports:
      - "8000:80"
    volumes:
      - ./docker/nginx/load-balancer.conf:/etc/nginx/conf.d/default.conf:ro
    depends_on:
      - api-1
      - api-2
      - api-3

  api-1:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    environment:
      INSTANCE_ID: api-1
      FEATURE_AI_DESCRIPTION: "true"
      FEATURE_AI_BIO: "true"
      FEATURE_ADVANCED_SEARCH: "false"
      # ... other env vars
    depends_on:
      - db
      - redis

  api-2:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    environment:
      INSTANCE_ID: api-2
      FEATURE_AI_DESCRIPTION: "true"
      FEATURE_AI_BIO: "false"
      FEATURE_ADVANCED_SEARCH: "true"
      # ... other env vars
    depends_on:
      - db
      - redis

  api-3:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    environment:
      INSTANCE_ID: api-3
      FEATURE_AI_DESCRIPTION: "false"
      FEATURE_AI_BIO: "true"
      FEATURE_ADVANCED_SEARCH: "true"
      # ... other env vars
    depends_on:
      - db
      - redis

  db:
    image: postgres:15
    # ... config

  redis:
    image: redis:7-alpine
    # ... config
```

---

### Reverse Proxy (HAProxy)

#### Konfiguracja HAProxy

```haproxy
# /etc/haproxy/haproxy.cfg

global
    log /dev/log local0
    maxconn 4096
    daemon

defaults
    log global
    mode http
    option httplog
    option dontlognull
    timeout connect 5000ms
    timeout client 50000ms
    timeout server 50000ms

# Frontend
frontend moviemind_frontend
    bind *:80
    default_backend moviemind_backend

    # Health check
    acl is_health_check path_beg /health
    use_backend health_backend if is_health_check

    # Route AI generation to instances with AI features
    acl is_ai_generation path_beg /api/v1/generate /api/v1/movies /api/v1/people
    use_backend ai_backend if is_ai_generation

    # Route search to instances with search features
    acl is_search path_beg /api/v1/search
    use_backend search_backend if is_search

# Backend - All instances (default)
backend moviemind_backend
    balance roundrobin
    option httpchk GET /health/instance
    http-check expect status 200
    
    server api-1 api-1:8000 check inter 5s fall 3 rise 2
    server api-2 api-2:8000 check inter 5s fall 3 rise 2
    server api-3 api-3:8000 check inter 5s fall 3 rise 2

# Backend - AI generation instances
backend ai_backend
    balance roundrobin
    option httpchk GET /health/instance
    http-check expect status 200
    http-check expect string "ai_description_generation.*true"
    
    server api-1 api-1:8000 check inter 5s fall 3 rise 2
    server api-2 api-2:8000 check inter 5s fall 3 rise 2

# Backend - Search instances
backend search_backend
    balance roundrobin
    option httpchk GET /health/instance
    http-check expect status 200
    http-check expect string "public_search_advanced.*true"
    
    server api-2 api-2:8000 check inter 5s fall 3 rise 2
    server api-3 api-3:8000 check inter 5s fall 3 rise 2

# Backend - Health check
backend health_backend
    server api-1 api-1:8000
```

---

### Docker Swarm

#### Stack Definition

```yaml
# docker-stack.yml

version: "3.9"

services:
  nginx:
    image: nginx:1.27-alpine
    ports:
      - "8000:80"
    volumes:
      - ./docker/nginx/load-balancer.conf:/etc/nginx/conf.d/default.conf:ro
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.role == manager
    networks:
      - moviemind_network

  api:
    image: moviemind-api:latest
    environment:
      INSTANCE_ID: "api-${HOSTNAME}"
      FEATURE_AI_DESCRIPTION: "true"
      FEATURE_AI_BIO: "true"
      FEATURE_ADVANCED_SEARCH: "false"
      # ... other env vars
    deploy:
      replicas: 3
      update_config:
        parallelism: 1
        delay: 10s
        failure_action: rollback
      restart_policy:
        condition: on-failure
        delay: 5s
        max_attempts: 3
    networks:
      - moviemind_network
    depends_on:
      - db
      - redis

  api-ai-only:
    image: moviemind-api:latest
    environment:
      INSTANCE_ID: "api-ai-${HOSTNAME}"
      FEATURE_AI_DESCRIPTION: "true"
      FEATURE_AI_BIO: "false"
      FEATURE_ADVANCED_SEARCH: "false"
      # ... other env vars
    deploy:
      replicas: 2
      update_config:
        parallelism: 1
        delay: 10s
      restart_policy:
        condition: on-failure
    networks:
      - moviemind_network
    depends_on:
      - db
      - redis

  api-search-only:
    image: moviemind-api:latest
    environment:
      INSTANCE_ID: "api-search-${HOSTNAME}"
      FEATURE_AI_DESCRIPTION: "false"
      FEATURE_AI_BIO: "false"
      FEATURE_ADVANCED_SEARCH: "true"
      # ... other env vars
    deploy:
      replicas: 2
      update_config:
        parallelism: 1
        delay: 10s
      restart_policy:
        condition: on-failure
    networks:
      - moviemind_network
    depends_on:
      - db
      - redis

  db:
    image: postgres:15
    volumes:
      - db_data:/var/lib/postgresql/data
    deploy:
      replicas: 1
      placement:
        constraints:
          - node.role == manager
    networks:
      - moviemind_network

  redis:
    image: redis:7-alpine
    volumes:
      - redis_data:/data
    deploy:
      replicas: 1
    networks:
      - moviemind_network

networks:
  moviemind_network:
    driver: overlay

volumes:
  db_data:
  redis_data:
```

#### Deployment

```bash
# Initialize Swarm
docker swarm init

# Deploy stack
docker stack deploy -c docker-stack.yml moviemind

# Scale specific service
docker service scale moviemind_api-ai-only=5

# Update service
docker service update --env-add FEATURE_AI_DESCRIPTION=true moviemind_api
```

---

### Kubernetes

#### Deployment Manifest

```yaml
# k8s/deployment.yaml

apiVersion: apps/v1
kind: Deployment
metadata:
  name: moviemind-api
  labels:
    app: moviemind-api
spec:
  replicas: 3
  selector:
    matchLabels:
      app: moviemind-api
  template:
    metadata:
      labels:
        app: moviemind-api
        instance-type: full
    spec:
      containers:
      - name: api
        image: moviemind-api:latest
        ports:
        - containerPort: 8000
        env:
        - name: INSTANCE_ID
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        - name: FEATURE_AI_DESCRIPTION
          value: "true"
        - name: FEATURE_AI_BIO
          value: "true"
        - name: FEATURE_ADVANCED_SEARCH
          value: "false"
        # ... other env vars
        livenessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
        resources:
          requests:
            memory: "256Mi"
            cpu: "250m"
          limits:
            memory: "512Mi"
            cpu: "500m"
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: moviemind-api-ai
  labels:
    app: moviemind-api
    instance-type: ai-only
spec:
  replicas: 2
  selector:
    matchLabels:
      app: moviemind-api
      instance-type: ai-only
  template:
    metadata:
      labels:
        app: moviemind-api
        instance-type: ai-only
    spec:
      containers:
      - name: api
        image: moviemind-api:latest
        ports:
        - containerPort: 8000
        env:
        - name: INSTANCE_ID
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        - name: FEATURE_AI_DESCRIPTION
          value: "true"
        - name: FEATURE_AI_BIO
          value: "false"
        - name: FEATURE_ADVANCED_SEARCH
          value: "false"
        # ... other env vars
        livenessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
---
apiVersion: apps/v1
kind: Deployment
metadata:
  name: moviemind-api-search
  labels:
    app: moviemind-api
    instance-type: search-only
spec:
  replicas: 2
  selector:
    matchLabels:
      app: moviemind-api
      instance-type: search-only
  template:
    metadata:
      labels:
        app: moviemind-api
        instance-type: search-only
    spec:
      containers:
      - name: api
        image: moviemind-api:latest
        ports:
        - containerPort: 8000
        env:
        - name: INSTANCE_ID
          valueFrom:
            fieldRef:
              fieldPath: metadata.name
        - name: FEATURE_AI_DESCRIPTION
          value: "false"
        - name: FEATURE_AI_BIO
          value: "false"
        - name: FEATURE_ADVANCED_SEARCH
          value: "true"
        # ... other env vars
        livenessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 30
          periodSeconds: 10
        readinessProbe:
          httpGet:
            path: /health/instance
            port: 8000
          initialDelaySeconds: 5
          periodSeconds: 5
---
apiVersion: v1
kind: Service
metadata:
  name: moviemind-api
spec:
  selector:
    app: moviemind-api
  ports:
  - protocol: TCP
    port: 80
    targetPort: 8000
  type: ClusterIP
---
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: moviemind-api-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
    nginx.ingress.kubernetes.io/ssl-redirect: "true"
spec:
  ingressClassName: nginx
  rules:
  - host: api.moviemind.com
    http:
      paths:
      - path: /
        pathType: Prefix
        backend:
          service:
            name: moviemind-api
            port:
              number: 80
```

#### Service with Feature-Based Routing

```yaml
# k8s/service-routing.yaml

apiVersion: v1
kind: Service
metadata:
  name: moviemind-api-ai
spec:
  selector:
    app: moviemind-api
    instance-type: ai-only
  ports:
  - protocol: TCP
    port: 80
    targetPort: 8000
---
apiVersion: v1
kind: Service
metadata:
  name: moviemind-api-search
spec:
  selector:
    app: moviemind-api
    instance-type: search-only
  ports:
  - protocol: TCP
    port: 80
    targetPort: 8000
---
apiVersion: networking.k8s.io/v1
kind: Ingress
metadata:
  name: moviemind-api-ingress
  annotations:
    nginx.ingress.kubernetes.io/rewrite-target: /
    nginx.ingress.kubernetes.io/use-regex: "true"
spec:
  ingressClassName: nginx
  rules:
  - host: api.moviemind.com
    http:
      paths:
      # AI generation endpoints → AI instances
      - path: /api/v1/(generate|movies|people)
        pathType: Prefix
        backend:
          service:
            name: moviemind-api-ai
            port:
              number: 80
      # Search endpoints → Search instances
      - path: /api/v1/search
        pathType: Prefix
        backend:
          service:
            name: moviemind-api-search
            port:
              number: 80
      # Default → All instances
      - path: /
        pathType: Prefix
        backend:
          service:
            name: moviemind-api
            port:
              number: 80
```

#### Deployment Commands

```bash
# Apply deployments
kubectl apply -f k8s/deployment.yaml

# Scale deployment
kubectl scale deployment moviemind-api-ai --replicas=5

# Update feature flags
kubectl set env deployment/moviemind-api-ai FEATURE_AI_DESCRIPTION=true

# Rolling update
kubectl rollout restart deployment/moviemind-api

# Check status
kubectl get pods -l app=moviemind-api
kubectl describe deployment moviemind-api
```

---

### On-Premise (Bare Metal/VMs)

#### Systemd Service Files

```ini
# /etc/systemd/system/moviemind-api-1.service

[Unit]
Description=MovieMind API Instance 1
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/moviemind-api
Environment="INSTANCE_ID=api-1"
Environment="FEATURE_AI_DESCRIPTION=true"
Environment="FEATURE_AI_BIO=true"
Environment="FEATURE_ADVANCED_SEARCH=false"
Environment="APP_ENV=production"
Environment="APP_DEBUG=false"
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8001
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

```ini
# /etc/systemd/system/moviemind-api-2.service

[Unit]
Description=MovieMind API Instance 2
After=network.target postgresql.service redis.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/moviemind-api
Environment="INSTANCE_ID=api-2"
Environment="FEATURE_AI_DESCRIPTION=true"
Environment="FEATURE_AI_BIO=false"
Environment="FEATURE_ADVANCED_SEARCH=true"
Environment="APP_ENV=production"
Environment="APP_DEBUG=false"
ExecStart=/usr/bin/php artisan serve --host=0.0.0.0 --port=8002
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

#### Nginx Configuration

```nginx
# /etc/nginx/sites-available/moviemind-api

upstream moviemind_api {
    least_conn;
    
    server 127.0.0.1:8001 max_fails=3 fail_timeout=30s;
    server 127.0.0.1:8002 max_fails=3 fail_timeout=30s;
    server 127.0.0.1:8003 max_fails=3 fail_timeout=30s;
    
    keepalive 32;
}

server {
    listen 80;
    server_name api.moviemind.com;

    root /var/www/moviemind-api/public;

    location / {
        try_files $uri $uri/ @php;
    }

    location @php {
        fastcgi_pass moviemind_api;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }

    location ~ \.php$ {
        fastcgi_pass moviemind_api;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

#### Management Scripts

```bash
#!/bin/bash
# /usr/local/bin/moviemind-scale.sh

INSTANCE_TYPE=$1
REPLICAS=$2

case $INSTANCE_TYPE in
  "ai-only")
    for i in $(seq 1 $REPLICAS); do
      systemctl start moviemind-api-ai-${i}.service
    done
    ;;
  "search-only")
    for i in $(seq 1 $REPLICAS); do
      systemctl start moviemind-api-search-${i}.service
    done
    ;;
  *)
    echo "Unknown instance type: $INSTANCE_TYPE"
    exit 1
    ;;
esac
```

---

## Zarządzanie Feature Flags

### Database-Driven Flags

Laravel Pennant używa bazy danych do przechowywania flag. Dla każdej instancji możesz ustawić różne wartości:

```sql
-- Instance 1: Wszystkie funkcje włączone
INSERT INTO features (name, scope_type, scope_id, value, created_at, updated_at)
VALUES 
  ('ai_description_generation', 'instance', 'api-1', true, NOW(), NOW()),
  ('ai_bio_generation', 'instance', 'api-1', true, NOW(), NOW()),
  ('public_search_advanced', 'instance', 'api-1', false, NOW(), NOW());

-- Instance 2: Tylko AI description
INSERT INTO features (name, scope_type, scope_id, value, created_at, updated_at)
VALUES 
  ('ai_description_generation', 'instance', 'api-2', true, NOW(), NOW()),
  ('ai_bio_generation', 'instance', 'api-2', false, NOW(), NOW()),
  ('public_search_advanced', 'instance', 'api-2', true, NOW(), NOW());
```

### API do zarządzania flagami

```php
// routes/api.php
Route::prefix('admin')->group(function () {
    Route::get('/flags', [AdminFlagController::class, 'index']);
    Route::post('/flags/{name}/toggle', [AdminFlagController::class, 'toggle']);
    Route::post('/flags/{name}/instance/{instanceId}', [AdminFlagController::class, 'setForInstance']);
});
```

### Automatyczne zarządzanie przez zmienne środowiskowe

```php
// app/Providers/AppServiceProvider.php
public function boot(): void
{
    // Override feature flags from environment
    $instanceId = env('INSTANCE_ID');
    
    foreach (config('pennant.flags', []) as $name => $config) {
        $envKey = 'FEATURE_' . strtoupper(str_replace(['-', '_'], '_', $name));
        $envValue = env($envKey);
        
        if ($envValue !== null && $instanceId) {
            Feature::for('instance:' . $instanceId)->activate($name, filter_var($envValue, FILTER_VALIDATE_BOOLEAN));
        }
    }
}
```

---

## Monitoring i obserwowalność

### Metrics do monitorowania

1. **Instance Health**
   - Response time per instance
   - Error rate per instance
   - Active feature flags per instance

2. **Feature Flag Usage**
   - Requests per feature flag
   - Success rate per feature flag
   - Performance impact per feature flag

3. **Load Distribution**
   - Requests per instance
   - CPU/Memory usage per instance
   - Queue depth per instance

### Prometheus Metrics

```php
// app/Http/Middleware/MetricsMiddleware.php
public function handle(Request $request, Closure $next): Response
{
    $startTime = microtime(true);
    $response = $next($request);
    $duration = microtime(true) - $startTime;

    // Export metrics
    $instanceId = env('INSTANCE_ID', 'unknown');
    $featureFlags = $this->getActiveFeatures();

    // Log to Prometheus
    \Log::info('request_metrics', [
        'instance_id' => $instanceId,
        'duration' => $duration,
        'status' => $response->getStatusCode(),
        'features' => $featureFlags,
    ]);

    return $response;
}
```

### Health Check Dashboard

```php
// routes/api.php
Route::get('/admin/instances', [AdminInstanceController::class, 'index']);
```

```php
// app/Http/Controllers/AdminInstanceController.php
public function index(): JsonResponse
{
    $instances = [
        'api-1' => $this->getInstanceStatus('api-1'),
        'api-2' => $this->getInstanceStatus('api-2'),
        'api-3' => $this->getInstanceStatus('api-3'),
    ];

    return response()->json([
        'instances' => $instances,
        'total_instances' => count($instances),
        'healthy_instances' => collect($instances)->where('status', 'healthy')->count(),
    ]);
}

private function getInstanceStatus(string $instanceId): array
{
    // Check instance health
    $health = Http::timeout(2)->get("http://{$instanceId}:8000/health/instance");
    
    return [
        'instance_id' => $instanceId,
        'status' => $health->successful() ? 'healthy' : 'unhealthy',
        'features' => $health->json('features', []),
        'last_check' => now()->toIso8601String(),
    ];
}
```

---

## Best Practices

### 1. Idempotentność

- Wszystkie operacje powinny być idempotentne
- Feature flags nie powinny zmieniać logiki biznesowej, tylko dostępność funkcji

### 2. Graceful Degradation

- Jeśli funkcja jest wyłączona, zwracaj czytelny błąd (503 Service Unavailable)
- Nie crashuj aplikacji, jeśli feature flag nie jest dostępny

### 3. Database Consistency

- Wspólna baza danych wymaga synchronizacji
- Używaj transakcji dla operacji, które modyfikują dane
- Rozważ read replicas dla skalowania odczytów

### 4. Cache Strategy

- Wspólny Redis cache dla wszystkich instancji
- Invalidate cache po zmianie danych
- Używaj cache tags dla łatwego zarządzania

### 5. Session Management

- Jeśli używasz sesji, używaj Redis/database sessions
- Unikaj sticky sessions (session affinity) jeśli możliwe
- Używaj stateless authentication (JWT tokens)

### 6. Deployment Strategy

- Rolling updates - aktualizuj instancje stopniowo
- Blue-green deployment - wdrażaj nową wersję równolegle
- Canary deployment - wdrażaj nową wersję dla części ruchu

### 7. Monitoring

- Monitoruj health checks wszystkich instancji
- Alertuj, gdy instancja jest unhealthy
- Trackuj metryki per instance i per feature flag

### 8. Scaling Decisions

- Skaluj tylko te moduły, które wymagają większej wydajności
- Monitoruj metryki przed podjęciem decyzji o skalowaniu
- Używaj auto-scaling jeśli dostępne (Kubernetes HPA, Docker Swarm)

---

## Podsumowanie

Modular Monolith z Feature-Based Instance Scaling to elastyczne podejście do skalowania aplikacji, które pozwala na:

- **Selektywne skalowanie** - skaluj tylko potrzebne moduły
- **Kontrola kosztów** - uruchamiaj tylko potrzebne instancje
- **Zero-downtime** - wdrażaj nowe wersje bez przestojów
- **A/B testing** - testuj różne wersje funkcji równolegle

Kluczowe elementy:

1. **Feature Flags** - kontrola dostępności funkcji
2. **Load Balancer** - rozdzielanie ruchu między instancje
3. **Health Checks** - monitorowanie stanu instancji
4. **Shared Infrastructure** - wspólna baza danych i cache
5. **Monitoring** - obserwowalność i metryki

---

## Referencje

- [Laravel Pennant Documentation](https://laravel.com/docs/pennant)
- [Nginx Load Balancing](https://nginx.org/en/docs/http/load_balancing.html)
- [HAProxy Configuration](http://www.haproxy.org/#docs)
- [Docker Swarm](https://docs.docker.com/engine/swarm/)
- [Kubernetes Deployments](https://kubernetes.io/docs/concepts/workloads/controllers/deployment/)

---

**Autor:** MovieMind API Team  
**Data:** 2025-01-28  
**Wersja:** 1.0
