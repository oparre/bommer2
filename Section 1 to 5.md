## SECTION 1 — PRODUCT VISION & GOALS  

### 1.1 Elevator Pitch  

- **Business view**  
  For development and sales teams frustrated with messy, incomplete and outdated bills of materials, the Bommer web app is a workspace that organizes all BOM creation, communication, and retrieval into a searchable, well‑presented interface.

- **Engineering view**  
  Bommer is an internal web application running on PHP 8.3.6, MySQL 8.3.0, and Apache, with a Clarity Design System frontend. It provides authenticated, role‑based access to a structured BOM, Project, and Product ID data model, including fast search, matrix and comparison views, and full revision/audit history.

---

### 1.2 Problem Statement  

- **Business view**  
  - BOMs are currently stored in scattered Excel files and emails.  
  - Multiple departments (especially sales) depend on very busy development teams to locate and send the right BOM.  
  - There is a high risk of using obsolete spreadsheets for quotations, which can lead to incorrect pricing and customer promises.  

- **Engineering view**  
  - There is no canonical, central database for BOMs, their revisions, statuses, and relationships to projects and products.  
  - Excel‑based BOMs lack schema enforcement, validation, and audit logging; they are not suited for reliable querying, searching, or automated processing.  
  - Existing infrastructure (authentication module, Clarity UI, local asset constraints) is in place but not yet leveraged to manage BOMs and Product IDs comprehensively.

---

### 1.3 Why Now?  

- **Business view**  
  - The number of BOMs is increasing rapidly, along with the complexity of products and configurations.  
  - Fast, accurate communication with customers requires immediate access to the latest approved BOM.  
  - Relying on individuals to manage, update, and share Excel files is risky and doesn’t scale.

- **Engineering view**  
  - The organization already has a hardened authentication subsystem and Clarity‑based UI infrastructure.  
  - Standardizing on **PHP 8.3.6** and **MySQL 8.3.0** provides a modern stack with good performance and long‑term support.  
  - Building Bommer now allows:  
    - Reuse of the existing security layers (CSRF, brute‑force protection, session fingerprinting, remember‑me).  
    - A single data model for Projects, Product IDs, BOMs, and Components that can support later integrations (e.g., AI assistance, reporting) without changing foundations.

---

### 1.4 Success Criteria (6‑Month Horizon)  

- **Business view**  
  - A majority of BOM‑related work (creation, updates, retrieval for quotations) happens through Bommer instead of Excel.  
  - Sales and development teams report that they can reliably find the **latest BOM** without asking colleagues.  
  - Reduction in quotation errors attributable to wrong or obsolete BOMs.  
  - Bommer is perceived as easy to use and becomes a standard part of daily workflows.

- **Engineering view**  
  - ≥ 80% of BOM retrieval actions are performed via Bommer (as inferred from access logs and database usage).  
  - 0 critical data‑loss incidents affecting BOMs, projects, or products.  
  - < 1% of BOM‑related actions result in support tickets or manual interventions due to system issues.  
  - Typical performance targets:  
    - BOM search: < 1 second for typical dataset size.  
    - Matrix and comparison views: render in < 2 seconds for typical workloads.  
  - All code is compatible with PHP 8.3.6 and MySQL 8.3.0; no blocking deprecations or SQL mode issues.

---

### 1.5 Failure Modes  

- **Business view**  
  - Users continue to rely on Excel instead of Bommer because the app feels cumbersome, confusing, or slow.  
  - BOMs inside Bommer are incomplete, out‑of‑date, or not trusted by users.  
  - Sales continues to request BOMs from development manually, indicating poor adoption.

- **Engineering view**  
  - Performance problems in search or matrix/comparison views, especially for real‑world BOM sizes.  
  - Data inconsistencies in version histories or statuses (e.g., approved BOMs not clearly distinguished from drafts or obsolete versions).  
  - Security regressions (e.g., incorrect permissions, unauthorized access to BOMs or Product IDs).
  - Accessibility issues that make the app unusable for users relying on keyboard or assistive tech, violating WCAG 2.2 AA.

---

### 1.6 Product Type & Deployment Context  

