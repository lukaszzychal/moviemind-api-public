# Webhook System - Business Documentation

> **For:** Business Stakeholders, Product Managers  
> **Related Task:** TASK-008  
> **Last Updated:** 2025-01-27

---

## 📖 What is a Webhook System?

A **webhook system** is a mechanism that allows external services (like Stripe, PayPal, or other payment providers) to automatically notify MovieMind API when important events occur, such as:
- A new subscription is created
- A subscription is upgraded or cancelled
- A payment succeeds or fails

Instead of MovieMind API constantly checking ("polling") external services for updates, the external service **pushes** notifications to MovieMind API when events happen.

## 🎯 Business Value

### Why Do We Need Webhooks?

1. **Real-time Updates** ⚡
   - Instant synchronization when subscriptions change
   - No delays in updating user access and permissions
   - Better user experience

2. **Reliability** 🛡️
   - Automatic retry if webhook processing fails
   - No data loss if temporary issues occur
   - Guaranteed delivery of important events

3. **Efficiency** 💰
   - No need to constantly check external services
   - Reduced server load and API calls
   - Lower costs

4. **Audit Trail** 📊
   - Complete history of all webhook events
   - Easy troubleshooting and debugging
   - Compliance and reporting

## 🔄 How It Works

### Simple Flow

```
External Service (Billing Provider) → Event Occurs → Sends Webhook → MovieMind API
                                                              ↓
                                                    Process Event
                                                              ↓
                                                    Update Database
                                                              ↓
                                                    Success ✅
```

### With Retry (If Something Fails)

```
External Service → Webhook → MovieMind API
                              ↓
                    Processing Fails ❌
                              ↓
                    Store for Retry
                              ↓
                    Wait 1 minute → Retry
                              ↓
                    Still Fails? → Wait 5 minutes → Retry
                              ↓
                    Still Fails? → Wait 15 minutes → Retry
                              ↓
                    Success ✅ or Permanent Failure ❌
```

## Notification Webhooks

### Incoming Notification Webhooks

External systems can send notification webhooks to MovieMind API to notify about events such as:
- Generation completed
- Generation failed
- User registered
- User updated

**Business Value:**
- Real-time synchronization with external systems
- Automatic event processing
- Reliable delivery with retry support

### Outgoing Notification Webhooks

MovieMind API can send webhooks to external systems when events occur, such as:
- Movie generation requested
- Person generation requested
- (Future) Generation completed
- (Future) Generation failed

**Business Value:**
- Real-time notifications to external systems
- Integration with third-party services
- Event-driven architecture

## 📊 Key Features

### 1. Automatic Retry

**Problem:** Sometimes webhook processing fails due to temporary issues (network problems, database locks, etc.).

**Solution:** System automatically retries failed webhooks with increasing delays:
- **1st retry:** After 1 minute
- **2nd retry:** After 5 minutes
- **3rd retry:** After 15 minutes

**Business Benefit:** No manual intervention needed for temporary failures. Events are processed automatically when the issue is resolved.

### 2. Duplicate Prevention (Idempotency)

**Problem:** External services might send the same webhook multiple times (network retries, system issues).

**Solution:** Each webhook includes a unique `idempotency_key`. If the same key is received twice, the system recognizes it and doesn't process it again.

**Business Benefit:** Prevents duplicate subscriptions, double-charging, or incorrect data updates.

### 3. Event Tracking

**Problem:** Need to know what happened, when, and why.

**Solution:** All webhook events are stored in the database with:
- Event type and source
- Full payload data
- Processing status
- Error messages (if failed)
- Timestamps

**Business Benefit:** Complete audit trail for compliance, debugging, and reporting.

### 4. Error Handling

**Problem:** When webhooks fail, we need to know why and fix it.

**Solution:** System stores detailed error information:
- Error message
- Error context
- Number of retry attempts
- Final status (failed or permanently failed)

**Business Benefit:** Easy troubleshooting and monitoring of issues.

## 🎯 PRAKTYCZNE PRZYKŁADY - Dlaczego Webhooks są Potrzebne

### ❌ Problem BEZ Webhooks (Jak to działałoby bez webhooks)

**Scenariusz:** Użytkownik kupuje subskrypcję Pro przez billing provider (Stripe/PayPal) za $29/miesiąc.

