## SECTION 6 — PERMISSIONS & ROLES  

### 6.1 Role Overview  

- **Business view**  
  Bommer distinguishes access by role so that:
  - Only trusted users can change BOMs and structure (Projects/Product IDs).
  - Sales can safely consume BOM information without accidentally modifying it.
  - Administrative users can manage accounts and system configuration.

- **Engineering view**  
  Core roles (mapping to the existing auth system):

  - **Anonymous**  
    - Not logged in.  
    - Can only access the login page and possibly publicly documented help pages (if any in future).  

  - **Standard User (Project Manager)**  
    - Authenticated, can create and modify BOMs for authorized Projects/Product IDs.
    - Uses BOM detail, comparison, and matrix views.  

  - **Standard User (Sales)**  
    - Authenticated, but primarily read‑only for BOM data.  
    - Uses search, view, comparison, and matrix views; can export but not modify BOMs.  

  - **Admin**  
    - Authenticated user with elevated permissions.  
    - Manages users and roles via admin UI (existing auth subsystem).  
    - May also have extended rights on Projects, Product IDs, and BOM configurations (depending on organization policy).

---

### 6.2 Permission Matrix (High‑Level)

| Capability                                            | Anonymous | Sales User | Project Manager | Admin |
|-------------------------------------------------------|-----------|-----------:|----------------:|------:|
| View login page                                      | ✅        | ✅         | ✅               | ✅    |
| Log in                                               | ❌        | ✅         | ✅               | ✅    |
| View BOM list/search                                 | ❌        | ✅         | ✅               | ✅    |
| View BOM detail                                      | ❌        | ✅         | ✅               | ✅    |
| View comparison & matrix views (project/product)    | ❌        | ✅         | ✅               | ✅    |
| Export BOMs                                          | ❌        | ✅         | ✅               | ✅    |
| Create/edit BOMs                                     | ❌        | ❌         | ✅               | ✅    |
| Change BOM status (draft/approved/obsolete)          | ❌        | ❌         | ✅               | ✅    |
| Create/edit Projects                                 | ❌        | ❌         | ✅				        | ✅    |
| Create/edit Product IDs                               | ❌        | ❌         | ✅	     		      | ✅    |
| Manage user accounts/roles                           | ❌        | ❌         | ❌               | ✅    |
| Configure system‑level settings                      | ❌        | ❌         | ❌               | ✅    |

---

### 6.3 Authorization Rules  

- **Business view**  
  - Changes to BOMs, Projects, and Product IDs must only be made by users who are accountable (typically Project Managers and Admins).
  - Sales must be assured they cannot accidentally alter a BOM while working with it.

- **Engineering view**  
  - All protected pages and APIs must enforce authorization via the existing PHP helper functions (`requireLogin`, `requireAdmin`) extended for BOM-specific permissions.  
  - Add or reuse helper checks such as:
    - `requireProjectEditor($project_id)` – for BOM changes tied to a project.  
    - `requireProductEditor($product_id)` – for operations on products.
  - All UI actions (buttons, links, forms) must reflect the user’s permissions:
    - Hide or disable actions the user is not allowed to perform.  
    - Backend must still enforce authorization (no reliance solely on UI).  
  - Even when multiple users have edit permission on the same BOM, only the user who holds the server-side edit lock may enter the editing interface; all others are restricted to read-only views.

---

### 6.4 Authentication Integration  

- **Business view**  
  - Users will log in with the same credentials and login flow as the existing authentication system.

- **Engineering view**  
  - Reuse existing:
    - Login/logout endpoints and flows.  
    - Session fingerprinting and brute‑force lockout.  
    - Remember‑me tokens and CSRF protections.  
  - BOM‑related routes must be protected with the appropriate `requireLogin()`/role checks; anonymous access is limited to login/help pages.

---

## SECTION 7 — UI/UX REQUIREMENTS  

### 7.1 Design System & Visual Language  

- **Business view**  
  - The app must be visually consistent, clean, and easy to scan for complex tabular data (BOMs, comparisons, matrix views).

- **Engineering view**  
  - Use **Clarity Design System** as the primary UI framework:
    - Clarity typography combined with local Noto Sans SC font.  
    - Clarity tables, buttons, icons, modals, alerts, form elements.  
  - Visual requirements:
    - **Dark theme** throughout.  
    - Noto Sans SC as primary font, loaded from local assets.  
    - No external CDNs; all CSS/JS loaded from local paths.  