- **Business view**  
  - Bommer is an **internal tool** used inside the organization.  
  - Primary usage is on Windows desktops/laptops, with some usage on iPads.

- **Engineering view**  
  - Single‑tenant internal deployment (one organization, one database).  
  - Runtime environment:  
    - PHP **8.3.6**  
    - MySQL **8.3.0**  
    - Apache with `mod_rewrite`  
  - Frontend: Clarity Design System, Noto Sans SC fonts, dark theme, local assets only (no external CDNs).

---

### 1.7 Timeline & Scope Boundaries  

- **Business view**  
  - Target: **usable v1 within 3 weeks**.  
  - v1 must support core BOM workflows and basic matrix/comparison visualization for same‑project BOMs; product‑based matrix and deeper reporting can be iterated on, but must have a clear first‑cut.

- **Engineering view**  
  - Priorities for v1:  
    - Data model: Projects, Product IDs, BOMs, Components, revisions, statuses, and audit logs.
    - Workflows: create/edit BOMs, set statuses, search/retrieve/export BOMs, view matrix and comparison for BOMs in a project and product.  
    - Security: integrate fully with the existing authentication system (login, roles, sessions, CSRF).  
    - Accessibility: WCAG 2.2 AA within Clarity.  
  - Explicitly **not** in v1: full PLM workflows, external monetization, or multi‑tenant architecture.

---

## SECTION 2 — TARGET USERS & PERSONAS  

### 2.1 Primary Users  

- **Business view**  
  - **Project Managers in Development**  
    - Own and maintain BOMs for their projects.  
    - Need to create, update, and share BOMs quickly and reliably.

- **Engineering view**  
  - Role in system: **standard authenticated user**, potentially with elevated permissions for BOM editing.  
  - Permissions (high level):  
    - Create BOMs for projects they own.  
    - Edit and revise BOMs.  
    - Mark BOMs as obsolete or change status.  
    - Use matrix and comparison views to analyze BOM variants per project or product.  

---

### 2.2 Secondary Users  

- **Business view**  
  - **Sales staff**  
    - Need access to the latest, approved BOMs for quotations and proposals.  
    - Often need to compare BOM variants for pricing differences.

- **Engineering view**  
  - Role in system: typically **read‑only user** for BOM data.  
  - Permissions:  
    - Search and view BOMs, Projects, and Product IDs.
    - Use comparison and matrix views to understand differences.  
    - Export BOMs (e.g., CSV/XLSX/PDF) for use in quoting tools.  
    - No permission to modify BOM contents, statuses, or project/product structure.

---

### 2.3 User Characteristics  

- **Business view**  
  - Technical skill level: skilled software users, familiar with internal tools and spreadsheets.  
  - Age range: approximately 20–50 years old.  
  - Devices:  
    - ~90%: Windows desktop/laptop browsers.  
    - ~10%: iPads (tablet browser).

- **Engineering view**  
  - UX must be optimized for desktop first while remaining usable on iPads:  
    - Tables, comparison views, and matrix views must be readable and scrollable on tablet.  
    - Interactions should support keyboard navigation on desktop browsers (for WCAG 2.2 AA).  
  - No explicit support for phones in v1 is required (but not prohibited if it comes “for free” from responsive design).

---

### 2.4 First 5 Minutes Experience  

- **Business view**  
  In their first 5 minutes with Bommer, a new user should be able to:  
  - Log in.  
  - Find the BOM they care about for a particular project or product.
  - Confirm they are viewing the latest revision and correct status.  
  - Export or otherwise use that BOM in their existing quoting or planning workflow.

- **Engineering view**  
  Typical first‑time flow:  
  1. User signs in via the existing login flow.  
  2. User is taken to a **BOM dashboard** or landing page.  
  3. User uses search or filters (by project, product, status, BOM name, etc.) to locate their BOM.  
  4. User opens BOM detail, comparison view, or matrix view.  
  5. User exports or copies data as needed (with clear status/revision context).

---

### 2.5 Frustrations with Existing Solutions  

