# Refactor Comparison – Service vs Events/Jobs

## Current approach
```
GenerateController → AiService → (Mock) closure dispatch
```
- Logic duplicated between mock/real.  
- Large closures hard to test.  
- Limited retry/backoff/logging options.

## Event-driven target
```
GenerateController → Event → Listener → Job
```
- Dedicated jobs with retry/backoff.  
- Clean separation of concerns.  
- Easier monitoring via Horizon.

## Decision matrix
| Aspect | Service layer | Events/Jobs |
|--------|---------------|-------------|
| Mock/Real toggle | ✅ already supported | Requires updating mock path |
| Testability | ⚠️ limited | ✅ per class |
| Observability | ⚠️ basic | ✅ via Horizon |
| Laravel best practice | ⚠️ hybrid | ✅ idiomatic |

**Recommendation:** migrate towards event/job architecture, refactor mock service first, then drop the old closure flow.

**Polish source:** [`../pl/REFACTOR_COMPARISON.md`](../pl/REFACTOR_COMPARISON.md)