**BEZ webhooks:**
1. Użytkownik płaci przez billing provider ✅
2. MovieMind API **NIE WIE** o tym, że użytkownik zapłacił
3. MovieMind API musiałoby **co minutę** sprawdzać billing provider: "Czy ten użytkownik zapłacił?"
4. Użytkownik czeka **minutę, 5 minut, godzinę** na dostęp do Pro features
5. **Złe doświadczenie użytkownika** - zapłacił, ale nie ma dostępu

**Koszty:**
- 1000 użytkowników × sprawdzanie co minutę = **1,440,000 zapytań dziennie** do RapidAPI
- Wysokie koszty serwerowe
- Opóźnienia w dostępie do funkcji

### ✅ Rozwiązanie Z Webhooks

**Z webhooks:**
1. Użytkownik płaci na RapidAPI ✅
2. RapidAPI **natychmiast** wysyła webhook do MovieMind API
3. MovieMind API **od razu** tworzy subskrypcję
4. Użytkownik **natychmiast** ma dostęp do Pro features
5. **Świetne doświadczenie** - zapłacił i od razu ma dostęp

**Koszty:**
- Tylko webhook gdy coś się dzieje
- **Zero** niepotrzebnych zapytań
- Natychmiastowy dostęp

---

## 📋 KONKRETNE PRZYKŁADY UŻYCIA

### Przykład 1: Nowy Użytkownik Kupuje Pro Plan

**Sytuacja:**
- Jan Kowalski chce używać MovieMind API do generowania opisów filmów
- Kupuje plan Pro na RapidAPI ($29/miesiąc)
- Chce **natychmiast** generować opisy AI

**Co się dzieje:**

```
1. Jan płaci $29 na RapidAPI
   ↓
2. RapidAPI wysyła webhook:
   POST /api/v1/webhooks/billing
   {
     "event": "subscription.created",
     "data": {
       "rapidapi_user_id": "user-jan-kowalski-123",
       "plan": "pro"
     },
     "idempotency_key": "sub-2025-01-27-jan-123"
   }
   ↓
3. MovieMind API:
   ✅ Tworzy subskrypcję w bazie
   ✅ Ustawia limit: 10,000 zapytań/miesiąc
   ✅ Włącza funkcje: AI generation, context_tags
   ✅ Status: "active"
   ↓
4. Jan może OD RAZU używać Pro features!
```

**Rezultat:**
- Jan zapłacił i **natychmiast** ma dostęp
- Może generować opisy AI
- Ma 10,000 zapytań miesięcznie
- **Zero opóźnień**

---

### Przykład 2: Upgrade z Free do Enterprise

**Sytuacja:**
- Firma "FilmStudio" używa Free plan (100 zapytań/miesiąc)
- Potrzebują więcej - upgrade do Enterprise ($199/miesiąc)
- Chcą **natychmiast** mieć unlimited access

**Co się dzieje:**

```
1. FilmStudio płaci $199 na RapidAPI
   ↓
2. RapidAPI wysyła webhook:
   {
     "event": "subscription.updated",
     "data": {
       "subscription_id": "sub-filmstudio-456",
       "plan": "enterprise"
     },
     "idempotency_key": "upgrade-2025-01-27-filmstudio-456"
   }
   ↓
3. MovieMind API:
   ✅ Aktualizuje subskrypcję: Free → Enterprise
   ✅ Zmienia limit: 100 → unlimited
   ✅ Włącza funkcje: webhooks, analytics, dedicated models
   ✅ Status pozostaje: "active"
   ↓
4. FilmStudio ma OD RAZU unlimited access!
```

**Rezultat:**
- Firma **natychmiast** ma unlimited access
- Może używać wszystkich funkcji Enterprise
- **Zero przestoju** w działaniu

---

### Przykład 3: Płatność się Nie Powiodła

**Sytuacja:**
- Użytkownik ma kartę kredytową która wygasła
- RapidAPI próbuje pobrać $29 za Pro plan
- Płatność **nie przechodzi**

**Co się dzieje:**

```
1. RapidAPI próbuje pobrać płatność ❌
   ↓
2. RapidAPI wysyła webhook:
   {
     "event": "payment.failed",
     "data": {
       "subscription_id": "sub-user-789",
       "reason": "card_declined",
       "amount": 29.00
     },
     "idempotency_key": "payment-failed-2025-01-27-user-789"
   }
   ↓
3. MovieMind API:
   ✅ Loguje błąd płatności
   ✅ Może wysłać email do użytkownika
   ✅ Może ograniczyć dostęp (opcjonalnie)
   ✅ Status subskrypcji: "expired" lub "active" (grace period)
   ↓
4. Użytkownik dostaje powiadomienie
```