- **Business view**  
  - Excel BOMs are easy to misplace and not versioned consistently.  
  - Users are often not sure whether they are using the latest BOM.  
  - Sales is slowed down by waiting for development to provide BOMs.  

- **Engineering view**  
  - No central database means:  
    - No reliable global search.  
    - No enforced schema or validation.  
    - No audit logging on BOM changes.  
  - Any automation (e.g., reporting, AI assistance) is very difficult since BOM data is not structured.

---

### 2.6 Accessibility Requirements  

- **Business view**  
  - Bommer must be usable by internal staff who rely on keyboard navigation or assistive technologies, in line with internal standards and best practices.

- **Engineering view**  
  - The application MUST meet **WCAG 2.2 level AA** within the limits of the **Clarity Design System**:  
    - Use Clarity components and patterns for all UI (tables, buttons, dialogs, toggles, etc.).  
    - Ensure keyboard access for all core flows including:  
      - Logging in.  
      - Navigating BOM lists.  
      - Opening comparison views and matrix views.  
      - Changing filters and toggles (grouped/flat view, project vs product scope for matrix, etc.).  
    - Ensure visible focus indicators and logical focus order.  
    - Ensure sufficient color contrast for dark theme and highlight states.  
    - Use meaningful text labels and ARIA attributes where Clarity allows configuration, without replacing Clarity itself.

---

### 2.7 Usage Context  

- **Business view**  
  - Internal users access Bommer during regular work hours for BOM maintenance, quotation preparation, and project planning.  
  - Usage is primarily office environments with stable network connectivity.

- **Engineering view**  
  - Application runs on an internal network or via VPN.  
  - No offline mode required.  
  - Sessions and authentication follow existing security requirements (session fingerprinting, account lockout, remember‑me tokens).

---

## SECTION 3 — CORE FEATURES & SCOPE  

### 3.1 Must‑Have Features (v1)  

- **Business view**  
  Bommer must provide, at minimum:

  - **BOM Management**  
    - Create, edit, and revise BOMs for Projects.  
    - Each BOM is identified by a globally unique SKU that users can rely on across all Projects and Product IDs.
    - Manage BOM revisions with statuses (e.g., draft, approved, obsolete, invalidated); BOMs themselves are never physically deleted.  
    - Mistakes are corrected by creating new revisions, not by removing old ones.  
    - Users can safely edit BOMs without losing work thanks to automatic local autosave during editing.

  - **Search & Retrieval**  
    - Quickly search and filter by Project, Product ID, status, part numbers, etc.
    - Ensure users can always find the **latest approved** BOM for a given need.  
    - Provide a component "where-used" view so users can see all BOMs (by SKU), Projects, and Product IDs that reference a specific component.

  - **Comparison Views**  
    - Side‑by‑side comparisons of BOMs (e.g., up to 5) with synchronized scrolling.  
    - Grouped and flattened views (per existing BOM visualization specs).

  - **Matrix Views (Core UX Module)**  
    - **Project‑level matrix**: compare multiple BOMs belonging to the same Project in a matrix layout.  
    - **Product‑level matrix**: compare BOMs across Projects belonging to the same Product ID.
    - Provide an overview of common and differing components across variants.

  - **Product IDs**
    - Ability to define Product IDs as combinations of Projects.
    - Navigate from a Product ID to its Projects and their BOMs.

  - **Audit Logs & Version History**  
    - Full version history for BOMs (immutable revisions) where each revision carries its own status (draft, approved, obsolete, invalidated).  
    - Audit log of key actions (BOM creation, revision saves, status changes, product/project associations) with enough detail to reconstruct who changed what and why.

