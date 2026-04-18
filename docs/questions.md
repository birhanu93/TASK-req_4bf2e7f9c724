## 1. Multi-role sign-in landing behavior

**Question:** If one account has multiple active roles (for example, `supervisor` and `guardian`), which workspace should load immediately after authentication?
**Assumption:** Multi-role users are expected in this organization, and forcing an arbitrary default role risks exposing the wrong operational context first.
**Solution:** Show a post-login role picker listing only assigned roles, then issue a role-scoped session context; users can switch roles later from a workspace switcher with re-authorization checks per action.

---
