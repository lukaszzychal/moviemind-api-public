# Refactoring Proposal – Unifying Mock/Real Selection

## 🔍 Identified issues
1. **Duplicate driver switching** – both `AppServiceProvider` and queue listeners decide between mock/real.  
2. **Tight coupling** – controllers depend on service interface + listeners branch again.  
3. **Inconsistent architecture** – mock path uses closures, real path uses events/jobs.

## ✅ Proposal
- Centralise driver resolution in one place (`AiServiceSelector`).  
- Ensure both mock and real paths dispatch the same jobs via events.  
- Simplify listeners to always dispatch `Generate*Job` without driver checks.  
- Update tests to cover both pathways through configuration.

## 📦 Deliverables
- New selector service.  
- Refactored listeners.  
- Adjusted service container bindings.  
- Documentation updates showing new flow.

**Polish source:** [`../pl/REFACTORING_PROPOSAL.md`](../pl/REFACTORING_PROPOSAL.md)
