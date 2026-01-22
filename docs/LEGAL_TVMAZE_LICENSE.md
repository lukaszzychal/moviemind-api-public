# TVmaze License Information

## ✅ Commercial Use Allowed

**TVmaze API is free and allows commercial use under Creative Commons Attribution-ShareAlike (CC BY-SA) license.**

---

## 📋 License Details

### License Type

**Creative Commons Attribution-ShareAlike 4.0 (CC BY-SA 4.0)**

- **Full License Text:** https://creativecommons.org/licenses/by-sa/4.0/
- **Commercial Use:** ✅ **ALLOWED**
- **Cost:** ✅ **FREE** (public API)

---

## 📝 Attribution Requirements

### Required Attribution

You must provide attribution to TVmaze:

1. **Source Credit:**
   - Credit TVmaze as the source of data
   - Include a link to TVmaze in your application/website

2. **Link Format:**
   - You can use URLs from the API for linking
   - Example: Link to show page: `https://www.tvmaze.com/shows/{show_id}`

### ShareAlike Requirement

**Important:** If you redistribute TVmaze data or derivatives:

- You must use the same license (CC BY-SA 4.0)
- This applies only if you **redistribute** data (e.g., export to file, share via API)
- If you only use data internally (no redistribution), ShareAlike does not apply

---

## 🔧 Technical Requirements

### Rate Limiting

- **Minimum:** 20 requests per 10 seconds per IP
- **HTTP 429:** Returned when rate limit is exceeded
- **Recommendation:** Implement retry logic with backoff

### Caching

- ✅ **Allowed:** Caching is permitted (even long-term)
- **Images:** Can be cached indefinitely (URLs do not change)

### CORS

- ✅ **Enabled:** Can be used directly in web applications

---

## 🚀 Enterprise API (Optional)

If you need:
- Higher request limits
- SLA (Service Level Agreement)
- Additional features
- Different license (without ShareAlike)

**Contact:** TVmaze sales department

---

## 📚 Premium Membership (Optional)

**User API Features:**
- Access to user data (followed shows, watched episodes, votes)
- Read-write access to user account
- **Requires:** Premium membership

---

## 🔗 Official Resources

- **API Documentation:** https://www.tvmaze.com/api
- **License:** https://creativecommons.org/licenses/by-sa/4.0/
- **Website:** https://www.tvmaze.com

---

## 📚 Related Documentation

- **Detailed Analysis:** `docs/knowledge/technical/API_LEGAL_ANALYSIS_TMDB_TVMAZE.md`
- **Project README:** `README.md`

---

## 💡 Implementation Notes

This project uses TVmaze API for:
- TV Series verification (`TvmazeVerificationService`)
- TV Shows verification (`TvmazeVerificationService`)

**Attribution is handled in:**
- API responses (if required)
- Application documentation
- README files

---

**Last Updated:** 2025-01-21  
**Project:** MovieMind API (Portfolio/Demo)