**Rezultat:**
- Wiesz **natychmiast** o problemie z płatnością
- Możesz **proaktywnie** skontaktować się z użytkownikiem
- Możesz **automatycznie** ograniczyć dostęp
- **Zero** utraconych przychodów przez nieuwagę

---

### Przykład 4: Użytkownik Anuluje Subskrypcję

**Sytuacja:**
- Użytkownik nie potrzebuje już Pro plan
- Anuluje subskrypcję na RapidAPI
- Chce wrócić do Free plan

**Co się dzieje:**

```
1. Użytkownik anuluje na RapidAPI
   ↓
2. RapidAPI wysyła webhook:
   {
     "event": "subscription.cancelled",
     "data": {
       "subscription_id": "sub-user-999"
     },
     "idempotency_key": "cancel-2025-01-27-user-999"
   }
   ↓
3. MovieMind API:
   ✅ Oznacza subskrypcję jako "cancelled"
   ✅ Ustawia cancelled_at timestamp
   ✅ Status: "cancelled"
   ✅ Użytkownik wraca do Free plan (100 zapytań/miesiąc)
   ↓
4. Użytkownik ma natychmiast Free plan
```

**Rezultat:**
- Subskrypcja **natychmiast** anulowana
- Użytkownik wraca do Free plan
- **Zero** opóźnień w zmianie planu

---

## 💰 DLACZEGO TO JEST WAŻNE DLA BIZNESU

### Bez Webhooks = Problemy

1. **Utrata przychodów:**
   - Użytkownik płaci, ale nie ma dostępu
   - Użytkownik rezygnuje, bo "nie działa"
   - **Utrata klientów**

2. **Wysokie koszty:**
   - Ciągłe sprawdzanie RapidAPI (polling)
   - 1,440,000 zapytań dziennie dla 1000 użytkowników
   - **Wysokie koszty serwerowe**

3. **Złe doświadczenie:**
   - Opóźnienia w dostępie do funkcji
   - Użytkownicy czekają godzinami
   - **Niska satysfakcja**

### Z Webhooks = Korzyści

1. **Więcej przychodów:**
   - Użytkownicy **natychmiast** mają dostęp
   - Mniej rezygnacji
   - **Więcej zadowolonych klientów**

2. **Niższe koszty:**
   - Tylko webhook gdy coś się dzieje
   - **Zero** niepotrzebnych zapytań
   - **Oszczędność kosztów**

3. **Lepsze doświadczenie:**
   - Natychmiastowy dostęp
   - Zero opóźnień
   - **Wysoka satysfakcja**

---

## 🔄 CO BY SIĘ STAŁO BEZ WEBHOOKS?

### Scenariusz: 1000 Użytkowników

**BEZ webhooks:**
- MovieMind API musiałoby sprawdzać **co minutę** czy każdy użytkownik zapłacił
- 1000 użytkowników × 1440 minut dziennie = **1,440,000 zapytań dziennie**
- Każde zapytanie = koszt serwera + koszt API
- **Opóźnienia:** Użytkownik zapłacił, ale MovieMind API dowie się o tym dopiero przy następnym sprawdzeniu (max 1 minuta opóźnienia)

**Z webhooks:**
- MovieMind API dostaje webhook **tylko gdy** użytkownik zapłaci
- 1000 użytkowników × średnio 1 webhook na użytkownika = **~1000 webhooków dziennie**
- **Zero** niepotrzebnych zapytań
- **Natychmiastowy** dostęp - zero opóźnień

**Oszczędność:**
- 1,440,000 zapytań → 1000 webhooków
- **99.93% mniej zapytań**
- **Natychmiastowy** dostęp zamiast max 1 minuta opóźnienia

---

## 💼 Use Cases

### Use Case 1: Subscription Management

**Scenario:** User subscribes to Pro plan on RapidAPI.

**Flow:**
1. RapidAPI sends `subscription.created` webhook
2. MovieMind API receives webhook
3. System creates subscription in database
4. User immediately gets Pro plan access

