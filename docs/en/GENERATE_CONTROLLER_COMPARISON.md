# GenerateController – Version Comparison

## 📊 Current situation
- **Active file:** `GenerateController.php` (used in routes).  
- **Refactored example:** `GenerateController.refactored.php` (prototype, not wired up).

---

## 🔍 Comparison
### Current version (`GenerateController.php`)
```php
class GenerateController extends Controller
{
    public function __construct(private readonly AiServiceInterface $ai) {}

    public function generate(...) {
        $this->ai->queueMovieGeneration($slug, $jobId); // Delegates to service layer
    }
}
```
**Flow**
```
Controller → AiServiceInterface → MockAiService / RealAiService → Events or closure
```
**Pros**
- Works with both mock and real implementations.  
- Switching via `AI_SERVICE` env var.  
- Service encapsulates logic.

**Cons**
- Extra layer (service interface).  
- Controller tied to service abstraction.  
- Mock version still uses legacy closure (no retries/backoff).

---

### Refactored version (`GenerateController.refactored.php`)
```php
class GenerateControllerRefactored extends Controller
{
    public function generate(...) {
        event(new MovieGenerationRequested($slug, $jobId));
    }
}
```
**Flow**
```
Controller → Event → Listener → Job
```
**Pros**
- Direct event usage (idiomatic Laravel).  
- Fewer layers, loose coupling.  
- Supports PERSON/ACTOR flows easily.

**Cons**
- Drops the mock/real switch unless mock is reworked.  
- Requires removing the existing service layer or keeping a thin wrapper.

---

## 🎯 Which one to keep?
### Option 1 – keep current (`GenerateController.php`)
Best if you still rely on `AI_SERVICE` toggle.
- Real driver already dispatches events → jobs (`RealAiService`).  
- Mock driver can stay as closure for backwards compatibility.  
- No immediate migration required.

### Option 2 – adopt refactored version
Best if you want pure events/jobs everywhere.
- Replace controller with refactored version.  
- Refactor/remove `AiServiceInterface`.  
- Update mock implementation to dispatch events instead of closures.  
- Adjust tests.

---

## ✅ Recommendation
For now keep the current controller – it already uses events/jobs when `AI_SERVICE=real`. Plan a follow-up to migrate the mock service to events, then drop the service layer entirely.

**Polish source:** [`../pl/GENERATE_CONTROLLER_COMPARISON.md`](../pl/GENERATE_CONTROLLER_COMPARISON.md)
