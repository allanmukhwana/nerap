## Inspiration

The idea for NERAP Cloud was born from a devastating pattern — not a single tragedy, but a **thousand invisible ones**.

During research into Kenya's snakebite crisis, a recurring story emerged: patients arriving at hospitals hours after being bitten, having visited two or three facilities that had no antivenom, each transfer eating away at survival odds. The antivenom existed somewhere in the country. Nobody knew where.

The same story repeated itself with O-negative blood, with rabies immunoglobulin, with functional ICU beds during the COVID-19 surge, with emergency shelters after the 2024 Kenya floods. The resources were there. **The information was not.**

We were inspired by **Ushahidi** — born in Kenya, powered by the crowd, adopted by the world — which proved that a simple information-sharing platform built for a specific African crisis could become a globally transformative tool. We asked: *What if Ushahidi's crowdsourcing spirit met modern WhatsApp reach and real-time data visualization?*

We were also inspired by the frontline healthcare workers who built informal WhatsApp groups among themselves — nurses texting nurses across counties asking *"Do you have any RIG?"* — essentially inventing a manual version of NERAP out of desperate necessity. **They showed us the solution was already hiding in plain sight.** We simply needed to formalize it, verify it, visualize it, and scale it.

NERAP Cloud 2.0 is our answer to the question: *What if every family had the same network as a well-connected doctor?*

---

## What It Does

NERAP Cloud 2.0 delivers a **unified emergency resource intelligence ecosystem** with five core capabilities:

### 1. Instant WhatsApp Resource Search
Any user — regardless of technical literacy — sends a WhatsApp message to the NERAP number. Through an intuitive keyword and menu system, they receive:
- Nearest verified facilities with the requested resource
- Google Maps directions link
- Direct contact number of the facility
- Timestamp of last verified stock confirmation
- Alerts if stock is critically low or unconfirmed

**Example interaction:**
```
User: "ANTIVENOM"
NERAP: " Antivenom Search
       1. Polyvalent (Snake)
       2. Scorpion
       3. Spider
       Reply with number:"

User: "1"
NERAP: " 3 Facilities found near Nairobi:
       1️⃣ KNH - CONFIRMED STOCK (Updated 2hrs ago)
          📞 +254-20-2726300
          📍 maps.google.com/...
       2️⃣ Aga Khan Hospital - LOW STOCK
       3️⃣ MP Shah - UNCONFIRMED (Last update: 3 days)
       
       🔔 Reply ALERT to get notified of stock changes."
```

###  2. Live Interactive Resource Map
The public web dashboard at **www.nerap.cloud** displays a **Google Maps-powered live view** of:
- Color-coded facility pins (🟢 Confirmed Stock / 🟡 Low Stock / 🔴 Out of Stock / ⚫ Unverified)
- Clickable facility cards with full resource inventory details
- Filterable by resource type, region, and verification status
- Animated stock flow overlays during active disaster events
- Heat maps showing resource density and critical gap zones

### 📊 3. Real-Time Analytics Dashboard
**Chart.js** visualizations give health officials and decision-makers live insight through:
- **Line Charts:** Stock trend analysis and depletion forecasting for critical items
- **Bar Charts:** Facility-by-facility comparison of resource availability
- **Doughnut Charts:** Regional breakdown of resource coverage percentages
- **Heat Maps:** Geographic concentration of critical shortages
- **Live Counters:** Total verified facilities, active alerts, resources tracked, and last update timestamp — all refreshing dynamically

###  4. WhatsApp Crowdsourced Reporting & Facility Updates
Health facility staff can submit inventory updates directly via WhatsApp using a structured reporting menu — no login, no portal, no training required. Field reporters during humanitarian crises can log new shelter locations, water distribution points, or mobile clinic positions instantly. All submissions enter the moderation queue automatically.

###  5. Targeted WhatsApp Broadcast Alerts
Subscribed users — doctors, nurses, ambulance coordinators, disaster managers — receive **proactive WhatsApp push alerts** when:
- A resource they've searched for becomes available nearby
- A critical shortage is confirmed in their region
- A new emergency shelter or distribution point opens
- A humanitarian event is declared in their area