---

### 7.2 Core Screens  

- **BOM Dashboard / Listing**  
  - Business:
    - Show a searchable, filterable list of BOMs with key columns in this order: Project name, BOM name (used as SKU name), BOM description, SKU code, current BOM revision, BOM status, and last modified date.  
  - Engineering:
    - Implement as Clarity data grid/table with:
      - Column sorting (e.g., by Project, status, last updated).  
      - Filters (e.g., by Project, Product ID, status).
      - Row actions (view, compare, open matrix context).  

- **BOM Detail & Editing**  
  - Business:
    - Provide a clear view of BOM components (No., part number, description, quantity, unit, cost, etc.).  
    - Allow editing, adding, and removing components (for authorized users).  
    - Show status and revision metadata prominently.  
  - Engineering:
    - Use Clarity forms and tables.  
    - Respect BOM table layout rules:
      - Compact layout.  
      - Proper alignment for numeric values (quantities, costs).  
      - Minimal "No." column width.  
    - For drag-and-drop interactions, highlight valid drop targets (e.g., groups or other structurally valid areas) when a component is being dragged and do not highlight invalid regions.  
    - Ensure that all actions supported by drag-and-drop (such as moving a component between groups) can also be performed via non-drag controls (buttons/menus) so the core flow remains accessible.

- **Comparison View (Side‑by‑Side)**  
  - Business:
    - Up to 5 BOMs side‑by‑side for visual comparison.  
    - Grouped/flattened views for different analysis perspectives.  
  - Engineering:
    - Use existing design rules:
      - Each BOM in its own vertical column.  
      - Synchronized horizontal and vertical scrolling.  
      - Grouped view: group headers + rows, no repeated group IDs on items.  
      - Flattened view: rows with group badges; badge text size must match row text.  
      - Minimal “No.” column in each BOM table, per constraint.  
    - Implement view toggles at the header (grouped/flat) following Clarity styles.  

- **Matrix View (Project and Product ID)**  
  - Business:
    - Provide a matrix showing multiple BOMs as columns and components/attributes as rows.  
    - For Projects: compare BOMs belonging to the same Project.  
    - For Product IDs: compare BOMs across Projects that belong to the same Product ID.
  - Engineering:
    - Implement as Clarity‑based table or grid:
      - Columns: BOMs (identified by name, revision, status).  
      - Rows: components or normalized attributes.  
      - Cells: indicate presence/absence, quantity, or differences.  
    - Accessibility:
      - Full keyboard navigation across cells.  
      - Clear focus indication.  
      - Header cells labeled so screen readers can announce row/column context.  

#### 7.2.1 Matrix View UX & Interaction Details  

- **Business view**  
  - Matrix View compares up to 10 related BOM SKUs at once for a given Project or Product ID so users can quickly see which components are common, unique, or differ in quantity or cost.
  - Component variants (for example the same logical part with different vendor/MPN) are represented as separate rows so supplier choices and variant impacts are clearly visible.  
  - The matrix is primarily a high-level analysis tool; users should be able to understand key differences without leaving the page, but can drill into specific BOMs when needed.  

- **Engineering view**  
  - **Scope and structure**:  
    - Project matrix: columns are BOMs belonging to the same Project; Product ID matrix: columns are BOMs whose Projects belong to the same Product ID.
    - Rows are components; variants are modeled as separate rows (for example, Part A – Vendor 1, Part A – Vendor 2).  
    - Left-side sticky columns include `No.`, `Part number`, `Name`, and `Description`; BOM columns are horizontally scrollable.  
  - **Interaction rules**:  
    - Clicking a BOM column header navigates to the corresponding BOM Detail view.  
    - Clicking a row does not navigate anywhere; rows are used only for scanning differences.  
    - Hovering a cell shows inline contextual details for that component in that BOM (for example supplier/MPN, full description, unit cost, total cost, quantity) via a tooltip or lightweight popover; no modal dialogs or page changes are triggered from cells.  
  - **View modes and behavior**:  
    - Matrix View supports at least a grouped-by-category presentation; a flattened mode (all components in a single list with group badges) may reuse the BOM grouped/flat toggle pattern if introduced later.  
    - No row-level filtering is applied in v1; all components in scope are always shown to avoid accidentally hiding important differences.  
    - The implementation must handle edge cases efficiently:  
      - Single BOM input → show an explanatory message that at least two BOMs are required, and offer a link back to the Project or Product ID detail.
      - No common parts → render the matrix and clearly indicate that all tracked components are unique across the compared BOMs.  
      - Large datasets (many components or many BOMs, up to 10 columns) → rely on sticky headers, scrollable regions, and efficient rendering to keep performance and accessibility within the targets defined for v1.  

