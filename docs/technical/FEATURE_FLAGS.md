# Feature Flags Architecture (Modular Monolith Support)

This document describes the Feature Flag system implemented to support **Modular Monolith** architecture with **Instance Specialization**.

## 1. Overview
The system allows enabling/disabling features dynamically while respecting infrastructure constraints. It uses a **Layered Resolution Strategy** that prioritizes Environment Variables (for scaling) over Database Toggles (for business agility).

### The Hierarchy
The effective value of a feature is resolved in this order (highest priority first):

1.  **Environment Force** (`_FORCE`): Used to permanently disable/enable modules on specific instances (e.g., "Web Node" vs "Worker Node"). **Locks the UI.**
2.  **Database Toggle** (Filament UI): Runtime toggles managed by Admins.
3.  **Environment Default** (`_DEFAULT`): Initial state for fresh deployments.
4.  **Code Default**: Fallback value defined in the codebase.

## 2. Configuration (`config/features.php`)
All features are defined in `api/config/features.php`.

```php
return [
    'ai_description_generation' => [
        'default' => env('FEATURE_AI_DESCRIPTION_GENERATION', true), // Soft Default
        'force'   => env('FEATURE_AI_DESCRIPTION_GENERATION_FORCE'), // Hard Lock
        'name'    => 'AI Description Generation',
        'description' => 'Enables AI-generated movie/series descriptions.',
        'category' => 'core_ai',
    ],
];
```

## 3. Instance Scaling (Modular Monolith)
To scale specific modules independently (e.g., Worker vs API), use the `force` configuration in your `.env` file or container environment variables.

### Example: Split Deployment
**Instance A (API only)**:
```dotenv
# Force DISABLE background processing to save CPU/Memory
FEATURE_AI_DESCRIPTION_GENERATION_FORCE=false
FEATURE_VIDEO_PROCESSING_FORCE=false
```

**Instance B (Worker only)**:
```dotenv
# Force ENABLE background processing
FEATURE_AI_DESCRIPTION_GENERATION_FORCE=true
FEATURE_VIDEO_PROCESSING_FORCE=true
```

> [!NOTE]
> When `_FORCE` is set, the switch in the Admin Panel (Filament) will be **Locked** and show a "Locked" icon.

## 4. Usage in Code

checks are performed using standard Laravel Pennant facade or helper methods (as `BaseFeature` handles the resolution logic).

```php
use Laravel\Pennant\Feature;

if (Feature::active('ai_description_generation')) {
    // Execute AI logic
}
```

## 5. Adding a New Feature
1.  Add the feature definition to `api/config/features.php`.
2.  Create a Feature class extending `App\Features\BaseFeature` (optional, if complex logic needed).
3.  Add corresponding `.env` variables to `env/*.env.example`.

## 6. Testing
Tests should account for the `BaseFeature` logic.
- **Unit Tests**: explicitly call `Feature::activate()` or `Feature::deactivate()` to mock the state, or mock the `Config` facade if testing the resolution logic itself.
- **Note**: In `generate_movie_jobe_test`, we explicitly deactivate locking to ensure deterministic "append" behavior during tests.

## 7. Admin Panel
Manage features at `/admin/features`.
- **Enabled (Green)**: Feature is active.
- **Disabled (Red)**: Feature is inactive.
- **Lock Icon**: Indicates the state is controlled by Environment Variable (`_FORCE`) and cannot be changed via UI.