---

## How We Built It

*(Vanilla PHP · Bootstrap 5 · jQuery · Chart.js · Google Maps API)*

Building NERAP Cloud 2.0 required careful architectural decisions to maximize capability while maintaining simplicity, speed, and deployability on affordable infrastructure.

###  System Architecture Overview

```
[WhatsApp User] ←→ [WhatsApp Cloud API]
                         ↓
              [PHP Webhook Handler]
                    ↙        ↘
        [MySQL Database]   [Moderation Queue]
                ↓                  ↓
        [Map & Chart        [Moderator Dashboard]
         Dashboard]              (Bootstrap + jQuery)
        (Google Maps
         + Chart.js)
```

###  Backend: Vanilla PHP

**PHP handled everything on the server side — chosen for its near-universal hosting support and zero framework overhead:**

- **Webhook Receiver (`webhook.php`):** Listens for incoming WhatsApp messages from the Meta Cloud API. Parses JSON payloads, identifies message type (text, button reply, location), and routes to the appropriate handler function.

- **Conversation State Manager (`session_handler.php`):** Manages multi-step WhatsApp conversations using MySQL-stored session states keyed to phone numbers. Enables the menu-driven interaction flow without requiring any client-side memory.

- **Resource Query Engine (`resource_query.php`):** On receiving a keyword, queries the MySQL database for facilities within a configurable radius (using Haversine formula for geospatial distance calculation) filtered by resource type and verification status. Returns ranked results.

- **WhatsApp API Wrapper (`whatsapp_api.php`):** A lightweight PHP class wrapping the Meta Cloud API's HTTP endpoints. Handles sending text messages, interactive list messages (menus), button messages, and broadcast notifications using cURL.

- **Moderation API (`moderation_api.php`):** RESTful endpoints consumed by the moderator dashboard. Exposes approve/reject/flag actions that update the database and trigger appropriate WhatsApp responses to the original submitter.

- **Alert Scheduler (`alert_cron.php`):** Runs as a cron job every 15 minutes. Checks for stock status changes and dispatches WhatsApp broadcast messages to relevant subscribers via batch API calls.

- **Dashboard Data API (`dashboard_api.php`):** Returns JSON-formatted aggregated statistics — stock counts by region, facility status distributions, historical trend data — consumed by Chart.js on the frontend.

###  Database: MySQL

Core tables designed for speed and flexibility:

```sql
facilities          -- Registered health facilities + coordinates
resources           -- Resource types (antivenom, blood type, etc.)
facility_resources  -- Junction table: stock levels per facility
submissions         -- Raw incoming reports (WhatsApp + web)
moderation_log      -- Audit trail of all moderation decisions
wa_sessions         -- WhatsApp conversation state per phone number
subscribers         -- Alert subscription preferences per user
alert_log           -- Sent alerts for deduplication
```

Geospatial queries use indexed `latitude/longitude` columns with the **Haversine formula** implemented in SQL for fast proximity searches without requiring PostGIS.

###  Frontend: Bootstrap 5 + jQuery

**The public map dashboard and moderator panel were built with Bootstrap 5 for responsive layout and jQuery for dynamic interactions:**

- **Bootstrap 5 Grid & Components:** Responsive sidebar layout, facility detail cards, status badge components (color-coded availability), modal dialogs for facility detail views, and a mobile-optimized navigation structure ensuring the dashboard is usable on tablets in field conditions.

- **jQuery AJAX Polling:** The dashboard uses `$.ajax()` calls on a 30-second interval to fetch fresh data from `dashboard_api.php` without full page reloads, keeping the map and charts live. jQuery also handles all moderator dashboard interactions — approving submissions with a single button click that fires an AJAX request and updates the UI instantly.

- **Dynamic Filter Controls:** jQuery-powered filter panels allow users to toggle resource types, filter by region, and switch between map views — all updating both the Google Map markers and Chart.js panels simultaneously without page reload.

### Mapping: Google Maps JavaScript API