- **Product ID & Project Views**  
  - Business:
    - For a Product ID: list its Projects and allow navigating into their BOMs, comparison, and matrix views.
    - For a Project: list its BOMs, show current revision, and link to comparison/matrix.  
  - Engineering:
    - Clarity cards or tables for lists.  
    - Actions consistent with permissions (e.g., “Create BOM”, “Open matrix”, “Open comparison”).  

---

### 7.3 Navigation & Information Architecture  

- **Business view**  
  - Users should easily move between:
    - Product ID → Project → BOM → Comparison/Matrix.
    - Search results and detail views.  

- **Engineering view**  
  - Global navigation:
    - Top‑level entry points (e.g., BOM Dashboard, Product IDs, Projects, Admin).
  - Local navigation:
    - Tabs or sub‑navigation inside Projects and Product IDs.
    - Breadcrumbs showing context (Product ID / Project / BOM).

---

### 7.4 Accessibility & WCAG 2.2 AA  

- **Business view**  
  - The UI must support users who rely on keyboard or assistive technologies without sacrificing clarity or speed.

- **Engineering view**  
  - Implement WCAG 2.2 AA within Clarity:
    - Ensure correct heading structure for pages.  
    - Provide accessible labels for:
      - Filters.  
      - View toggles (grouped/flat, project/product scope).
      - Matrix/comparison actions (e.g., “Add BOM to comparison”).  
    - Ensure:
      - Keyboard operability of all core actions (no mouse‑only features).  
      - Visible focus states on interactive elements.  
      - Sufficient color contrast (check dark theme palettes against WCAG 2.2 AA).  
    - For dynamic views (switching grouped/flat, or switching matrix scope):
      - Keep focus management predictable.  
      - Avoid unexpected focus jumps that disorient screen reader users.  

---

### 7.5 Feedback & Interactions  

- **Business view**  
  - Users must receive clear feedback on:
    - Success or failure of actions (e.g., save, status change).  
    - Long‑running operations (if any).  

- **Engineering view**  
  - Use Clarity alerts/toasts for success/error messages.  
  - Disable buttons or show loading states on form submission to prevent double submissions.  
  - Use consistent hover and active states for BOM rows and actions (per existing hover rules for dark theme).  

---

## SECTION 8 — ERROR HANDLING & EDGE CASES  

### 8.1 Error Categories  

- **Business view**  
  - Input/validation errors (invalid fields or missing data).  
  - Domain errors (e.g., trying to modify an obsolete BOM).  
  - Authorization errors (user lacks permission).  
  - System errors (DB connection issues, unexpected failures).

- **Engineering view**  
  - Differentiate:
    - **User errors** → clear, actionable messages.  
    - **System errors** → generic user message + detailed server logs.  
  - Avoid exposing internal details (SQL queries, stack traces) to users.  

---

### 8.2 Typical Error Scenarios  

1. **Invalid BOM Input**  
   - Business:
     - User enters invalid quantity, missing required fields, or duplicates components where not allowed.  
   - Engineering:
     - Validate server‑side and client‑side.  
     - Show field‑level errors and a summary if needed.  

2. **Changing Status of a Non-Editable BOM**  
   - Business:
     - User attempts to change status of an obsolete or locked BOM.  
   - Engineering:
     - Enforce business rules in backend; show consistent error message like "This BOM cannot be modified because it is obsolete/locked."  

3. **Unauthorized Access**  
   - Business:
     - Sales user tries to edit BOM; user not logged in tries to access matrix view.  
   - Engineering:
     - Return 403 or redirect with message "You do not have permission to perform this action."  
     - Ensure all APIs and pages check role/permission.  

4. **Product ID/Project Association Constraints**  
   - Business:
     - Attempt to remove a Project from a Product ID that is required by policy.
   - Engineering:
     - Enforce constraints with clear error messages (e.g., "This project cannot be removed from this product due to active BOMs in status X.").

5. **Matrix & Comparison Edge Cases**  
   - Business:
     - A comparison or matrix view is requested with no BOMs or only 1 BOM.  
   - Engineering:
     - Show appropriate empty states or hints ("Select at least 2 BOMs for comparison.").  