- **Engineering view**  
  Must‑have implementation aspects:

  - **Data model** for:  
    - Product IDs, Projects, BOMs, BOM revisions, BOM items, Components, Users, Audit logs.

  - **BOM CRUD**  
    - Create/update BOMs and their components with validation and no physical deletes.  
    - Enforce global SKU uniqueness at the database level so each SKU identifies exactly one BOM.  

  - **Status & revision**  
    - Versioning model (e.g., version number per BOM or `bom_revisions` table) with a clear "current" revision pointer on the BOM and per-revision status (draft, approved, obsolete, invalidated).

  - **Visualization**  
    - Comparison view using Clarity tables, dark theme, Noto Sans SC, minimal "No." column width, grouped/flat toggles.  
    - Matrix view built with Clarity tables and layout components, accessible and keyboard-friendly.  

  - **Integration with auth**  
    - Use existing PHP auth stack (login, roles, sessions, CSRF, remember-me).  

  - **Logging & audit**  
    - Record major events in audit logs: edit session start (optional), revision saves, status changes, lock acquire/release, and bulk operations affecting BOMs, Product IDs, or Project–Product ID associations.

---

### 3.2 Nice‑to‑Have Features (v1+)  

- **Business view**  
  - **AI integration**:  
    - Suggest components for new BOMs based on partial information.  
    - Highlight differences between BOM revisions or variants more intelligently.  
  - Advanced reporting or dashboards (e.g., BOM coverage across products, components usage frequency).

- **Engineering view**  
  - AI features require:  
    - Clean, well‑structured APIs or internal interfaces to the BOM data.  
  - Planning:  
    - Data model and API design in v1 must not block adding AI modules later.  
  - Reporting:  
    - Optional, possibly implemented after core records & views are stable.

---

### 3.3 Explicitly Out‑of‑Scope  

- **Business view**  
  - Bommer is **not** a full PLM (Product Lifecycle Management) system:  
    - No complex approval workflows (multi‑step sign‑off processes).  
    - No supplier lifecycle management or costing optimization beyond simple fields.  
    - No change management workflows beyond BOM versioning and status flags.

- **Engineering view**  
  - Out of scope in v1:  
    - Multi‑tenant architecture.  
    - External customer access or monetization logic.  
    - Deep integrations with ERP/PLM systems (beyond potential future APIs).  

---

### 3.4 User Actions  

- **Business view**  

  **Project Manager actions:**  
  - Create BOM for a Project.  
  - Add/remove/edit components on a BOM.  
  - Save a new BOM revision.  
  - Change BOM status (draft → approved, approved → obsolete, etc.).  
  - Use comparison view to compare multiple BOM variants.  
  - Use matrix view at Project or Product ID level to analyze variants.
  - Export BOMs for sharing or offline analysis.

  **Sales actions:**  
  - Log in and search for a specific Project, Product ID, or BOM.
  - View BOM details, comparison, and matrix views.  
  - Export BOMs (read‑only).

- **Engineering view**  
  The system must provide operations equivalent to:

  - BOM:  
    - Create/update handlers.  
    - Revision creation that clones from an existing BOM and increments version.  
    - Status update operations.  

  - Project:  
    - Link BOMs to Projects; manage basic project metadata.  

  - Product ID:
    - Create Product IDs; link/unlink Projects to/from Product IDs.

  - Views:  
    - Handlers/views to render:  
      - BOM list views.  
      - BOM detail views.  
      - Comparison and matrix views for specified sets of BOMs (project or product scoped).

---

### 3.5 Real‑Time, Async, and Offline  

- **Business view**  
  - No real‑time collaboration or real‑time updates are required for v1.  
  - Offline use is not required.

- **Engineering view**  
  - No WebSockets or live updates needed initially.  
  - Potential background jobs might be introduced later for:  
    - Search index maintenance.  
    - Periodic data integrity checks or reports.  
  - v1 should be built with standard request/response patterns.

---

## SECTION 4 — BUSINESS MODEL & CONSTRAINTS  

### 4.1 Business Model  

- **Business view**  
  - Bommer is an **internal tool** with no external monetization in v1.  
  - Its business value is in operational efficiency, risk reduction (fewer wrong quotes), and improved collaboration.

- **Engineering view**  
  - No billing, subscription, or licensing logic is needed.  
  - User accounts are internal and managed through the existing authentication system and admin user management UI.

---

### 4.2 Usage Limits  

- **Business view**  
  - There are no explicit contractual or pricing‑based usage caps.  
  - The system must comfortably handle the organization’s expected data growth.