**Google Maps powers the core visual experience of the platform:**

- **Custom Marker Clustering:** Facilities are rendered as custom SVG markers color-coded by stock status. At high zoom-out levels, the **MarkerClusterer library** groups nearby facilities into count bubbles, preventing visual overload in dense urban areas.

- **Info Windows:** Clicking any marker opens a rich Bootstrap-styled info window overlay showing: facility name, all tracked resources with status indicators, last verification timestamp, contact details, and a "Get Directions" button.

- **Heatmap Layer:** Using the **Google Maps Visualization Library**, a toggleable heat map layer renders resource scarcity density — dark red zones indicate areas with critically low coverage, enabling at-a-glance identification of strategic gaps.

- **Dynamic Bounds Fitting:** When a WhatsApp search result is viewed on the web, the map automatically pans and zooms to fit all result facilities within the viewport.

- **Disaster Event Overlays:** During declared humanitarian events, affected zone polygons are drawn on the map (using GeoJSON boundaries) with shelter and distribution point markers overlaid within the zone.

###  Data Visualization: Chart.js

**Chart.js provides the analytics intelligence layer of the dashboard:**

- **Stock Trend Line Charts:** Historical stock levels for each resource category plotted over 7/30/90-day windows. Includes a forecasted depletion line calculated from the rate-of-change in recent reports.

- **Regional Coverage Doughnut Charts:** Per-region breakdown showing the percentage of facilities in each stock status category (confirmed / low / out / unverified) — gives health officials an instant coverage snapshot.

- **Facility Comparison Bar Charts:** Side-by-side bar chart comparing stock levels across all facilities for a selected resource, sortable by availability or geography.

- **Alert Activity Histogram:** Bar chart showing alert volume by day — useful for identifying when information demand spikes correlate with real-world events.

- **Live Summary Counters:** jQuery-animated count-up numbers on the dashboard header display total verified facilities, resources tracked, alerts sent in the last 24 hours, and seconds since last update.

All Chart.js instances subscribe to the same AJAX polling cycle as the map, ensuring charts and map always reflect the same data snapshot.

###  WhatsApp Integration: Meta Cloud API

**The WhatsApp Business Cloud API was integrated via PHP without any SDK dependency:**

- **Webhook Verification:** GET request handler for Meta's webhook verification challenge during setup.
- **Inbound Message Processing:** POST handler parsing the `messages` array from webhook payloads, supporting text, interactive replies (button/list), and location message types.
- **Interactive Message Types Used:**
  - `interactive list` messages for resource category menus (supports up to 10 items — used for module selection)
  - `interactive button` messages for binary confirmations (Subscribe to alerts? Yes/No)
  - `text` messages with embedded Google Maps links for final search results
- **Template Messages:** Pre-approved WhatsApp message templates used for broadcast alert notifications (required by Meta for proactive outbound messaging).
- **Media Messages:** During disaster events, facility submission flow supports WhatsApp image attachments — a photo of a physical stock sheet — which is stored and attached to the moderation queue item for human review.

---

## Challenges We Ran Into

Building NERAP Cloud 2.0 was not without significant friction. Several challenges tested both our technical approach and our assumptions about user behavior.

###  1. WhatsApp Session State Without a Framework
The most technically complex challenge was managing **multi-step conversational state** in stateless PHP. When a user is mid-flow through a resource search — they've selected "Antivenom" and are now choosing the type — the next incoming webhook message has no inherent context. We had to build a lightweight **state machine stored in MySQL**, keying conversation state to the user's phone number with a timestamp-based session expiry. Early iterations had race conditions when messages arrived in rapid succession; we resolved this with MySQL row-level locking (`SELECT ... FOR UPDATE`) on session reads.

###  2. Data Freshness vs. Verification Integrity
A fundamental tension exists at the heart of NERAP: **speed vs. accuracy**. A stock update that takes 24 hours to pass through moderation is potentially useless in an acute emergency. Conversely, displaying unverified data could send a patient to a facility that has already used its last unit. We resolved this by introducing a **tiered verification display system** — verified updates show as confirmed (green), recently submitted but unverified updates show as "Reported — Pending Verification" (yellow/amber), clearly communicating uncertainty without suppressing potentially life-saving information entirely.