6. **System / Database Errors**  
   - Business:
     - User sees a generic "Something went wrong" message; they know to retry or contact support.  
   - Engineering:
     - Log details server-side with correlation identifiers.  
     - Provide fallback pages that maintain navigation (no blank screens).  

7. **Saving BOM with Banned Components**  
   - Business:
     - User attempts to save a BOM revision that includes components that have been flagged as banned.  
   - Engineering:
     - On save, validate all components referenced in the BOM against the banned list.  
     - If any banned components are found, block the save and present a clear error message listing the offending components and required corrective action.  

8. **Save After Lock Timeout and Concurrent Edit**  
   - Business:
     - A user resumes editing after a long pause; meanwhile another user has acquired the lock and saved a newer revision.  
   - Engineering:
     - When the first user attempts to save:
       - If another user still holds the lock, block the save and inform the user that the BOM is currently being edited by someone else.  
       - If the lock is free but a newer revision exists, warn about the conflict and require explicit confirmation; on confirmation, acquire a fresh lock and create a new revision on top of the latest revision using the user’s current state.

---

### 8.3 Handling Inconsistent Data  

- **Business view**  
  - Users should never see obviously inconsistent information (e.g., BOM referencing a non‑existent component).

- **Engineering view**  
  - Use foreign keys and constraints for core relationships.  
  - When data is missing or corrupted:
    - Log the issue.  
    - Provide a graceful fallback (e.g., show a placeholder “Unknown component [ID]” in matrix/comparison, with a warning).  

---

## SECTION 9 — ANALYTICS & REPORTING  

### 9.1 High‑Level Analytics Needs  

- **Business view**  
  - Understand adoption:
    - How often is Bommer used vs legacy workflows.  
  - Understand BOM usage:
    - Which BOMs are most referenced for quotations.  
    - Which Product IDs/Projects have the most variants.

- **Engineering view**  
  - Minimal initial analytics:
    - Access logs (who accessed which BOM/Product ID/Project and when).
    - Basic usage metrics:
      - Number of logins.  
      - Number of BOM views, exports, comparisons, matrix views.  

---

### 9.2 Reporting Requirements (v1)  

- **Business view**  
  - At minimum, internal stakeholders should be able to:
    - List BOMs with their statuses and last updated dates.  
    - List Product IDs and associated Projects.
    - Extract audit logs when investigating issues.  

- **Engineering view**  
  - Provide internal queries/reports (SQL or admin pages) for:
    - BOM status overview.  
    - Product IDs and their Projects.
    - Audit log filters (by date, user, entity, action).  
    - Where-used reports for components (which BOMs/Projects/Product IDs reference a given component or variant).
    - Lists of BOMs that currently include banned or obsolete components to support cleanup and impact analysis.  
  - UI-level dashboards are nice-to-have for later phases, not mandatory for v1.  

---

### 9.3 Privacy & Security  

- **Business view**  
  - Analytics should not expose sensitive technical or internal information more widely than necessary.

- **Engineering view**  
  - Logs and reports:
    - Accessible only to authorized roles (e.g., Admin, certain managers).  
    - Respect internal policies on log retention and access.  

---

## SECTION 10 — NON‑FUNCTIONAL REQUIREMENTS  

### 10.1 Performance  

- **Business view**  
  - The app must feel responsive; users should not wait several seconds for common actions.

- **Engineering view**  
  - Targets:
    - Login: < 500 ms page render under typical conditions.  
    - BOM search/list load: < 1 second for typical sets.  
    - BOM detail load: < 1 second.  
    - Comparison/matrix views: < 2 seconds for typical BOM sizes and column counts.  
  - Use efficient indexing, query optimization, and caching (if needed) to hit these targets.  

---

### 10.2 Reliability & Availability  

- **Business view**  
  - Bommer should be available during standard working hours without frequent interruptions.

- **Engineering view**  
  - Aim for high uptime during business hours (formal SLAs can be defined later).  
  - Implement backups (DB and configuration).  
  - Ensure that errors in optional features do not take down core BOM functionality.  
  - Implement client-side autosave for BOM editing using browser storage (localStorage or IndexedDB), scoped by user and BOM, with near real-time persistence and draft cleanup after successful save, explicit discard, or starting a new BOM.  
  - Ensure edit locks are robust against crashes and network loss by using heartbeat-based timeouts (e.g., 60 minutes) and clear messaging when a lock conflict or timeout occurs.