- **Engineering view**  
  - The system should handle at least:  
    - Hundreds of Projects.  
    - Hundreds to thousands of BOMs.  
    - Thousands to tens of thousands of BOM items.  
    - A few dozen concurrent internal users.  
  - Performance tuning and indexing must reflect these scales, especially for search and matrix/comparison views.

---

### 4.3 Legal, Regulatory, Compliance  

- **Business view**  
  - No external compliance regimes (e.g., GDPR for customer data) are primary drivers in v1, as this is internal and BOM data is mostly technical.

- **Engineering view**  
  - Internal security and data protection standards apply:  
    - Authenticated access only.  
    - Role‑based permissions.  
    - Proper handling of logs and backups.  
  - The system must comply with internal rules for logging and data retention where applicable.

---

### 4.4 Monetization  

- **Business view**  
  - No monetization in v1; Bommer is not a commercial product.

- **Engineering view**  
  - No need for payment providers, subscription tracking, or license enforcement code.

---

### 4.5 Multi‑Tenancy  

- **Business view**  
  - Bommer supports only a single organization; there is no concept of multiple tenants.

- **Engineering view**  
  - Single database schema, single tenant.  
  - Data model should avoid assumptions that prevent multi‑tenancy entirely, but adding multi‑tenancy is not a goal for v1.

---

### 4.6 Technical Constraints  

- **Engineering view**  
  - Runtime versions are **fixed** for v1:  
    - PHP **8.3.6**.  
    - MySQL **8.3.0**.  
  - MySQL 8.3.0 SQL modes and behavior (e.g., strict mode, reserved words, indexing) must be considered in schema and query design.  
  - All front‑end assets must be local:  
    - Noto Sans SC from `public/fonts/noto-sans-sc/`.  
    - Clarity UI and icons from `public/node_modules/@clr/ui/`.  
    - No external CDNs or remote fonts/scripts.  
  - Use the existing authentication security stack (CSRF tokens, session fingerprinting, brute‑force protection, remember‑me tokens) without weakening it.

---

## SECTION 5 — DATA & DOMAIN MODEL  

### 5.1 Main Entities  

- **Business view**

  - **Product ID**  
    - A combination of multiple Projects that together form a larger product or deliverable.  
    - Provides a higher‑level grouping for BOMs across projects.

  - **Project**  
    - A product or internal project for which BOMs are created.  
    - A Project can belong to multiple Product IDs.

  - **BOM (Bill of Materials)**  
    - A structured list of components for a Project, identified by a globally unique SKU. Each SKU corresponds to exactly one BOM, and each BOM maintains its own independent revision history.  
    - Multiple BOMs/SKUs within the same Project may represent configuration variants of the same product; these variants are explicitly grouped at the BOM level via a variant identifier so the system can treat them as related while keeping SKUs and revision trees distinct.  
    - Has an immutable revision history where each revision carries its own status (draft, approved, obsolete, invalidated).

  - **Component**  
    - An individual part or item that appears in many BOMs across projects and products and may have revisions and vendor/MPN variants.

  - **User**  
    - Internal user accounts (project managers, sales, admins) who authenticate via the existing auth system and act on BOM data.

- **Engineering view**

  Core tables (conceptually):

  - `products` – basic metadata about each product.  
  - `product_projects` – join table mapping Product IDs ↔ Projects (many‑to‑many).  
  - `projects` – holds project metadata.  
  - `boms` – BOM records; includes project linkage, globally unique SKU, and a pointer to the current revision.  
  - `bom_revisions` (or `boms` with versioning fields) – tracks immutable revisions per BOM, including status and reason for change.  
  - `bom_items` – line items for BOMs, linking to components and carrying quantities & other attributes.  
  - `components` – component catalog with revision/variant and status information used for validity checks.  
  - `users` – existing auth users.  
  - `audit_logs` – records of important changes (BOMs, Product IDs, associations, statuses).

---

### 5.2 Relationships  

- **Business view**  
  - A **Product ID** contains multiple **Projects**.
  - A **Project** can be part of multiple Product IDs (flexible grouping).
  - A **Project** has one or more **BOMs**.  
  - Each **BOM** has multiple **Components** via BOM line items.  