###  3. Geospatial Performance at Scale
The Haversine distance query works efficiently for small datasets but degrades significantly as the facility database grows. Early testing with simulated national-scale data (2,000+ facilities) revealed unacceptable query times. We resolved this by implementing a **bounding box pre-filter** — first querying only facilities within a rough lat/lng square before applying the precise Haversine formula — reducing the result set for the expensive calculation by over 95%.

### 4. WhatsApp Message Rate Limits & Broadcast Throttling
Meta's Cloud API enforces strict per-second messaging rate limits. During a simulated mass-alert scenario (500 subscribers in one region), naïve sequential API calls immediately hit rate limit errors. We redesigned the alert dispatcher with a **queue-based batch processor** — grouping recipients into batches of 20, with 1-second delays between batches — and added exponential backoff retry logic for failed sends.

### 5. Multilingual Conversation Design
Designing conversation flows that work naturally in English, Swahili, Somali, and Amharic — across users with vastly different literacy levels — required significant iteration. Keyword matching needed to be **fuzzy and culturally aware** (e.g., recognizing "damu" as the Swahili word for blood). We implemented a simple keyword synonym table in MySQL and used PHP's `similar_text()` function for fuzzy matching, significantly improving response accuracy for non-English queries.

---

## Accomplishments That We're Proud Of

###  A Fully Functional WhatsApp-to-Map Pipeline
The complete loop — **WhatsApp message in → PHP webhook → database query → WhatsApp response out** — works end-to-end in under 3 seconds on a standard shared hosting environment. Watching a text message transform into a verified, map-linked resource recommendation in real time was our most tangible "it's real" moment.

### Zero-Training User Interface
We tested the WhatsApp flow with individuals who had never heard of NERAP — a market trader, a secondary school student, a grandmother — and all successfully located a resource within three message exchanges, without any instruction. The conversation design is intuitive enough to require no onboarding.

### The Live Dashboard's Visual Impact
The moment we first loaded the Google Maps dashboard with live color-coded markers, the heatmap overlay, and the Chart.js analytics panels updating in real time — it was immediately obvious that this was a tool for **commanding situational awareness**, not just viewing data. The visual contrast between green-marker clusters in Nairobi and red-marker zones in arid northern counties made the equity gap viscerally visible.

### Moderation Queue → WhatsApp Alert Loop
The fully automated pipeline from moderation approval to WhatsApp broadcast notification — where approving a submission instantly dispatches alerts to all relevant subscribers — took considerable engineering effort and felt like a genuine infrastructure achievement when it first fired correctly.

###  Built on Deliberately Accessible Technology
Every technology choice — Vanilla PHP, Bootstrap, jQuery, Chart.js, Google Maps — was made with **deployability and maintainability** in mind. This platform can be hosted on a $5/month VPS, maintained by a junior developer, and forked by a Ministry of Health team with no enterprise software licenses. That simplicity is itself an accomplishment in a space often over-engineered.

---

## What We Learned

### The Channel IS the Strategy
Our biggest learning was that **choosing WhatsApp wasn't a feature decision — it was the entire strategy**. The moment we stopped thinking of WhatsApp as a notification add-on and started thinking of it as the primary interface, the entire platform design clarified. The map dashboard became a command tool for decision-makers; WhatsApp became the universal access point for everyone else. Separating these two user journeys made both better.

### Trust Architecture is Harder Than Technical Architecture
We spent more time debating and designing the **moderation system and tiered verification display** than any technical component. We learned that in life-critical information systems, the hardest engineering is not code — it is the design of **trust**. Every display decision (what color means what, when to show unverified data, how to communicate uncertainty) carries moral weight.

###  State Management Belongs in the Database, Not in Memory
Attempting to manage WhatsApp conversation state in PHP session variables (our first approach) failed immediately in a multi-server or shared hosting environment. The database-as-state-store pattern we ultimately implemented is more robust, auditable, and scalable — a principle that applies far beyond this project.