**Business Impact:** Users get instant access to paid features.

### Use Case 2: Subscription Upgrade

**Scenario:** User upgrades from Free to Pro plan.

**Flow:**
1. RapidAPI sends `subscription.updated` webhook
2. MovieMind API updates subscription
3. User's rate limits and features updated immediately

**Business Impact:** Seamless upgrade experience, no delays.

### Use Case 3: Payment Failure Handling

**Scenario:** User's payment fails.

**Flow:**
1. RapidAPI sends `payment.failed` webhook
2. MovieMind API logs the event
3. System can trigger notifications or limit access

**Business Impact:** Proactive handling of payment issues.

---

## 🎯 PODSUMOWANIE - Dlaczego Webhooks?

| Aspekt | Bez Webhooks | Z Webhooks |
|--------|--------------|------------|
| **Czas dostępu** | Max 1 minuta opóźnienia | Natychmiastowy |
| **Koszty serwerowe** | Wysokie (polling) | Niskie (tylko webhooks) |
| **Liczba zapytań** | 1,440,000/dzień | ~1000/dzień |
| **Doświadczenie użytkownika** | Złe (czeka) | Świetne (natychmiast) |
| **Utrata przychodów** | Tak (rezygnacje) | Nie |
| **Automatyzacja** | Częściowa | Pełna |

**Wniosek:** Webhooks są **niezbędne** dla dobrego doświadczenia użytkownika i efektywności biznesowej.

## 📈 Metrics and Monitoring

### Key Metrics to Monitor

1. **Webhook Processing Rate**
   - How many webhooks processed per hour/day
   - Success rate vs failure rate

2. **Retry Rate**
   - How many webhooks need retry
   - Average retry attempts

3. **Permanent Failures**
   - Webhooks that failed after all retries
   - Requires manual investigation

4. **Processing Time**
   - How long it takes to process a webhook
   - Identify performance issues

### Dashboard (Future)

A monitoring dashboard will show:
- Real-time webhook processing status
- Failed webhooks requiring attention
- Success/failure trends
- Alert notifications

## ⚠️ Important Notes

### For Business Stakeholders

1. **Webhooks are Critical**
   - Webhooks handle billing and subscription events
   - Failures can impact revenue and user experience
   - System is designed to be reliable with automatic retry

2. **Monitoring is Essential**
   - Regularly check for permanently failed webhooks
   - Set up alerts for high failure rates
   - Review retry patterns to identify issues

3. **Idempotency Keys**
   - External services must provide unique keys
   - Prevents duplicate processing
   - Critical for billing accuracy

4. **Testing Environment**
   - Signature verification can be disabled for testing
   - **Never disable in production** - security risk

## 🔒 Security

### Webhook Security

- **HMAC Signature Verification:** All webhooks are verified using cryptographic signatures
- **No API Key Required:** Webhooks use signature verification instead
- **Idempotency:** Prevents duplicate processing and attacks

### Best Practices

- ✅ Always verify webhook signatures in production
- ✅ Use unique idempotency keys
- ✅ Monitor for suspicious activity
- ✅ Keep webhook secrets secure

## 📞 Support and Troubleshooting

### Common Issues

1. **Webhook Not Processing**
   - Check if webhook endpoint is accessible
   - Verify signature verification settings
   - Check logs for errors

2. **Duplicate Subscriptions**
   - Verify idempotency keys are unique
   - Check if webhook was sent multiple times

3. **Permanent Failures**
   - Review error messages
   - Check if data is valid
   - May require manual intervention

### Escalation

If webhooks are failing consistently:
1. Check system logs
2. Review permanently failed webhooks
3. Contact technical team
4. Check external service status

## 🎯 Success Criteria

The webhook system is successful when:
- ✅ 99%+ of webhooks process successfully
- ✅ Failed webhooks retry automatically
- ✅ No duplicate processing occurs
- ✅ Complete audit trail available
- ✅ Monitoring and alerts in place

## 📚 Related Documentation

- [Technical Guide](../knowledge/technical/WEBHOOK_SYSTEM.md)
- [QA Testing Guide](../qa/WEBHOOK_SYSTEM_QA_GUIDE.md)
- [RapidAPI Integration](../../RAPIDAPI_WEBHOOKS.md)

---

**Last Updated:** 2025-01-27  
**Contact:** Technical Team for questions or issues

