# Project Structure

```
open-swiss-buildings-api/
│
├── 📁 src/                              # Symfony application source
│   ├── Application/                     # Application layer
│   │   ├── Contract/                    # Interfaces and contracts
│   │   ├── Messaging/                   # Async messaging (workers)
│   │   └── Web/                         # HTTP controllers
│   ├── Domain/                          # Domain models
│   │   ├── Address/                     # Address entities
│   │   ├── Building/                    # Building entities
│   │   └── Resolving/                   # Resolution logic
│   └── Infrastructure/                  # Infrastructure layer
│       ├── Doctrine/                    # Database mappings
│       ├── Meilisearch/                 # Search engine
│       └── Symfony/                     # Framework config
│
├── 📁 config/                           # Symfony configuration
│   ├── packages/                        # Package-specific configs
│   │   ├── doctrine.yaml               # Database config
│   │   ├── messenger.yaml              # Worker queues
│   │   └── meilisearch.yaml            # Search config
│   └── services.yaml                    # Service definitions
│
├── 📁 docker/                           # Container configuration
│   ├── nginx/                           # Web server config
│   ├── php/                             # PHP-FPM config
│   └── services/                        # S6 service definitions
│
├── 📁 migrations/                       # Database migrations
│   └── Version*.php                     # Schema changes
│
├── 📁 public/                           # Web root
│   └── index.php                        # Entry point
│
├── 📁 init/                             # AI Assistant documentation
│   ├── PROJECT_OVERVIEW.md              # High-level overview
│   ├── PROJECT_STRUCTURE.md             # This file
│   ├── API_ENDPOINTS.md                 # Endpoint reference
│   └── TROUBLESHOOTING.md               # Common issues
│
├── 📁 var/                              # Runtime data (gitignored)
│   ├── cache/                           # Symfony cache
│   ├── data/                            # Downloaded GWR data
│   └── log/                             # Application logs
│
├── 📄 compose.yaml                      # Development Docker setup
├── 📄 compose.final.yaml                # Production Swarm config
├── 📄 compose.override.example.yaml     # Dev override example
│
├── 🔧 deploy-to-swarm.sh                # Automated deployment
├── 🔧 restart-workers.sh                # Manual worker restart
├── 🔧 test-api.sh                       # API test suite
├── 🔧 worker-startup.sh                 # Worker init script
│
├── 📄 .env.production                   # Environment template
├── 📄 .env                              # Active environment (gitignored)
│
├── 📄 rust-api-integration.md           # Rust integration guide
├── 📄 README.md                         # Main documentation
└── 📄 LICENSE                           # MIT License
```

## Key Directories

### `/src` - Application Code
- **Application**: Controllers, messaging, API endpoints
- **Domain**: Business logic, entities, value objects
- **Infrastructure**: External integrations, persistence

### `/config` - Configuration
- **packages**: Service-specific YAML configs
- **services.yaml**: Dependency injection
- **routes.yaml**: API routing

### `/docker` - Container Setup
- **nginx**: Web server configuration
- **php**: PHP-FPM settings
- **services**: S6 process supervisor configs

### `/init` - AI Documentation
Special folder for AI assistants (like Claude) containing:
- Project context and overview
- API endpoint documentation
- Troubleshooting guides
- Architecture decisions

## Important Files

### Deployment Scripts
- `deploy-to-swarm.sh`: Main deployment automation
- `restart-workers.sh`: Manual worker management
- `test-api.sh`: Health check suite

### Docker Configurations
- `compose.final.yaml`: Production Swarm setup
- `compose.yaml`: Local development
- `.env.production`: Environment template

### Documentation
- `README.md`: User-facing documentation
- `rust-api-integration.md`: Integration guide
- `init/*.md`: Technical references

## Data Flow

1. **Government Data** → Downloaded to `/var/data/`
2. **Import Process** → Stored in PostgreSQL
3. **Search Index** → Synchronized to Meilisearch
4. **API Requests** → Processed by controllers
5. **Async Jobs** → Handled by workers
6. **Results** → Returned via API

## Service Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Docker Swarm                       │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌─────────────────┐    ┌──────────────────┐      │
│  │  App Service    │    │ Worker Monitor    │      │
│  │  (Nginx+PHP)    │    │ (Auto-restarts)   │      │
│  └────────┬────────┘    └────────┬──────────┘      │
│           │                       │                  │
│  ┌────────▼────────┐    ┌────────▼──────────┐      │
│  │   PostgreSQL    │    │   Meilisearch     │      │
│  │   (PostGIS)     │    │  (Search Index)   │      │
│  └─────────────────┘    └───────────────────┘      │
│                                                     │
└─────────────────────────────────────────────────────┘
```