###  Geospatial Problems Are Always Harder Than They Look
The jump from "find nearby facilities" to "find nearby facilities, performantly, at scale, with filtering, correctly handling edge cases near the equator and date line" was humbling. Geographic edge cases and query performance issues consumed far more development time than anticipated.

###  Visual Data Creates Moral Pressure
When health officials saw the heatmap showing resource deserts in their jurisdiction — not as a table of numbers but as a vivid red zone on a live map — the response was different. Data visualization doesn't just inform; it **creates moral urgency**. We learned that charts and maps are not decoration — they are instruments of accountability.

---

## What's Next for NERAP Cloud

NERAP Cloud 2.0 is a foundation, not a destination. Our roadmap is ambitious but deliberately sequenced.

###  Phase 1 — Kenya National Rollout (0–6 Months)
- **Official KEMSA API Integration:** Establish live data feeds from Kenya Medical Supplies Authority to auto-populate and verify facility stock data for public facilities, dramatically reducing manual reporting burden
- **County Health Department Onboarding:** Partner with all 47 county health departments to designate official NERAP data officers responsible for regional moderation and data quality
- **WhatsApp Number Verification for Facility Staff:** Implement a phone-number verification system so that stock updates submitted from registered facility numbers receive expedited moderation treatment (semi-trusted sources)
- **USSD Fallback Channel (`*384*NERAP#`):** For users without smartphones or WhatsApp, deploy a USSD interface providing text-only resource searches — extending reach to feature phone users in remote areas

### Phase 2 — Intelligence Layer Enhancement (6–12 Months)
- **Predictive Depletion Alerts:** Using historical stock submission patterns, implement a simple time-series forecasting model (built in PHP with rolling averages as a lightweight baseline) that **proactively alerts facilities** when their stock is projected to reach critical levels within 14 days — shifting from reactive to anticipatory response
- **AI-Assisted Moderation:** Integrate a lightweight NLP classifier (exposed as an API) to pre-screen incoming WhatsApp submissions, auto-approving high-confidence updates from verified facility numbers and flagging suspicious or inconsistent reports for human review — reducing moderator workload without removing human oversight
- **Offline-Capable PWA Dashboard:** Convert the web dashboard to a **Progressive Web App** with service worker caching, enabling emergency responders in the field to view the last-synced map and resource data even when connectivity is lost

###  Phase 3 — Regional IGAD Expansion (12–24 Months)
- **Ethiopia, Somalia, South Sudan, Uganda Deployment:** Package NERAP Cloud as a **deployable open-source kit** with country-specific configuration files (language packs, national health authority API endpoints, regional administrative boundaries) enabling rapid national-level deployments
- **Cross-Border Resource Visibility:** Enable cross-country resource visibility for border regions — a hospital in Mandera (Kenya) being able to see resources available in Dollo Ado (Ethiopia) — critical for a region where borders are administrative but crises are geographic
- **IGAD Regional Alert Network:** Establish a regional moderation and alert coordination layer enabling multinational humanitarian events (cross-border floods, refugee crisis health emergencies) to trigger coordinated alerts across all deployed national instances simultaneously
- **UN OCHA & WHO Integration:** Pursue formal data-sharing agreements with UN agencies to incorporate NERAP data into regional humanitarian dashboards (HDX, ReliefWeb) while pulling verified WHO facility and supply chain data back into NERAP

###  Phase 4 — Sustainability & Ecosystem (24+ Months)
- **API Marketplace:** Publish a documented public API allowing third-party developers — ambulance dispatch software, hospital management systems, insurance platforms — to query NERAP data and embed resource availability into their own products
- **Community Feedback Loop:** Enable WhatsApp users to **rate the accuracy** of information received ("Was the resource actually available? Reply YES/NO") — creating a real-world ground-truth feedback mechanism that improves data quality over time and gamifies accuracy for facilities
- **Academic & Policy Data Portal:** Anonymized, aggregated NERAP data made available to researchers and policymakers through a dedicated analytics portal — turning emergency response data into long-term health system evidence