- **Engineering view**  

  - Product IDs ↔ Projects: **many‑to‑many**  
    - `product_projects.product_id` → `products.id`.  
    - `product_projects.project_id` → `projects.id`.  

  - Projects ↔ BOMs: **one‑to‑many**  
    - `boms.project_id` → `projects.id`.  

  - BOMs ↔ BOM Items: **one‑to‑many**  
    - `bom_items.bom_id` → `boms.id`.  

  - BOM Items ↔ Components: **many‑to‑one**  
    - `bom_items.component_id` → `components.id`.  

  These relationships must support:  
  - Filtering BOMs by project or product ID for list, matrix, and comparison views.
  - Efficient queries for:  
    - Project-level matrix: all BOMs with `boms.project_id = X`.  
    - Product-level matrix: all BOMs whose projects are linked to Product Y via `product_projects`.  
  - Efficient "where-used" queries for components to list all BOMs (by SKU), Projects, and Product IDs that reference a given component or variant.

---

### 5.3 Expected Data Volume & Performance Implications  

- **Business view**  
  - Expect growth over time as more products and variants are added.  
  - The app must remain responsive for everyday tasks (searching, opening BOMs, using matrix/comparison views).

- **Engineering view**  
  - Scale assumptions:  
    - Projects: hundreds.  
    - BOMs: hundreds to thousands.  
    - Components: thousands.  
    - BOM items: thousands to tens of thousands.  
  - Performance considerations:  
    - Indices on `project_id`, `product_id` (via join), `bom_id`, `component_id`, and status fields.
    - Careful query design for matrix views (often multi‑BOM joins).  
    - Pagination or scoped loading in UI for large matrices to keep rendering fast and accessible.

---

### 5.4 Deletion & Recovery  

- **Business view**  
  - **No physical deletion** of BOMs or critical domain objects in v1.  
  - Instead:  
    - BOMs can be marked “obsolete” or superseded by newer revisions.  
    - Product IDs, Projects, and Components may have similar active/inactive semantics to prevent use in new BOMs while preserving history.

- **Engineering view**  
  - Use status flags and possibly soft-delete fields:  
    - `status` (`draft`, `approved`, `obsolete`, `invalidated`, etc.) on revisions.  
    - `is_active` or similar flags and detailed status for Projects, Product IDs, Components (including "banned" where applicable).
  - Rely on database backups for full recovery from catastrophic errors.  
  - UI and queries must consistently respect status flags (e.g., show current/approved BOMs by default but allow viewing obsolete/invalidated/revision history as needed) and prevent saving new BOM revisions that contain banned components.

---

### 5.5 Audit Logs  

- **Business view**  
  - Stakeholders must be able to answer:  
    - Who created or changed a BOM?  
    - When was a BOM approved or marked obsolete?  
    - How were projects assigned or removed from product IDs?

- **Engineering view**  
  - `audit_logs` (or equivalent) must track major events rather than every keystroke:  
    - Entity type (e.g., BOM, Product ID, Project, BOM Item).
    - Entity ID.  
    - Action (e.g., edit session start, revision created, status change, lock acquired/released, project added to product, bulk update).
    - User ID.  
    - Timestamp.  
    - Description (e.g., Reason for Change or summary of the event).  
  - Log entries should be created within the same transaction as the changes where possible.

---

### 5.6 Version Histories  

- **Business view**  
  - Users must see and trust the **revision history**:  
    - Each BOM revision is preserved and identifiable (e.g., “v1, v2, v3”).  
    - They can understand which revision is current and which were used historically.

- **Engineering view**  
  - Versioning model:  
    - Either a dedicated `bom_revisions` table or version fields on `boms`.  
  - Requirements:  
    - Each revision is immutable once created; changes create a new revision.  
    - A clear way to mark one revision as "current" for a given BOM/project, with status stored on the revision (draft, approved, obsolete, invalidated).  
    - Store a non-empty Reason for Change/Creation string for every revision.  
    - Matrix and comparison views should be able to specify which revision set they display (e.g., current vs selected historic revisions).  
    - Any change to a referenced component revision or variant in a BOM must trigger creation of a new BOM revision.

---