---

### 10.3 Security  

- **Business view**  
  - BOM data must be protected from unauthorized access or manipulation.

- **Engineering view**  
  - Enforce:
    - Auth and roles via existing login system.  
    - CSRF protection on all state‑changing requests.  
    - Session hardening (regeneration, fingerprinting, account lockout, remember‑me security).  
    - PDO prepared statements for all DB access.  
  - Ensure BOM, Project, and Product ID data is only accessible according to defined permissions.

---

### 10.4 Maintainability  

- **Business view**  
  - The system should be maintainable by the internal dev team without excessive cost.

- **Engineering view**  
  - Code guidelines:
    - Clear separation between domain logic (BOMs, Product IDs) and infrastructure (auth, DB).
    - Consistent use of helper functions and patterns for permission checks.  
  - Documentation:
    - Keep PRD, architecture, and auth docs updated with BOM/Product ID modules.

---

### 10.5 Scalability  

- **Business view**  
  - As BOM volume grows, the system should scale without major rewrites.

- **Engineering view**  
  - Design the schema and queries with growth in mind.  
  - Consider future ability to:
    - Partition data (by Project/Product ID) if necessary.
    - Add search indexing or caching layers if usage grows.  

---

## SECTION 11 — CONSTRAINTS & ASSUMPTIONS  

### 11.1 Constraints  

- **Business view**  
  - Internal tool, one organization, limited budget/time for v1.  
  - v1 must be ready in about 3 weeks.

- **Engineering view**  
  - Technical:
    - Must use PHP 8.3.6 and MySQL 8.3.0.  
    - Must use Clarity Design System and local assets only.  
  - Organizational:
    - Limited number of active developers.  
    - Must integrate with existing auth architecture and deployment practices.  

---

### 11.2 Assumptions  

- **Business view**  
  - Project Managers and Sales will be trained in using Bommer.  
  - Once Bommer is available, new BOMs will be created only in Bommer (no new Excel BOMs).

- **Engineering view**  
  - Existing auth system is stable and remains in place.  
  - Network and server infrastructure can support expected loads.  
  - Any external integrations (e.g., ERP) will be handled in later phases and are not required for v1’s BOM management to be useful internally.  

---

### 11.3 Dependencies  

- **Business view**  
  - Successful rollout depends on user training and change management (moving away from Excel).

- **Engineering view**  
  - Depends on:
    - Database provisioning and access (MySQL 8.3.0).  
    - Web server configuration (Apache, HTTPS, local assets).  
    - Existing auth module and its configuration.  

---

## SECTION 12 — OPEN QUESTIONS & FUTURE ENHANCEMENTS  

### 12.1 Open Questions  

- **Business view**  
  - Should there be explicit workflow steps around BOM approval (e.g., “submitted”, “under review”, “approved”), or is a simple status field sufficient?  
  - Are there specific reporting views that stakeholders need (beyond basic lists and logs)?  
  - Do different departments require different visibility into Product IDs and Projects?

- **Engineering view**  
  - How granular should permissions be (e.g., per‑Project or per‑Product ID access rules)?
  - Should matrix and comparison views support selective revision choices (e.g., compare v1 vs v3 of a BOM)?  
  - Are there future integration targets (ERP, costing tools) that should influence current schema design (e.g., adding fields for supplier codes, pricing, etc.)?  

---

### 12.2 Future Enhancements (Backlog)  

- **Business view**  
  - **Self‑service password reset** workflow to reduce dependency on admins.  
  - **AI assistance**:
    - Suggest BOM components based on project metadata.  
    - Automatically highlight risky differences between BOM versions or variants.  
  - **Advanced reporting dashboards**:
    - BOM usage, component usage, product complexity, etc.

- **Engineering view**  
  - Potential enhancements:
    - REST or GraphQL API layer for BOM data.  
    - Integration with external systems (ERP/PLM) for component and cost data.  
    - More sophisticated search (full‑text, fuzzy matching).  
    - Performance optimizations for very large BOMs and products (pagination, virtualized rendering, caching).

---

That completes the PRD **Sections 6–12** in the same style and constraints as Sections 1–5.  
If you want, I can now help you break this into **12 separate `.md` sections** with filenames (e.g., `PRD-06-Permissions-Roles.md`, etc.), or refine any specific section further.