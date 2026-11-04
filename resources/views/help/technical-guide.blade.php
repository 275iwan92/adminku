{{-- REMARK
 Tujuan       : Technical Guide AdminKU (HTML, English); bagian 13 GA, 14 HR, 15 Purchase, 16 Kas: tabel, siklus dokumen/approval, modul dan hak, keputusan tiap modul. Flow besar sistem Stok, siklus dokumen (termasuk Stok Opname), buku besar stok, model data, akses dan sesi, tambah cepat, pesanan dan lead time, konvensi, operasional (pasang, konfigurasi, tes).
                Halaman dokumentasi murni, jadi memakai HTML biasa (kelas sstock-guide di resources/css/app.css), bukan komponen x-dl.
 Sumber hulu  : kode (StockDocuments, StockLedger, Modules, routes/web.php) dan HANDOVER.md; ubah halaman ini bila perilaku itu berubah.
 Output       : tab Help (menu profil > Help & support) di Layer Shell.
 Dipakai oleh : route help.technical-guide (hanya Administrator).
 Status       : Aktif.
--}}
@extends('layout', ['title' => 'Technical Guide', 'bodyClass' => 'sstock-bare'])
@section('body')
@include('help.tabs', ['active' => 'tg'])
<div class="sstock-guide">
    <nav class="g-toc" aria-label="Contents">
        <strong>Technical Guide</strong>
        <a href="#overview">1. System overview</a>
        <a href="#big-flow">2. The big flow</a>
        <a href="#business">3. Business flow</a>
        <a href="#lifecycle">4. Document lifecycle</a>
        <a class="g-toc-sub" href="#phone-view">Phone view</a>
        <a href="#ledger">5. Stock ledger</a>
        <a href="#data">6. Data model</a>
        <a href="#access">7. Access and security</a>
        <a href="#screens">8. Dashboard and reports</a>
        <a href="#conventions">9. Development conventions</a>
        <a href="#operations">10. Operations</a>
        <a class="g-toc-sub" href="#install">Install steps</a>
        <a class="g-toc-sub" href="#testing">Testing</a>
        <a href="#decisions">11. Key decisions and why</a>
        <a href="#open">12. Open items</a>
        <a href="#ga">13. GA: vehicle and AC care</a>
        <a class="g-toc-sub" href="#ga-tables">Tables and prefix rule</a>
        <a class="g-toc-sub" href="#ga-vehicle">Vehicle due dates and status</a>
        <a class="g-toc-sub" href="#ga-ac">AC due dates and codes</a>
        <a class="g-toc-sub" href="#ga-capacity">Scheduling and capacity</a>
        <a class="g-toc-sub" href="#ga-visit">Visit state machine</a>
        <a class="g-toc-sub" href="#ga-messages">Messages and minutes</a>
        <a class="g-toc-sub" href="#ga-files">Attachments</a>
        <a class="g-toc-sub" href="#ga-settings">Settings</a>
        <a class="g-toc-sub" href="#ga-modules">Modules and rights</a>
        <a class="g-toc-sub" href="#ga-routes">Routes</a>
        <a class="g-toc-sub" href="#ga-decisions">GA decisions</a>
        <a class="g-toc-sub" href="#ga-open">GA open items</a>
        <a href="#hr">14. HR: employees, recruitment, discipline</a>
        <a class="g-toc-sub" href="#hr-tables">Tables</a>
        <a class="g-toc-sub" href="#hr-recruit">Recruitment board and moves</a>
        <a class="g-toc-sub" href="#hr-discipline">Discipline letters</a>
        <a class="g-toc-sub" href="#hr-modules">Modules and rights</a>
        <a href="#purchase">15. Purchase: PR, PO, PV</a>
        <a class="g-toc-sub" href="#pur-tables">Tables</a>
        <a class="g-toc-sub" href="#pur-lifecycle">Document lifecycle</a>
        <a class="g-toc-sub" href="#pur-modules">Modules and rights</a>
        <a href="#kas">16. Kas: cash in, cash out, accounts</a>
        <a class="g-toc-sub" href="#kas-tables">Tables</a>
        <a class="g-toc-sub" href="#kas-lifecycle">Document lifecycle</a>
        <a class="g-toc-sub" href="#kas-modules">Modules, rights and simplifications</a>
        <a class="g-toc-sub" href="#kas-reports">Reports</a>
    </nav>

    <main class="g-body">
        <h1>AdminKU Technical Guide</h1>
        <p class="g-lead">How AdminKU works from login to report: where stock is entered, how it is posted, who may do what, and where each rule lives in the code. Administrator access only.</p>

        <h2 id="overview">1. System overview</h2>
        <p>AdminKU started as a simple inventory application (module Stok) and now also covers GA, HR, Purchase and Kas for Jays. <b>Master data</b> is defined once, <b>stock documents</b> record every change, the <b>ledger</b> holds the balances, and <b>reports plus the dashboard</b> read the result.</p>
        <table class="g-table">
            <thead><tr><th>Layer</th><th>What it is</th><th>Where</th></tr></thead>
            <tbody>
                <tr><td>Stack</td><td>Laravel 13, PHP 8.3 or newer (<code>"php": "^8.3"</code>), MySQL 8 (tests: in-memory SQLite), Vite 8 + Tailwind 4 (Node 20.19+ or 22.12+)</td><td><code>composer.json</code>, <code>package.json</code>, <code>vite.config.js</code></td></tr>
                <tr><td>UI</td><td>DevLib components (<code>x-dl::*</code>, package <code>jo/devlib</code>, a Composer path repository at <code>../devlib</code>); screens are declarations, not hand-built markup</td><td><code>resources/views</code>, <code>config/devlib.php</code></td></tr>
                <tr><td>Translation</td><td>Screen labels are written in English and translated to Indonesian by an app dictionary</td><td><code>app/Support/Indonesian.php</code>, <code>lang/id</code></td></tr>
                <tr><td>Routing and guards</td><td>One route file; middleware <code>auth</code>, <code>active</code>, <code>module</code> run in that order on every logged-in URL</td><td><code>routes/web.php</code>, <code>EnsureActive</code>, <code>EnsureModuleAccess</code></td></tr>
                <tr><td>Business logic</td><td>Services: document lifecycle (<code>StockDocuments</code>), stock ledger (<code>StockLedger</code>), valuation (<code>StockValuation</code>), Stok Opname (<code>StockCounts</code>, <code>CountSchedule</code>), re-order orders and lead time (<code>ReorderOrders</code>, <code>LeadTimes</code>), incoming units (<code>ProductUnits</code>), sample data (<code>SampleData</code>)</td><td><code>app/Services</code></td></tr>
                <tr><td>Data</td><td>Master tables, documents, ledger</td><td><code>database/migrations</code></td></tr>
            </tbody>
        </table>

        <h2 id="big-flow">2. The big flow</h2>
        @include('help.diagram', ['name' => 'tg-system-flow', 'caption' => 'System flow: from login to reports. StockLedger is the only writer of stock.'])
        <p class="g-note">A confirmed Receiving adds its quantities to the open re-order records of the products it contains. <code>ReorderOrders::reconcile</code> allocates them FIFO and closes a record only when its <code>qty</code> is reached. Cancelling that Receiving recomputes the records, so an order that is no longer complete reopens.</p>

        <h3>Product and batch model</h3>
        @include('help.diagram', ['name' => 'tg-product-batch', 'caption' => 'Product and batch model: tables, document lines, FEFO allocation and the per-product reorder point.'])

        <h2 id="business">3. Business flow</h2>
        <ol class="g-steps">
            <li><b>Set up master data.</b> Categories, locations, units with conversions, suppliers, departments, recipients, items. Items are two-level: a <b>product</b> (<code>m_item</code>, stable code <code>{CATEGORY PREFIX, 3 chars}-{4-digit sequence}</code> from <code>Product::nextCode</code>, never contains a date) and its <b>batches</b> (<code>items</code>, one per expiry date, batch code <code>{product code}-{YYMMDD}</code>; one undated batch with the product code when the product does not expire). Code, category and <code>tracks_expiry</code> are locked once saved; any user may add a product, only a Supervisor edits parameters (propagated to the batches by <code>Product::propagate</code>). Supplier, Recipient, Department, Category, Unit and Location dropdowns carry a quick-add plus button (section 7). The system supplier <b>Kontan</b> (cash or unknown supplier) always exists.</li>
            <li><b>Opening balance.</b> Supervisor only and optional: the first Receiving is never turned into an opening balance. The date is chosen on the form (not after today) and becomes <code>moved_at</code>. Confirm is rejected by <code>StockLedger::post</code> for a batch that already has any stock movement, a reversal row included (use Adjustment instead). The Stock Card reads the opening balance as an <code>OPENING</code> row when the report period starts on or before that date, otherwise it is folded into the opening balance of the report.</li>
            <li><b>Receiving.</b> <code>supplier_id</code> is required (an active supplier; <b>Kontan</b> for cash purchases). A line names a product and, for <code>tracks_expiry</code> products, an expiry date (required): <code>Product::batchFor()</code> finds or creates the batch (unused batches of a deleted or edited draft are purged). A Receiving line may use another unit; it is converted to the item's stock unit by <code>UnitConverter</code>, which looks first at the product's own <b>incoming units</b> (<code>product_units</code>: <code>1 {unit} = {factor} {products.unit}</code>, stock unit implied as 1) and only then at the global pair in <code>unit_conversions</code> (fallback, never deleted). Issuance and Adjustment are always in the stock unit: no unit picker, a posted <code>unit</code> is ignored, and no <code>entered_*</code> is written (old rows that have them reopen through <code>qty</code>). <code>ProductUnits::options()</code> gives the row its choices (stock unit, own units, global factors for other units); <code>docs/pack-units.blade.php</code> replaces <code>cellRaw</code> / <code>refresh</code> of the <code>lines</code> component on the page (no DevLib change) to list only those units per row and to show the live "= 60 pcs" before Confirm; the server remains the only calculation that counts (<code>StockDocuments::toStockUnit</code>, <code>Decimal</code> bcmath). <code>StockDocuments::assertConversions()</code> collects every product + unit that has neither factor, flashes <code>missing_conversions</code> (<code>product_id, from, to, items</code>) and rejects the save; the form opens the "missing factor" modal (<code>docs/conversion-modal</code>) which posts each pair to <code>docs.conversion</code>, creating the PRODUCT's incoming unit (not a global conversion), then the user saves again.</li>
            <li><b>Issuance.</b> A recipient is required. Stock may never go negative. A line names a batch; a quantity above that batch's stock spills over to the other usable batches of the same product (earliest expiry first, FEFO; <code>StockDocuments::allocate</code>). Picking a later-expiring batch while an earlier one has stock only warns (<code>fefoWarnings</code>), it never blocks.</li>
            <li><b>Adjustment.</b> The line quantity is the difference <code>counted_qty - system_qty</code>. It needs Supervisor approval (single level; the Admin Data role opens the same screens as a Supervisor but <code>User::canApprove()</code> is false, so approve / reject are 403 and their buttons are not drawn; multi-level authority is deliberately not used in the stock app, it belongs to purchasing and sales).</li>
            <li><b>Reports.</b> Most have a from / to date filter (Expiry uses a days window instead). Prices and values are visible to Supervisors only.</li>
        </ol>

        <h2 id="lifecycle">4. Document lifecycle</h2>
        <p>Four document types share one controller (<code>StockDocumentController</code>, routes <code>docs/{type}</code> with type <code>opening | receiving | issuance | adjustment</code>) and one service (<code>StockDocuments</code>). The controller reads <code>_action</code> from the form button: <code>save, confirm, delete, cancel, approve, reject</code>. Document numbers are <code>PREFIX-YYMM-NNNN</code> (OPN, RCV, ISS, ADJ), sequenced per type per month. Numbering is race-safe: the column <code>doc_no</code> is unique and <code>StockDocuments::createNumbered</code> reads the last number, tries to insert, and retries up to 5 times when the unique index rejects a collision. Batch creation follows the same idea (<code>Product::batchFor</code> uses <code>createOrFirst</code> on the unique batch code).</p>

        <h3>Opening balance, Receiving, Issuance</h3>
        @include('help.diagram', ['name' => 'tg-doc-lifecycle', 'caption' => 'State diagram of Opening Balance, Receiving and Issuance documents.'])
        <table class="g-table">
            <thead><tr><th>From</th><th>Action</th><th>To</th><th>Stock effect</th></tr></thead>
            <tbody>
                <tr><td>(new)</td><td>Save</td><td><span class="g-st g-grey">draft</span></td><td>None</td></tr>
                <tr><td>draft</td><td>Delete</td><td>(removed)</td><td>None</td></tr>
                <tr><td>draft</td><td>Confirm</td><td><span class="g-st g-green">confirmed</span></td><td>Posted to the ledger in one DB transaction</td></tr>
                <tr><td>confirmed</td><td>Cancel by Supervisor/Admin</td><td><span class="g-st g-red">cancelled</span></td><td>Reversed (new reversal rows)</td></tr>
                <tr><td>confirmed</td><td>Cancel by Data Entry (reason required)</td><td><span class="g-st g-yellow">cancel_pending</span></td><td>None yet</td></tr>
                <tr><td>cancel_pending</td><td>Supervisor Approve</td><td><span class="g-st g-red">cancelled</span></td><td>Reversed</td></tr>
                <tr><td>cancel_pending</td><td>Supervisor Reject, or requester withdraws</td><td><span class="g-st g-green">confirmed</span></td><td>None</td></tr>
            </tbody>
        </table>

        <h3>Adjustment</h3>
        @include('help.diagram', ['name' => 'tg-adjustment-lifecycle', 'caption' => 'State diagram of an Adjustment document.'])
        <table class="g-table">
            <thead><tr><th>From</th><th>Action</th><th>To</th><th>Stock effect</th></tr></thead>
            <tbody>
                <tr><td>(new)</td><td>Save</td><td><span class="g-st g-grey">draft</span></td><td>None</td></tr>
                <tr><td>draft</td><td>Confirm (submit)</td><td><span class="g-st g-yellow">approval</span></td><td>None yet</td></tr>
                <tr><td>approval</td><td>Supervisor Approve</td><td><span class="g-st g-green">approved</span></td><td>Posted to the ledger</td></tr>
                <tr><td>approval</td><td>Supervisor Reject</td><td><span class="g-st g-red">rejected</span></td><td>None</td></tr>
                <tr><td>approval</td><td>Withdraw (Cancel)</td><td><span class="g-st g-red">cancelled</span></td><td>None (nothing was posted)</td></tr>
                <tr><td>approved</td><td>Cancel by Supervisor/Admin</td><td><span class="g-st g-red">cancelled</span></td><td>Reversed</td></tr>
                <tr><td>approved</td><td>Cancel by Data Entry</td><td><span class="g-st g-yellow">cancel_pending</span></td><td>None yet; Approve cancels, Reject returns to approved</td></tr>
            </tbody>
        </table>

        <h3>Stock count (Stok Opname)</h3>
        @include('help.diagram', ['name' => 'tg-stock-count-lifecycle', 'caption' => 'State diagram of a Stok Opname (stock count).'])
        <p>A routine physical count of many batches at once. It is separate from the ad-hoc Adjustment above and does not change it. Module <code>SO</code> (group Transaksi, minimum role Data Entry, guarded route prefix <code>stock-counts.</code>). Logic: <code>App\Services\StockCounts</code>; screens: <code>StockCountController</code>, views <code>stock-counts/*</code>; print data: <code>App\Support\StockCountPrint</code> (<code>x-dl::print-sheet</code>, A4 landscape).</p>
        <table class="g-table">
            <thead><tr><th>From</th><th>Action (who)</th><th>To</th><th>Stock effect</th></tr></thead>
            <tbody>
                <tr><td>(new)</td><td><code>start()</code> (Supervisor)</td><td><span class="g-st g-yellow">counting</span></td><td>None. Snapshot: one <code>stock_count_lines</code> row per active batch with stock &gt; 0 in scope (<code>system_qty</code>, <code>unit_cost</code> = average cost at <code>cutoff_at</code>). Only ONE count may be counting / review at a time</td></tr>
                <tr><td>counting</td><td><code>saveLine()</code> (module RW), <code>addItem()</code></td><td>counting</td><td>None. <code>counted_qty</code> null = not counted yet, 0 = empty; <code>skipped</code> needs a note. Blind count: <code>system_qty</code> is never sent to the browser (lookup <code>item_count</code> also omits stock)</td></tr>
                <tr><td>counting</td><td><code>finish()</code> (module RW)</td><td><span class="g-st g-yellow">review</span></td><td>None. Refused while any line is empty or skipped without a note</td></tr>
                <tr><td>review</td><td><code>reviewLine()</code> (Supervisor): <code>recount_qty</code>, <code>reason</code></td><td>review</td><td>None. Final result = <code>recount_qty</code> if set, else <code>counted_qty</code></td></tr>
                <tr><td>review</td><td><code>approve()</code> (Supervisor)</td><td><span class="g-st g-green">done</span></td><td>One approved ADJ document (<code>reason</code> = "Stok Opname SO-..."), posted by <code>StockLedger::post</code>; none if no line differs</td></tr>
                <tr><td>review</td><td><code>reject()</code> with note, <code>refreshMoved()</code> (all moved lines) or <code>refreshOne()</code> (one moved line) (Supervisor)</td><td><span class="g-st g-yellow">counting</span></td><td>None. Refresh sets <code>system_qty</code> to current stock and clears the line; <code>refreshOne()</code> refuses (422) a line whose stock did not move</td></tr>
                <tr><td>counting, review</td><td><code>cancel()</code> (Supervisor)</td><td><span class="g-st g-red">cancelled</span></td><td>None; lock released</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Approval checks</b> (one DB transaction, count row and item rows locked): every line final; a reason on every line whose |difference| exceeds <code>threshold_pct</code> of <code>system_qty</code> (compared without rounding with bcmath; <code>system_qty</code> 0 and any difference always exceeds); for every line with a difference the item's current <code>stock_qty</code> must still equal <code>system_qty</code>, otherwise the approval is refused with the list and "Refresh moved lines" is needed. Then status <code>done</code> is written FIRST (so the lock is gone), then <code>StockDocuments::recordCountAdjustment()</code> creates the ADJ document and posts it; any failure rolls everything back.</li>
            <li><b>Transaction lock:</b> a single guard, <code>StockLedger::assertNotLocked()</code>, at the top of <code>post()</code> and <code>reverse()</code>, reads <code>StockCount::lockingNow()</code> (status counting / review and <code>lock_transactions</code>). So Confirm (Receiving, Issuance, Opening), Approve (Adjustment) and Cancel of a processed document are all refused while locked; drafts and submitting an Adjustment for approval do not post and stay allowed. <code>unlock()</code> sets <code>lock_transactions</code> false and records <code>unlocked_by/at</code>.</li>
            <li><b>Audit and soft guard:</b> every save that changes a line records <code>entered_by</code> / <code>entered_at</code> (the account; <code>counted_by</code> stays the free-text counter on paper) and increments <code>rev</code>. The sheet posts the row's <code>rev</code> back; if it differs (someone else saved since the screen loaded) <code>StockCountRowChanged</code> becomes HTTP 409 <code>{reason_required, error}</code> and the DevLib sheet asks for a reason; posting again with a <code>reason</code> overwrites (Replace, never adds). A batch is one DB transaction, so a conflict saves none of it. The approve screen warns (never blocks) when the approver entered more than half of the lines (<code>StockCounts::enteredShare</code>).</li>
            <li><b>Lock / unlock:</b> <code>lock()</code> and <code>unlock()</code> (Supervisor) toggle <code>lock_transactions</code> and record <code>locked_by/at</code> and <code>unlocked_by/at</code>. Not blind: the input sheet also gets <code>system_qty</code> and a server-calculated <code>diff</code> per row.</li>
            <li><b>Schedule and reminder:</b> <code>App\Services\CountSchedule</code> keeps the schedule in the <code>constants</code> table through <code>App\Support\Constants</code> (<code>opname.frequency</code> weekly|monthly, <code>opname.weekday</code> 1..7, <code>opname.day_of_month</code> 1..31|last, <code>opname.schedule_set_at</code>); default monthly, end of month. Next date = first scheduled date after the last DONE count's <code>count_date</code> (cancelled ignored), else after <code>schedule_set_at</code>, else after today; short months clamp (Feb 28/29). <code>state()</code> feeds the Home reminder (<code>partials/count-reminder</code>, only for users with module SO; tone neutral / warn at H-3..today / danger when late) and <code>badge()</code> the menu badge (<code>Menu</code>; running or late). <code>available()</code> keeps Home and the menu alive before the migration is run. Changing the schedule is the Supervisor-only route <code>stock-counts.schedule</code>.</li>
            <li><b>Numbering:</b> <code>SO-YYMM-NNNN</code> per month; <code>doc_no</code> is unique and the creator retries on a collision, like document numbers.</li>
            <li><b>Screens:</b> the entry and review sheets are <code>x-dl::lookup-grid</code> in auto-save mode; each cell edit POSTs <code>{changed}</code> to <code>stock-counts.lines</code> / <code>stock-counts.review</code> and gets all rows back. The review screen reloads after a save (<code>resources/js/app.js</code>, attribute <code>data-reload-on-save</code>) so the server-calculated summary stays right; this needs <code>npm run build</code>.</li>
            <li><b>Row colour (DevLib opt-in <code>row-tone</code>):</b> <code>x-dl::sheet</code> / <code>x-dl::lookup-grid</code> accept <code>row-tone="&lt;field&gt;"</code>; the row value (<code>danger</code> | <code>warn</code> | <code>success</code>) becomes the class <code>is-tone-*</code> on the <code>&lt;tr&gt;</code>. Without the prop the DevLib markup is unchanged (tmerp is not affected). <code>StockCounts::reviewRows()</code> sets <code>tone = danger</code> on rows over <code>threshold_pct</code>; <code>entryRows()</code> does the same only when the count is NOT blind, so a blind count never leaks the size of a difference through colour.</li>
            <li><b>Backup Save:</b> the entry sheet saves per cell, so the button <i>Save Backup</i> (<code>data-count-save</code>, handler in <code>resources/js/app.js</code>) only confirms. It POSTs <code>{seen}</code> (rows with a count or Not counted, read from the sheet data) to <code>stock-counts.save</code>; <code>StockCounts::enteredCount()</code> counts the same rows in the database. Equal = 200 with a message; different = 409 (an unsaved cell, or another user filled other rows); not counting = 422. The script retries up to three times, 0.7 s apart, so a cell save still in flight can finish.</li>
            <li><b>Refresh one line:</b> <code>POST stock-counts/{count}/lines/{line}/refresh</code> (<code>stock-counts.refresh-line</code>, supervisor middleware) calls <code>refreshOne()</code>, which only accepts a line in <code>movedLines()</code> and then reuses <code>refreshLine()</code>; the review alert shows one button per moved line (first 12) next to <i>Refresh Moved Lines</i>.</li>
            <li><b>Rights:</b> view, fill in, print the count sheet = module <code>SO</code> (RW to write); start, review, approve, return, cancel, unlock, minutes (Berita Acara) and every value = <code>supervisor</code> middleware. Values (difference x average cost) are shown to Supervisors only.</li>
        </ul>

        <h3 id="phone-view">Phone view: choosing and switching</h3>
        <ul class="g-list">
            <li><b>Device variants:</b> phone and desktop are two variants of the DEVICE, not versions. DevLib <code>Devlib\Device</code> guesses (<code>Sec-CH-UA-Mobile</code>, then <code>User-Agent</code>; phones only, tablets and "desktop site" = desktop); the cookie <code>ui</code> (<code>phone</code> | <code>desktop</code>, one year; the old value <code>hp</code> still reads as <code>phone</code>) set by the user always wins. It is written on login (<code>AuthController::login</code>, field <code>ui</code> from <code>x-dl::login-box</code> part <code>device</code>) and by <code>POST /ui</code> (<code>HpController@setDevice</code>, route <code>ui.set</code>): the Menu panel of the phone shell ("Desktop view", not drawn on a real phone, see below) and the "Phone view" action of the desktop shell (<code>x-dl::layer-shell</code> prop <code>deviceAction</code>).</li>
            <li><b>Automatic two-way switching (DevLib, opt-in):</b> sign-in and the desktop opening screen use <code>x-dl::phone-offer auto</code>. When no choice is stored and the screen is phone-sized (<code>Devlib.phoneScreen()</code>: under 600 px wide, or sideways with height under 480 px, coarse pointer, width under 1000 px; also when the window is dragged narrower, through <code>change</code> and <code>resize</code>) it posts <code>ui=phone&amp;auto=1</code>. <code>setDevice</code> then also writes the marker cookie <code>ui_auto=1</code> (<code>Devlib\Device::AUTO_COOKIE</code>); any choice made without <code>auto</code> removes it. While the marker is present the stored <code>ui</code> counts as "not chosen by the user". <code>hp/layout</code> passes <code>auto-return</code> to <code>x-dl::phone-shell</code>: when the phone view is automatic (marker) or the page was opened directly while <code>Device::view</code> says desktop, and the screen is wide again, the Menu sheet form posts <code>ui=desktop&amp;auto=1</code> by itself. A real phone (guessed <code>phone</code>) and a user's own choice are never switched. Both scripts allow one submit per 10 seconds per tab so a browser that refuses cookies cannot loop.</li>
            <li><b>A real phone stays on the phone view:</b> <code>App\Http\Middleware\PhoneStaysPhone</code> (web group) ignores the cookie <code>ui=desktop</code> when the User-Agent is a phone (<code>HpShell::isRealPhone</code>), and <code>HpShell::deviceAction()</code> hides the "Desktop view" control on phones (phone shell menu and phone sign-in). A narrowed desktop window keeps both, so it can return to desktop. <code>GET /ui</code> redirects to Home (only POST sets the device). Test: <code>PhoneViewTest</code>.</li>
            <li><b>Scrolling:</b> DevLib makes <code>body.dl-body</code> a fixed 100vh box. One rule in <code>resources/css/app.css</code> lets the body scroll instead on pages without their own scrolling form (<code>body.dl-body:not(.sstock-bare):not(:has(.dl-form))</code>) and on every page inside the phone frame (<code>body.dl-phone-shelled</code>); desktop forms keep the scroll of the form itself. Phone frame pages hide their own page title (<code>.sstock-pagetitle</code>). Rebuild with <code>npm run build</code>.</li>
            <li><b>Sign-out with an expired token:</b> a <code>419</code> on <code>POST /logout</code> goes to Login instead of the error page (<code>bootstrap/app.php</code>). The favicon follows the application base address (<code>asset('favicon.svg')</code>), so the app also works under a path prefix such as <code>/adminku/</code>.</li>
        </ul>

        <h3 id="phone-shell">Phone view: shell, routes and native screens</h3>
        <ul class="g-list">
            <li><b>Shell:</b> <code>HomeController</code> returns <code>hp.home</code> when the device is <code>phone</code>; <code>x-dl::phone-shell</code> (<code>hp/layout</code>) frames every phone screen with the main menus from <code>App\Support\HpShell</code> (Beranda, Stok, Transaksi, Opname, then Menu). Modules without a phone screen open their desktop page in the frame (<code>hp.open</code>, iframe; the page's own route still enforces module access); the Transaksi tab is <code>hp.group</code>. The desktop screens are not changed.</li>
            <li><b>Count screen:</b> <code>StockCountController@phoneIndex</code> / <code>@phone</code> (routes <code>hp.counts</code>, <code>hp.count</code>, guarded as module <code>SO</code> through the <code>Modules</code> route prefix <code>hp.count</code>); view <code>hp/count</code>, script <code>resources/js/hp-count.js</code>. Same rules and endpoint as desktop: each item is saved with POST <code>stock-counts.lines</code> (one row per request, <code>compact=1</code> returns only that row plus <code>by_id</code>, <code>by</code>, <code>at</code> from <code>StockCounts::entryRows($count, true)</code>; a <code>409</code> also returns the latest row). Blind counts never send <code>system_qty</code>. Finish posts <code>hp=1</code> so the redirect goes to <code>hp.counts</code>.</li>
            <li><b>Not losing input:</b> unsent count entries are kept in memory and <code>localStorage</code> (<code>hpc.stock-count.{id}</code>), retried every 8 s, on <code>online</code> and when the page is hidden; a leave-page warning appears while something is pending. A <code>409</code> (row changed by another person) asks for a reason to overwrite or discards the user's entry. There is no service worker or offline shell yet (needs HTTPS).</li>
            <li><b>Find screen:</b> <code>HpController@find</code> / <code>@product</code> (routes <code>hp.find</code>, <code>hp.find.show</code>, guarded as module <code>ITM</code> through the <code>Modules</code> route prefix <code>hp.find</code>; entry points: link on <code>hp.home</code> and in the <code>links</code> of the shell menu, both only when <code>canAccess('ITM')</code>). Views <code>hp/find</code>, <code>hp/find-show</code>, <code>hp/_find-box</code>; <code>resources/js/hp-find.js</code> only selects the field on focus so the next scan overwrites. One plain GET form, <code>type=text</code> 16 px+ autofocus (a Bluetooth / USB scanner types like a keyboard + Enter); no camera (needs HTTPS). A full product code or batch code (case-insensitive) redirects to the product (<code>?b=</code> marks the scanned batch); otherwise active products matching code / name / location / any batch code, limit 30, as cards with the Level badge (same rule as the Barang grid: available &lt;= reorder point), available stock from <code>Product::withAvailable()</code>. The product page lists batches with stock &gt; 0 in FEFO order (expiry ascending, no-expiry last, then id) with <code>Item::expiryState()</code> badges; stock value only for Supervisor. No new DevLib object: <code>x-dl::phone-link</code>, <code>x-dl::badge</code>, <code>x-dl::button</code>, <code>.dl-phone-card</code>. Test: <code>HpFindTest</code>.</li>
        </ul>

        <h3 id="phone-patterns">Phone view: DevLib patterns used on every screen</h3>
        <p>Every rule below is scoped to the phone context (<code>body.dl-on-phone</code>, which <code>Devlib\Device::bodyClass</code> adds, or <code>.dl-phone</code>); desktop pages match none of them. All of them are opt-in props or phone-only CSS.</p>
        <ul class="g-list">
            <li><b>Lists:</b> every <code>x-dl::grid</code> screen passes <code>:phone-cards</code> (title, sub, badge, tones, meta keys from the grid columns): items, the four document types (<code>docs/index</code> shares one view), Supplier, Satuan, Kategori, Lokasi, Departemen, Penerima, Riwayat Pemesanan, Stok Opname, Pengguna, Grup, Modul. Test: <code>PhoneViewTest</code> (list screens and admin lists).</li>
            <li><b>Forms:</b> master and admin forms are the desktop pages (<code>x-dl::form</code>) opened in the phone frame and follow the DevLib phone form rules (one column, labels on top, 16 px / 44 px fields; Save / Undo / Back move to the shell action bar). DevLib adds: dropdown / lookup rows of 44 px, form tabs of 44 px, error text 14 px and hint 13 px, radio / checkbox rows of 44 px, a 44 px tap area for the <code>aa</code> and <code>,00</code> chips (the chip itself stays small), the number up / down arrows hidden, and dialogs (quick add, pack unit) with a 44 px close button and 48 px footer buttons. Small tables inside a form use <code>x-dl::table :stack="true"</code> (<code>products/form</code>: Batch and Satuan masuk). The reorder advice box styles itself under <code>body.dl-on-phone</code> (<code>products/_reorder-advice</code>). Forgot / reset password render <code>auth/_*-box</code> in <code>main.dl-plogin</code> on a phone and in <code>x-dl::frame</code> on desktop. Test: <code>PhoneFormsTest</code>.</li>
            <li><b>Document lines:</b> <code>x-dl::lines</code> (<code>docs/form</code>) becomes one card per row: <code>lines.js</code> sets <code>data-label</code> on every cell, CSS turns <code>tr[data-i]</code> into a 6-column grid (number left / row buttons right on the first line, lookup, text and the note cell full width, number / read-only cells span 2, date / select cells span 3). Fields are 16 px / 44 px, the row icon buttons are 44 x 44 and the case chip 40 x 36; the footer sums row is hidden, the <code>totals</code> Summary stays. No prop; the desktop table is unchanged.</li>
            <li><b>Reports:</b> the six reports pass <code>phone-list</code> to <code>x-dl::report</code> (new opt-in prop of <code>x-dl::print-out</code>). On a phone the A4 sheets (shrunk to about a third, text about 3.5 px) give way on screen to a reading list built by <code>print.js</code> <code>list()</code> from the same rows: criteria box and KPI tiles, one card per row (first column = title, a second text column = subtitle, the rest as label / value pairs, badges, row tones), group headings with sub totals, and the grand total. The group switch rebuilds it; printing still uses the sheets. Test: <code>PhoneReportsTest</code>.</li>
            <li><b>Tap targets:</b> a DevLib block (end of <code>devlib.css</code>) enlarges the date / pick trigger (<code>.dl-pick &gt; .dl-field__trigger</code>, 44 px wide), the print bar segments (Kertas, Grup) and the line card buttons. The Help pages use a 44 px minimum for the table of contents on narrow screens (<code>resources/css/app.css</code>; rebuild with <code>npm run build</code>).</li>
        </ul>

        <h3 id="phone-testing">Phone view: testing, measuring and audit results</h3>
        <ul class="g-list">
            <li><b>Screen measuring page:</b> <code>GET /ukur</code> (route <code>ukur</code>, public, <code>resources/views/ukur.blade.php</code>) is the DevLib object <code>x-dl::screen-info</code>: it measures on the device itself (CSS width x height, DPR, orientation, <code>svh</code> / <code>lvh</code> / <code>dvh</code>, <code>visualViewport</code>, touch, safe area, the screen rule, the served view) and "Copy result" puts one text block on the clipboard. Client side only. Test: <code>ScreenInfoPageTest</code>.</li>
            <li><b>Automated tests:</b> <code>PhoneViewTest</code> (device choice, cookies, a real phone never switching to desktop, automatic switching of a narrowed window, shell, list screens, admin lists, and a guard that every phone screen renders the hooks the phone shell script binds to), <code>PhoneFormsTest</code> (14 form screens open on a phone without error and stay desktop on a computer), <code>PhoneReportsTest</code>, <code>HpStockCountTest</code>, <code>HpFindTest</code>, <code>HomeGreetingTest</code>.</li>
            <li><b>Browser probe (2026-10-28):</b> about 35 screens were opened one by one in the Browser pane at 393 px (the pane sends a phone User-Agent below 768 px) with a script that measures: horizontal overflow (<code>scrollWidth</code> against <code>innerWidth</code> and elements past the right edge outside scroll containers), input font size (16 px minimum) and buttons or links under 38 px. Screens: sign-in, Beranda, Cari Barang (empty, result), Opname, Barang (list, form), Supplier, Satuan, Kategori, Lokasi, Departemen, Penerima (list and form each), Perusahaan, the four document types (list and form), Riwayat Pemesanan, Stok Opname (list, new), six reports, Pengguna, Grup (list, form), Modul, both Help guides, <code>/ukur</code>. Result: no horizontal overflow on any screen, no input under 16 px. Findings fixed: line card buttons (30 px), the date trigger (34 px wide), print bar segments (31 px high), Pengguna and Grup lists (22 px row icons; now cards), Help table of contents links (21 px high).</li>
            <li><b>Interaction tests (2026-10-29, real clicks and typing at 393 px, then 360 / 430 px and 740 x 360 sideways):</b> sign-in by typing and Enter; Receiving from start to finish (supplier dropdown, item lookup by typing, a decimal quantity <code>12,5</code>, row Save, document Save with the required-field message and the Data tab counter, Confirm dialog, stock check in Cari Barang 42 to 54.50, Cancel with a reason, stock restored); Issuance item lookup; Stok Opname create form, the count screen (<code>12,5</code> + Enter saves and moves on, a simulated network failure keeps the entry on the device and the <code>online</code> event sends it, the summary card) and Cancel. Finding fixed: the item lookup popup of <code>x-dl::lines</code> was a 422 px table positioned in the page, so the page grew to 466 px and the item name wrapped over four lines; on a phone it is now full width with a two-line card per result and labelled cells ("Code: ... &middot; Stock: ..."). Also enlarged to 44 px: the pager buttons, the page size choices and the filter chips. The sideways size (740 x 360) and 360 / 430 px showed no overflow on Beranda, Cari Barang, a document form and a report. Test data created by these runs (document <code>RCV-2610-0003</code> and count <code>SO-2610-0001</code>) was cancelled; stock is back to its earlier values.</li>
            <li><b>Method limits:</b> the probe measures boxes; it does not test typing, pickers, the keyboard, or a real device. Pseudo-element hit areas (the <code>aa</code> and <code>,00</code> chips) are not measured. The browser pane does not emit <code>resize</code> or media query <code>change</code> events when its size is set, so the automatic switch was verified by dispatching a <code>resize</code> event by hand; a real browser sends them.</li>
            <li><b>Open items:</b> real Android and iPhone checks of QC scenarios HPV-01 to HPV-30; the Grup form lists 24 modules as tall cards (about 180 px each); the region lookup needs a re-check after the region tables are imported; printing from a phone is untested; camera scanning, install-to-home-screen and offline counting need HTTPS.</li>
        </ul>

        <h3>Rules that apply to every document</h3>
        <ul class="g-list">
            <li><b>Draft</b> never touches stock. Only a draft can be edited or deleted. Confirm needs at least one line.</li>
            <li><b>Row lock and idempotence.</b> Every state change (confirm, submit, approve, reject, cancel, delete, update) starts with <code>lockAndAssert</code>: the document row is re-read <code>FOR UPDATE</code> and its status is checked on that fresh copy. A double click or two parallel requests therefore cannot post twice, and the second one gets "Aksi ini tidak diizinkan pada status sekarang". <code>StockLedger::reverse</code> only reverses movements that have no reversal yet (<code>unreversed()</code>), so a second cancel never reverses again. Tests: <code>DoublePostingGuardTest</code>, <code>NumberingRaceTest</code>.</li>
            <li><b>Confirm</b> sets the document date/time to the moment of Confirm (Opening balance keeps the chosen date). A confirmed document is locked; correct it with an Adjustment.</li>
            <li><b>Approving an Adjustment</b> is refused if the item's stock changed since it was counted (<code>system_qty</code> differs). Reject it and count again.</li>
            <li><b>Cancel</b> needs a reason (3 to 255 characters). Before accepting a cancel or a request, <code>StockLedger::assertReversible</code> computes whether stock is still enough to pull the document back. If not, the cancel is refused and an Adjustment is needed. The result is also shown as a warning on the form before the button is pressed.</li>
            <li><b>Cancel authority:</b> a Supervisor or Administrator cancels directly. Others request. Only the requester or a Supervisor may withdraw a pending request (403 for anyone else).</li>
            <li>Detail rows use <code>x-dl::lines :row-state="true"</code> (DevLib, opt-in): each row is in state <i>edit</i> (Save + Undo) or <i>saved</i> (Edit + Delete); saved rows are locked, Undo drops a new row or restores the pre-Edit values, and the readonly (Confirmed) table shows no row buttons. Row data is posted in both states, so the form Save also commits an open, complete row.</li>
            <li>The Confirm, Delete, Cancel, Approve and Reject buttons come from the DevLib form status (<code>x-dl::form kind="document" :pipeline="true"</code>). They are not built by hand. Every stage is written to the form log.</li>
        </ul>

        <h2 id="ledger">5. Stock ledger</h2>
        <p>Only <code>App\Services\StockLedger</code> may change <code>items.stock_qty</code> and <code>items.avg_cost</code>. The columns are deliberately not mass-assignable; the ledger writes them with <code>forceFill</code>.</p>
        @include('help.diagram', ['name' => 'tg-stock-ledger', 'caption' => 'What StockLedger::post does for every document line, and how Cancel reverses it.'])
        <ol class="g-steps">
            <li><b>Lock</b> all item rows of the document at once, ordered by id (<code>lockItems</code>), so two documents holding the same items never wait on each other in a circle; each line then re-reads its item <code>FOR UPDATE</code>. Before that, <code>assertNotLocked()</code> refuses the posting while a Stok Opname holds the transaction lock.</li>
            <li><b>Signed quantity:</b> Issuance is negative; Opening and Receiving positive; Adjustment carries its own sign. A zero difference is skipped.</li>
            <li><b>Guard:</b> a resulting balance below zero throws a validation error (insufficient stock).</li>
            <li><b>Cost:</b> additions from Receiving or Opening use the line cost; positive Adjustments use the current average cost (or the item price if there is none); removals use the average cost.</li>
            <li><b>Moving average:</b> <code>(stock x avg + qty x cost) / new balance</code> on additions. Values (average cost, unit conversion, line and stock value) use exact <code>bcmath</code> arithmetic through <code>App\Support\Decimal</code> and are rounded once at the end; quantities keep their 3-decimal rounding.</li>
            <li><b>Write:</b> update the item and insert a <code>stock_movements</code> row with the balance after the movement. Products at or below the Reorder Point are returned and shown as an alert on the form. The reorder check is per <b>product</b> on the available stock (sum of batches with no expiry or expiry on/after today plus <code>sstock.reorder_exclude_days</code>), so expired batches never count as available.</li>
        </ol>
        <ul class="g-list">
            <li>Database integrity for batches (migration <code>add_integrity_constraints_to_items</code>): CHECK <code>stock_qty &gt;= 0</code> (MySQL / MariaDB only), unique <code>(product_id, expiry_date)</code> and an index on <code>expiry_date</code>. The migration stops without changing data when existing rows violate a rule. The ledger still guards negative stock itself, which is the only guard on SQLite (tests).</li>
            <li><code>stock_movements</code> rows are never edited or deleted. <code>reverse()</code> adds new rows with <code>reversal_of</code> pointing at the original; the Stock Card marks them "(CANCELLED)".</li>
            <li><code>StockValuation::series</code> builds the weekly stock-value chart from <code>balance_qty x balance_avg_cost</code> in the ledger (Supervisor only).</li>
        </ul>

        <h2 id="data">6. Data model</h2>
        @include('help.diagram', ['name' => 'tg-erd', 'caption' => 'Entity relationship diagram: key columns and relations.'])
        <table class="g-table">
            <thead><tr><th>Table</th><th>Purpose</th><th>Key relations</th></tr></thead>
            <tbody>
                <tr><td><code>m_category</code>, <code>m_location</code>, <code>m_unit</code>, <code>m_unit_conversion</code></td><td>Item classification, storage places, units and the global conversion factors (fallback only; <code>created_by</code> / <code>updated_by</code> record who changed a pair)</td><td>items belong to a category; <code>items.location</code> stores the location <b>code</b></td></tr>
                <tr><td><code>m_supplier</code> (+ <code>geo_*</code>)</td><td>Vendors with contact and region (region tables filled by <code>sstock:import-geo</code>; region is required only when <code>geo_city</code> has rows, see <code>GeoArea::available()</code>). <code>is_system</code> marks the built-in supplier <b>Kontan</b> (code <code>KONTAN</code>)</td><td>Receiving documents, re-order orders</td></tr>
                <tr><td><code>m_department</code>, <code>m_recipient</code></td><td>Who receives goods; a recipient has a default department</td><td>Documents copy the <b>names</b> so they stay readable if master data changes</td></tr>
                <tr><td><code>m_item_unit</code></td><td>Incoming units per product (<code>unit</code> = <code>m_unit.code</code>, <code>factor</code> decimal(18,6) &gt; 0, unique <code>product_id + unit</code>, <code>created_by</code> / <code>updated_by</code> + <code>updated_at</code> = who changed it and when). The stock unit has no row</td><td>product; unit; users</td></tr>
                <tr><td><code>m_item</code></td><td>Item master: stable code, parameters (price, reorder point/qty, lead time), <code>tracks_expiry</code>. The single source of the parameters</td><td>category; batches (<code>items</code>); re-order records</td></tr>
                <tr><td><code>items</code></td><td>Batches: batch code, <code>expiry_date</code>, running balance <code>stock_qty</code> and <code>avg_cost</code> (cache of the ledger); name/unit/parameters are copies of the product for readers</td><td>product; lines; movements</td></tr>
                <tr><td><code>stock_documents</code></td><td>Header: number, type, status, supplier/recipient, reason, created/confirmed/approved/cancelled by and at, cancel request</td><td>creator and approver users; lines; movements</td></tr>
                <tr><td><code>stock_document_lines</code></td><td>Item, quantity, cost; Adjustment keeps <code>system_qty</code> and <code>counted_qty</code>; <code>entered_qty/unit/unit_cost</code> keep what was typed when the unit differs</td><td>document, item</td></tr>
                <tr><td><code>stock_documents.reversal_of_document_id</code></td><td>Set on the auto-created reversing ADJUSTMENT document (status approved) when a processed document is cancelled (<code>StockDocuments::recordReversal()</code>); its lines carry the ledger delta, system and resulting quantity. The ledger rows stay <code>reversal_of</code> on the original document, so the Stock Card is unchanged. The reversing document cannot be cancelled.</td><td>self</td></tr>
                <tr><td><code>stock_movements</code></td><td>The ledger: quantity, cost, balance after, user, <code>reversal_of</code></td><td>item, document, itself (reversal)</td></tr>
                <tr><td><code>stock_counts</code>, <code>stock_count_lines</code></td><td>Stok Opname: header (scope, blind, lock, threshold, cut-off, status, submitted / approved / unlocked / cancelled by and at, <code>adjustment_document_id</code>) and one snapshot line per batch (<code>system_qty</code>, <code>unit_cost</code>, <code>counted_qty</code> null = not counted, <code>recount_qty</code>, <code>counted_by</code>, <code>skipped</code>, <code>reason</code>, <code>note</code>, <code>added_manually</code>; unique <code>stock_count_id + item_id</code>)</td><td>users; items; the ADJ document created on approval</td></tr>
                <tr><td><code>constants</code></td><td>Small shared key / value settings (<code>key</code> unique, <code>value</code> text, <code>group</code>); the Stok Opname schedule uses <code>opname.*</code>; the Company screen uses <code>company.*</code> (<code>name</code>, <code>legal_name</code>, <code>address</code>, <code>phone</code>, <code>email</code>, <code>npwp</code>, <code>logo</code>, <code>kop_style</code>)</td><td>none; read through <code>App\Support\Constants</code> (per-request cache, safe when empty)</td></tr>
                <tr><td><code>reorder_orders</code></td><td>Simple order record per product (<code>supplier_id</code>, <code>qty</code> in the stock unit, <code>ordered_at</code>, <code>ref_no</code>, <code>note</code>; old rows may have no supplier or qty). <code>received_qty</code> and <code>first_received_at</code> are filled by <code>ReorderOrders::reconcile</code>. Closed when confirmed Receiving quantities reach <code>qty</code> (<code>close_reason = 'received'</code>, <code>receiving_id</code> = the completing document), or manually (<code>'manual'</code>)</td><td>product, supplier, receiving document</td></tr>
                <tr><td><code>sessions</code></td><td>Database sessions (<code>SESSION_DRIVER=database</code>). Deactivating a user deletes the user's rows here, which logs that account out at once</td><td>users</td></tr>
                <tr><td><code>users</code>, <code>user_groups</code> (<code>is_active</code>: groups are deactivated, never deleted), <code>user_group_modules</code></td><td>Accounts, groups, and per-module access (none / RO / RW)</td><td>user belongs to a group</td></tr>
            </tbody>
        </table>

        <h3 id="table-prefix">Table naming convention (prefix)</h3>
        <p>Decided by the owner on 2026-11-01 for Jays, which keeps every module (Stok, GA, HR, Purchase, Kas) in one database. The prefix says what kind of table it is and which module owns it, and sorts tables into groups in any database tool. Names are <b>singular</b>, as in tmmockup. Status (2026-11-30): every prefix below is applied; nothing is pending any more.</p>
        <table class="g-table">
            <thead><tr><th>Prefix</th><th>Holds</th><th>Examples</th></tr></thead>
            <tbody>
                <tr><td><code>m_</code></td><td>All master data, whichever module uses it</td><td><code>m_item</code>, <code>m_supplier</code>, <code>m_unit</code>, <code>m_category</code>, <code>m_location</code>, <code>m_recipient</code>, <code>m_department</code>, <code>m_employee</code>, <code>m_recruit_step</code>, <code>m_vehicle</code>, <code>m_ac_unit</code>, <code>m_cash_account</code></td></tr>
                <tr><td><code>i_</code></td><td>Stok inventory state: batches and the re-order log (not <code>s_</code> as first planned)</td><td><code>i_batch</code> (the old <code>items</code> batch table), <code>i_reorder_order</code>, <code>i_count</code>, <code>i_count_line</code></td></tr>
                <tr><td><code>rm_</code></td><td>Stok transaction documents and the ledger (not <code>s_</code> as first planned; <code>rm</code> = raw material, the owner's term for stock)</td><td><code>rm_stock</code> (the document header, every type), <code>rm_stock_line</code>, <code>rm_stockcard</code> (the ledger)</td></tr>
                <tr><td><code>g_</code></td><td>General Affairs transactions</td><td><code>g_vehicle_doc</code>, <code>g_vehicle_service</code>, <code>g_ac_service_order</code></td></tr>
                <tr><td><code>h_</code></td><td>HR transactions</td><td><code>h_discipline</code>, <code>h_recruit_candidate</code>, <code>h_recruit_event</code>, <code>h_recruit_move</code></td></tr>
                <tr><td><code>p_</code></td><td>Purchase transactions</td><td><code>p_request</code>, <code>p_order</code>, <code>p_receipt</code> (and their <code>_line</code> tables)</td></tr>
                <tr><td><code>k_</code></td><td>Kas transactions</td><td><code>k_cash_in</code>, <code>k_cash_out</code></td></tr>
                <tr><td><code>sys_</code></td><td>System: accounts, groups, settings, menu, logs</td><td><code>sys_user</code>, <code>sys_user_group</code>, <code>sys_user_group_module</code>, <code>sys_constant</code></td></tr>
                <tr><td>none</td><td>Laravel framework tables (names read from framework config) and <code>geo_*</code> (not decided; see below)</td><td><code>cache</code>, <code>cache_locks</code>, <code>jobs</code>, <code>job_batches</code>, <code>failed_jobs</code>, <code>sessions</code>, <code>migrations</code>, <code>password_reset_tokens</code>, <code>geo_country</code> ... <code>geo_village</code></td></tr>
            </tbody>
        </table>
        <p>Map from the original, un-prefixed names this application shipped with before 2026-11-01 (migrations written before a rename keep the old name and run before that rename migration, so a fresh install still works):</p>
        <table class="g-table">
            <thead><tr><th>Old table</th><th>New table</th><th>Rename migration</th></tr></thead>
            <tbody>
                <tr><td><code>suppliers</code>, <code>departments</code>, <code>units</code>, <code>unit_conversions</code>, <code>categories</code>, <code>locations</code>, <code>recipients</code>, <code>products</code>, <code>product_units</code></td><td><code>m_supplier</code>, <code>m_department</code>, <code>m_unit</code>, <code>m_unit_conversion</code>, <code>m_category</code>, <code>m_location</code>, <code>m_recipient</code>, <code>m_item</code>, <code>m_item_unit</code></td><td><code>rename_master_tables_with_m_prefix</code> (2026-11-17)</td></tr>
                <tr><td><code>items</code> (batches), <code>reorder_orders</code>, <code>stock_counts</code>, <code>stock_count_lines</code></td><td><code>i_batch</code>, <code>i_reorder_order</code>, <code>i_count</code>, <code>i_count_line</code></td><td><code>rename_stock_tables_with_rm_prefix</code> and its sibling (2026-11-17)</td></tr>
                <tr><td><code>stock_documents</code>, <code>stock_document_lines</code>, <code>stock_movements</code></td><td><code>rm_stock</code>, <code>rm_stock_line</code>, <code>rm_stockcard</code></td><td><code>rename_stock_tables_with_rm_prefix</code> (2026-11-17)</td></tr>
                <tr><td>batch / item column named <code>item_id</code> on <code>rm_stock_line</code>, <code>rm_stockcard</code>, <code>i_count_line</code></td><td><code>batch_id</code> (column rename only; <code>product_id</code> is unchanged, it still points to <code>m_item</code>)</td><td><code>rename_item_id_to_batch_id</code> (2026-11-17)</td></tr>
                <tr><td><code>users</code>, <code>user_groups</code>, <code>user_group_modules</code>, <code>constants</code></td><td><code>sys_user</code>, <code>sys_user_group</code>, <code>sys_user_group_module</code>, <code>sys_constant</code></td><td><code>rename_system_tables_with_sys_prefix</code> (2026-11-17)</td></tr>
                <tr><td><code>geo_country</code>, <code>geo_region</code>, <code>geo_city</code>, <code>geo_district</code>, <code>geo_village</code></td><td>Not decided: kept as they are for now (<code>sstock:import-geo</code> copies them from <code>tm2mst</code> under the same names)</td><td>&mdash;</td></tr>
            </tbody>
        </table>
        <p class="g-note">Prose elsewhere in this guide (sections 1 to 12, written before the 2026-11-17 renames) still names the old tables (<code>stock_documents</code>, <code>items</code> for batches, and so on) and the old column name (<code>item_id</code> for the batch foreign key); the table and column facts above are the current ones. The model classes themselves did not rename: <code>StockDocument</code> (table <code>rm_stock</code>), <code>StockDocumentLine</code> (<code>rm_stock_line</code>), <code>StockMovement</code> (<code>rm_stockcard</code>), <code>Item</code> (<code>i_batch</code>, one batch), <code>Product</code> (<code>m_item</code>, the stock item master) &mdash; see <a href="#open">Open items</a> for the Item / Product wording question this leaves open.</p>
        <ul class="g-list">
            <li><b>Every model declares <code>protected $table</code></b>: Laravel's plural guess no longer matches. A table written as a string (<code>exists:</code> / <code>unique:</code> / <code>Rule::exists</code>, <code>DB::table</code>, <code>join</code>, <code>Schema::hasTable</code>, raw SQL, <code>'table.column'</code>) is not found by searching class names, so search for the old name as a literal string.</li>
            <li><b>Foreign keys always name the table</b>: <code>constrained('m_supplier')</code>; without an argument Laravel guesses <code>suppliers</code> from the column name. A pivot table needs its name written out.</li>
            <li><b>Renaming keeps old constraint and index names</b> (for example <code>items_product_id_foreign</code>, <code>items_product_id_expiry_date_unique</code>), because Laravel names them from the table at creation time. In a later migration drop an index or foreign key by its explicit name, not by column list. Foreign keys that point to a renamed table follow it automatically (SQLite checked; InnoDB documented). A rename migration never drops a table: it skips a table that already has the new name (safe to re-run), stops with a message when the database has a view, and uses one atomic <code>RENAME TABLE</code> on MySQL (<code>Schema::rename</code> one by one on SQLite in the tests).</li>
            <li><b>Views are not updated by <code>RENAME TABLE</code></b>. On 2026-11-01 there were no views, routines, triggers or events in the <code>jays</code> database on the laptop or on Biznet (read from <code>information_schema</code>). Check again before any rename, and create any later view only after the rename.</li>
            <li><b>Applying a rename on Biznet</b>: back up with <code>mysqldump</code> to the laptop, then <code>php artisan down</code>, pull, <code>migrate --force</code>, <code>optimize</code>, <code>up</code>. Rows are not touched, and <code>sessions</code> keeps its name, so testers stay signed in. Never run <code>migrate:fresh</code> or <code>composer sample</code> there.</li>
            <li>Stoku, the separate upstream inventory project this application was forked from, is frozen and keeps its old, un-prefixed names. Jays can no longer take code from Stoku with <code>git pull</code> after the rename: this application (AdminKU) has diverged for good.</li>
            <li><b>New document-type tables created after 2026-11-17</b> (HR, Purchase, Kas) are prefixed from the start (<code>h_</code>, <code>p_</code>, <code>k_</code>), so they need no rename migration of their own.</li>
        </ul>

        <h3>Migrations</h3>
        <p>Migrations are the source of truth for the schema. In order:</p>
        <table class="g-table">
            <thead><tr><th>Migration</th><th>What it does</th></tr></thead>
            <tbody>
                <tr><td><code>0001_01_01_00000x</code></td><td>Laravel base: <code>users</code>, password reset tokens, sessions, cache, jobs</td></tr>
                <tr><td><code>create_stock_tables</code></td><td><code>categories</code>, <code>items</code>, <code>suppliers</code>, <code>stock_documents</code>, <code>stock_document_lines</code>, <code>stock_movements</code></td></tr>
                <tr><td><code>create_geo_tables_and_supplier_contact</code></td><td><code>geo_country, geo_region, geo_city, geo_district, geo_village</code> (filled by <code>sstock:import-geo</code>) and supplier contact and region columns</td></tr>
                <tr><td><code>create_units_table</code>, <code>create_unit_conversions_table</code>, <code>add_kind_to_units</code>, <code>localize_unit_names</code></td><td>Unit master, conversion factors, colour kind, Indonesian unit names</td></tr>
                <tr><td><code>add_entered_unit_to_stock_document_lines</code></td><td><code>entered_qty / entered_unit / entered_unit_cost</code>: what was typed when the unit differs from the stock unit</td></tr>
                <tr><td><code>create_reorder_orders_table</code></td><td>Re-order log</td></tr>
                <tr><td><code>create_departments_and_recipients</code></td><td><code>departments</code>, <code>recipients</code> and the id and name columns on <code>stock_documents</code></td></tr>
                <tr><td><code>create_user_groups</code>, <code>add_access_to_user_group_modules</code></td><td>Groups and per-module access (none / RO / RW)</td></tr>
                <tr><td><code>add_confirm_and_cancel_to_stock_documents</code>, <code>add_cancel_request_to_stock_documents</code></td><td><code>confirmed_by/at</code>, <code>cancelled_by/at</code>, <code>cancel_reason</code>, <code>stock_movements.reversal_of</code>, <code>cancel_requested_by/at</code></td></tr>
                <tr><td><code>add_expiry_locations_and_category_active</code></td><td><code>locations</code> master, item expiry date, <code>categories.is_active</code></td></tr>
                <tr><td><code>create_products_and_batches</code></td><td>Two-level item model: <code>products</code>, <code>items.product_id</code> (batches), <code>reorder_orders.product_id</code>; old items are merged into products</td></tr>
                <tr><td><code>add_is_active_to_user_groups</code></td><td>Activate / Deactivate for groups</td></tr>
                <tr><td><code>add_reversal_of_document_to_stock_documents</code></td><td><code>stock_documents.reversal_of_document_id</code>: the automatic reversing ADJUSTMENT points at the cancelled document</td></tr>
                <tr><td><code>add_audit_to_unit_conversions</code></td><td><code>created_by</code> / <code>updated_by</code> on the global conversions</td></tr>
                <tr><td><code>add_integrity_constraints_to_items</code></td><td>CHECK <code>stock_qty &gt;= 0</code> (MySQL / MariaDB), unique <code>(product_id, expiry_date)</code>, index on <code>expiry_date</code>; stops without changing data when existing rows violate them</td></tr>
                <tr><td><code>give_expiry_report_to_groups</code>, <code>give_sample_data_to_administrator_groups</code></td><td>Data migrations: grant the new modules to existing administrator groups</td></tr>
                <tr><td><code>add_system_supplier_kontan</code></td><td><code>suppliers.is_system</code> and the system supplier row <code>KONTAN</code> (idempotent)</td></tr>
                <tr><td><code>add_supplier_qty_note_to_reorder_orders</code></td><td><code>reorder_orders.supplier_id</code> (FK, null on delete), <code>qty</code> decimal(15,3) null, <code>note</code> null: simple order record (nullable columns only; old rows unchanged)</td></tr>
                <tr><td><code>add_receipt_progress_to_reorder_orders</code></td><td><code>reorder_orders.received_qty</code> decimal(15,3) default 0, <code>first_received_at</code> null, <code>close_reason</code> null ('received' | 'manual'); closed rows are backfilled (columns only, nothing deleted)</td></tr>
                <tr><td><code>create_product_units_table</code></td><td><code>product_units</code>: incoming units per product with factor to the stock unit (adds a table only; no existing data is changed; CHECK factor &gt; 0 on MySQL / MariaDB)</td></tr>
                <tr><td><code>give_order_history_to_groups</code></td><td>Data migration only: groups with RCV rights get module <code>ORD</code> with the same level (idempotent)</td></tr>
                <tr><td><code>give_company_to_groups</code></td><td>Data migration only: grants module <code>CMP</code> (RW) to the groups that already write ADJ, OPN, UOM, CAT or LOC (idempotent)</td></tr>
                <tr><td><code>create_stock_counts_tables</code>, <code>give_stock_count_to_groups</code></td><td>Stok Opname tables (new tables only; no existing data changed); data migration that grants module <code>SO</code> (RW) to every group that has RW on ADJ or RCV (idempotent)</td></tr>
                <tr><td><code>add_lock_audit_to_stock_counts</code>, <code>add_entry_audit_to_stock_count_lines</code>, <code>create_constants_table</code></td><td>New columns / table only: <code>locked_by/at</code>; <code>entered_by</code>, <code>entered_at</code>, <code>rev</code> on lines; <code>constants</code> (skipped if it already exists)</td></tr>
                <tr><td><code>rename_master_tables_with_m_prefix</code></td><td>Renames the nine master tables only (<code>products</code> to <code>m_item</code>, <code>product_units</code> to <code>m_item_unit</code>, <code>categories</code> to <code>m_category</code>, <code>units</code> to <code>m_unit</code>, <code>unit_conversions</code> to <code>m_unit_conversion</code>, <code>locations</code> to <code>m_location</code>, <code>recipients</code> to <code>m_recipient</code>, <code>suppliers</code> to <code>m_supplier</code>, <code>departments</code> to <code>m_department</code>). Columns, data and foreign keys are unchanged. The migrations above keep the old names because they run before this one. <code>down()</code> reverses it</td></tr>
            </tbody>
        </table>

        <h2 id="access">7. Access and security</h2>
        @include('help.diagram', ['name' => 'tg-access-flow', 'caption' => 'Login and per-request authorization.'])
        <ol class="g-steps">
            <li>A request must be authenticated (<code>auth</code>), otherwise it goes to Login.</li>
            <li><code>EnsureActive</code> logs out a user who was deactivated during a live session (redirect to Login, or 403 JSON); deactivating also clears <code>remember_token</code> and the user's rows in <code>sessions</code>. The message is "Akun Anda dinonaktifkan.". The last active Administrator cannot be deactivated, and nobody can deactivate or change the role of their own account (<code>UserController</code>).</li>
            <li><code>EnsureModuleAccess</code> maps the route to a module code with <code>Modules::forRoute</code>. Access needs <b>both</b> a sufficient role and the module granted by the user's group.</li>
            <li>A module with RO access refuses every non-GET request.</li>
            <li>The controller or form request then checks per-document-type rights.</li>
        </ol>
        <ul class="g-list">
            <li>Roles: <code>data_entry</code> &lt; <code>admin_data</code> (opens like a Supervisor, <code>canApprove()</code> false) &lt; <code>supervisor</code> &lt; <code>administrator</code>. Administrators always have full access. A user without a group gets the role default (<code>Modules::defaults</code>).</li>
            <li><code>App\Support\Modules</code> is the single module list; it feeds the menu, the group settings, the module list screen and the route guard. A new module is one new row there.</li>
            <li>Extra middleware: <code>supervisor</code> (edit items, units, categories, locations, adjustment report) and <code>administrator</code> (users, groups, modules, this guide).</li>
            <li>Print: <code>docs.print</code> (<code>StockDocumentPrint</code> on DevLib <code>x-dl::print-doc</code>) is available in every status and prints the MODE (stamp + watermark for non-valid modes). Approve and Reject are Supervisor only. Password reset uses an e-mail link (throttled to 5 per minute, valid 60 minutes, single use).</li>
            <li>Incoming-unit factors (<code>ProductUnits::save</code>, used by the product form route <code>products.units.save</code> and the Receiving dialog <code>docs.conversion</code>): anyone with write access may create a factor and may correct one that no confirmed Receiving has used yet (a cancelled one still counts as used; drafts do not). A factor already used by a confirmed document changes only for the holder of write access to <code>UOM</code> (Supervisor); everyone else gets 403 "Faktor ini sudah dipakai di dokumen; hubungi Supervisor untuk mengubahnya.". Old documents never change: lines keep the converted <code>qty</code> and the typed <code>entered_*</code>; a locked document shows its stored quantity. The stock unit of a product cannot be changed while it has incoming units. The global <code>m_unit_conversion</code> remain a fallback only; the Unit master (UOM, Supervisor) still edits them.</li>
            <li>Login: username or e-mail plus password, <code>is_active</code> required, at most 5 failed attempts per login + IP (<code>RateLimiter</code>, then a wait message). Session: there is no "remember me" (the checkbox is removed and the input is ignored; no <code>remember_token</code> or recaller cookie). <code>.env.example</code> sets <code>SESSION_LIFETIME=30</code> minutes and <code>SESSION_DRIVER=database</code> (<code>config/session.php</code> falls back to 120 minutes when the variable is missing). <code>x-dl::idle-logout</code> is mounted once in <code>layout.blade.php</code> for logged-in pages only, with <code>minutes</code> = <code>config('session.lifetime')</code>: a warning with a countdown and a <b>Stay signed in</b> button shows 2 minutes before the end, then it POSTs <code>logout</code> and goes to Login. Activity pings <code>GET session/ping</code> (route <code>session.ping</code>, 204, behind <code>auth</code> and <code>active</code>, no module code), which extends the server session.</li>
            <li>Quick add: dropdowns of master data carry a plus button (DevLib <code>x-dl::field.select</code> prop <code>add</code>) built by <code>App\Support\QuickAdd</code>, which returns nothing unless the user can <b>write</b> the master's module (<code>User::canWrite</code>: role and group RW, the same rule as the route guard). It posts JSON to the master's normal <code>store</code> route (Supplier, Department, Recipient: any writer; Category, Location, Unit: Supervisor) and expects <code>201 {id, name}</code>, or 422 with Laravel errors. Supplier codes are generated (<code>Supplier::nextCode</code>, <code>SUP-001</code>...) and region is not asked; names must be unique, except Recipient. Locked documents and the report filters have no plus button.</li>
            <li>System supplier: <code>Kontan</code> (<code>m_supplier.is_system</code>, created by migration <code>add_system_supplier_kontan</code> and <code>SupplierSeeder</code>) cannot be deactivated, renamed or deleted (<code>SupplierRequest</code>, <code>SupplierController::toggle</code>, model <code>deleting</code> guard) and sorts first in every supplier dropdown (<code>Supplier::forSelect</code>).</li>
            <li>Messages worth knowing: <code>EnsureModuleAccess</code> answers 403 "Anda tidak punya akses ke modul ini." (no access) or "Akses Anda ke modul ini hanya-baca (RO)." (non-GET on an RO module).</li>
            <li>Known gaps: no segregation of duties (the creator of an Adjustment may approve it; the Stok Opname approval screen only warns when the approver entered more than half of the lines); the Save button still shows on RO modules (the server rejects it).</li>
        </ul>

        <h3>Module list (<code>App\Support\Modules</code>)</h3>
        <table class="g-table">
            <thead><tr><th>Code</th><th>Menu</th><th>Group</th><th>Minimum role</th><th>Guarded route prefix</th></tr></thead>
            <tbody>
                <tr><td>ITM</td><td>Barang</td><td>Data Master</td><td>Data Entry</td><td><code>products.</code> (add: any role; edit and toggle: Supervisor)</td></tr>
                <tr><td>SUP, DEP, RCP</td><td>Suppliers, Departemen, Penerima</td><td>Data Master</td><td>Data Entry</td><td><code>suppliers.</code>, <code>departments.</code>, <code>recipients.</code></td></tr>
                <tr><td>UOM, CAT, LOC</td><td>Satuan, Kategori, Lokasi Penyimpanan</td><td>Data Master</td><td>Supervisor</td><td><code>units.</code>, <code>categories.</code>, <code>locations.</code></td></tr>
                <tr><td>CMP</td><td>Perusahaan</td><td>Data Master</td><td>Supervisor</td><td><code>company.edit</code>, <code>company.update</code> (the route <code>company.logo</code> is public and has no module)</td></tr>
                <tr><td>OPN, ADJ</td><td>Saldo Awal, Penyesuaian Stok</td><td>Transaksi</td><td>Supervisor</td><td><code>docs.*</code> with <code>{type}</code> = opening / adjustment</td></tr>
                <tr><td>ORD</td><td>Riwayat Pemesanan</td><td>Transaksi</td><td>Data Entry</td><td><code>reorder-orders.</code> (all of them: GET list and detail are readable with RO; the POST routes <code>store</code> = Catat Pemesanan and <code>close</code> = Tutup Pesanan need RW)</td></tr>
                <tr><td>SO</td><td>Stok Opname</td><td>Transaksi</td><td>Data Entry</td><td><code>stock-counts.</code> (start, review, approve, return, cancel, unlock, minutes: Supervisor middleware on the route)</td></tr>
                <tr><td>RCV, ISS</td><td>Penerimaan Barang, Pengeluaran Barang</td><td>Transaksi</td><td>Data Entry</td><td><code>docs.*</code> with <code>{type}</code> = receiving / issuance</td></tr>
                <tr><td>R-CARD, R-SUM, R-IN, R-OUT, R-EXP</td><td>Kartu Stok, Ringkasan Stok, Laporan Penerimaan / Pengeluaran / Kedaluwarsa</td><td>Laporan</td><td>Data Entry</td><td><code>reports.*</code></td></tr>
                <tr><td>R-ADJ</td><td>Laporan Opname &amp; Penyesuaian</td><td>Laporan</td><td>Supervisor</td><td><code>reports.adjustment</code></td></tr>
                <tr><td>GVM, GVD, GVS, GVO, GVK, GVT, GAS, GAV, GAK, GAA, GAR, GAE</td><td>Perawatan Kendaraan and Perawatan AC screens</td><td>GA</td><td>Data Entry (rights only through a group, never by role default)</td><td>see <a href="#ga-modules">13. GA</a></td></tr>
                <tr><td>GAP</td><td>Pengaturan GA</td><td>GA</td><td>Supervisor (group right needed)</td><td><code>ga-settings.</code></td></tr>
                <tr><td>USR, GRP, SMP, MOD</td><td>Pengguna, Grup, Data Contoh, Daftar Modul</td><td>Keamanan</td><td>Administrator</td><td><code>users.</code>, <code>groups.</code>, <code>sample.</code>, <code>modules.</code></td></tr>
            </tbody>
        </table>
        <p class="g-note">Routes that belong to no module (home, logout, session ping, Help) are open to every logged-in user. The User's Guide lists only the modules the reader may open (<code>User::canAccess</code>); the Technical Guide route sits in the <code>administrator</code> middleware group and its tab link is rendered only for Administrators.</p>

        <h2 id="screens">8. Dashboard and reports</h2>
        <ul class="g-list">
            <li><b>Home screen</b> (<code>HomeController</code>): clickable KPIs (<code>data-open-tab</code> opens the module as a shell tab), the Stok Opname reminder (<code>partials/count-reminder</code>, only for users with module <code>SO</code>), Reorder Point alert with order status, near-expiry alert (batches with stock and <code>expiry_date</code> within <code>Item::EXPIRY_WARN_DAYS</code> = 30 days), and, for Supervisors, the approval queue (<code>approval</code> and <code>cancel_pending</code> documents) and the stock-value chart.</li>
            <li><b>Re-order log:</b> "Record order" creates a <code>reorder_orders</code> row; confirmed Receiving documents add to <code>received_qty</code> and close it once <code>qty</code> is reached. Open orders carry an information note (<code>ReorderOrder::note()</code>: order date, reference, who recorded it, and "received since ordering" = incoming movements of confirmed, not cancelled Receiving documents for all batches of the product since <code>ordered_at</code>) on the Home alert, the Stock Summary report (column <i>Info Pemesanan</i>) and the product form. Lead time (<code>App\Services\LeadTimes</code> on the DevLib engine <code>Devlib\Engine\LeadTime</code>): the orders of a product become records <code>{start: ordered_at, end: closed_at, key: id}</code> (<code>forMany()</code> does one query for many products; open orders have no end). <code>end</code> is now <code>first_received_at</code> (the FIRST receiving allocated to the order, also while it is only partially received); orders without one, including orders closed manually without any receiving, are not counted, and a cancelled Receiving is never counted because <code>reconcile</code> clears it. Options: last 5 orders, minimum 3, fallback = <code>products.lead_time_days</code> (0 = unset), late tolerance 1 day. Orders that have received nothing yet get the engine sentences (<code>describeExpected</code>, and <code>describeStatus</code> when late) appended to the note; once the first receiving has arrived they are no longer flagged late; with no figure nothing is added. The product form shows read-only hints (automatic lead time when the source is data; a suggestion box <i>Saran pesan ulang</i> from <code>App\Services\ReorderAdvice::advise</code>: with u = 90-day average daily usage of confirmed, not cancelled Issuance movements, reorder point = ceil(u x (lead time + safety days)) and reorder qty = ceil(u x cover days), at least 1; defaults 7 safety days and 30 cover days (<code>ReorderAdvice::SAFETY_DAYS</code> / <code>COVER_DAYS</code>); the two day values can be tried on screen (client-side recalculation, not stored) and the <i>Pakai saran</i> button, Supervisor only, only fills the two fields, saved by the normal Save; the box needs an effective lead time and u &gt; 0, otherwise a one-line note) and the Stock Summary has a column <i>Lead Time Otomatis</i>; the manual lead time and the reorder point are never written. The Record Order popup (<code>ReorderOrderController</code>) stores <code>supplier_id</code> (active supplier, FK null on delete), <code>qty</code> (&gt; 0, in the stock unit; nullable because old rows have none), <code>ordered_at</code>, optional <code>ref_no</code> (stored as an empty string when blank) and <code>note</code>; the note text and the report columns skip the supplier / quantity parts for old rows. This is a simple record, not a PO. The alert calculation is unchanged. <b>Closing rule</b> (<code>App\Services\ReorderOrders::reconcile</code>, called by <code>StockDocuments</code> on confirm and on every cancel of a Receiving): per product it takes the confirmed, not cancelled Receiving movements (stock unit, by time) and the product's orders by <code>ordered_at, id</code> and allocates FIFO, only receipts on or after the order date; each order with <code>qty</code> takes up to its <code>qty</code> and gets <code>received_qty</code>, <code>first_received_at</code> and, when complete, <code>closed_at</code> / <code>close_reason = 'received'</code> / <code>receiving_id</code> (the completing document), else all three are cleared; excess flows to the next order. Orders without <code>qty</code> (old rows) are closed by the first receiving on or after the order date, as before. Orders with <code>close_reason = 'manual'</code> (button <i>Tutup Pesanan</i> on the Home alert, <code>reorder-orders.close</code>, open orders only, optional reason appended to <code>note</code>) are never allocated or reopened. The result depends only on the receiving data, so reconcile is idempotent. Orders closed before this rule were backfilled by the migration (<code>first_received_at = closed_at</code>, <code>close_reason = 'received'</code>, <code>received_qty = qty</code>) but are recomputed like any other on the next reconcile of their product.</li>
            <li><b>Order history</b> (module <code>ORD</code>, <code>ReorderOrderController@index</code> / <code>show</code>, views <code>reorder-orders/*</code>): a plain grid of every order with every status (<code>ReorderOrder::statusLabel()</code>: Dipesan, Diterima sebagian, Selesai, Ditutup manual), a GET date-range filter on <code>ordered_at</code>, status boxes and a supplier filter in the grid, no money. Legacy orders without <code>qty</code> show "-". The detail page lists the receipts allocated to the order through <code>ReorderOrders::allocations()</code>, a read-only copy of the FIFO of <code>reconcile</code> (<code>ReorderOrderHistoryTest</code> checks that its sum equals <code>received_qty</code>; manual orders are not itemised). The Close Order button posts to the existing <code>reorder-orders.close</code> with <code>return=history</code> and is shown only for open orders and users who can write <code>ORD</code>. Migration <code>give_order_history_to_groups</code> copies the group right on <code>RCV</code> (RW to RW, RO to RO).</li>
            <li><b>Reports</b> (<code>ReportController</code>). Period filters (<code>from</code>, <code>to</code>) default to the first of the current month until today (<code>period()</code>). Stock Card and Stock Summary are per batch (<code>items</code>), not per product; the Stock Card needs an item and reads <code>stock_movements</code>. The opening balance of a report is the balance after the last movement before <code>from</code>, so an <code>OPENING</code> movement dated before the period is folded into it and one dated inside the period shows as an <code>AWAL</code> row. Stock Summary: <i>in</i> = Receiving + Opening movements; the reorder status is per product from the closing balance of non-expired batches. Receiving and Issuance list lines of <code>confirmed</code> and <code>cancel_pending</code> documents only (draft and cancelled are excluded); the Opname &amp; Adjustment report (Supervisor) lists Adjustment documents of every status, with a status filter, including the automatic reversal and Stok Opname adjustments. Expiry has no period (<code>days</code>, default 30, and category). Prices and values need <code>isSupervisor()</code>.</li>
        </ul>

        @include('help.diagram', ['name' => 'tg-reorder-flow', 'caption' => 'Re-order flow: alert, Record Order, and closing by Receiving.'])

        <h3>Greeting, weather and network line</h3>
        <ul>
            <li><b>Weather follows the device:</b> <code>x-dl::weather</code> uses <code>navigator.geolocation</code> by default (HTTPS or localhost only; <code>maximumAge</code> 6 hours, timeout 8 seconds, no high accuracy), the city name comes from BigDataCloud <code>reverse-geocode-client</code>, the weather from Open-Meteo, both called by the browser with coordinates rounded to 2 decimals; the answer is cached in <code>localStorage</code> (<code>dl-wx</code>) for 1 hour and a refusal is remembered for 7 days (<code>dl-wx-no</code>). Fallback chain: cache, device location, the company fallback place (<code>Company::weather()</code>), nothing (card hidden). On plain <code>http://</code> (LAN IP, <code>.test</code>, an Ubuntu server without a certificate) the device location never works, so the Company fallback is used until HTTPS is installed. The only outgoing calls of the app are these two services, made by the browser, never by the server.</li>
            <li><b>Fallback place:</b> the Company screen has Kota / Lintang / Bujur (<code>company.weather_city</code>, <code>weather_lat</code>, <code>weather_lon</code> in <code>constants</code>); <code>CompanyRequest</code> validates latitude -90..90 and longitude -180..180 (numeric, optional, both or none, a decimal comma is accepted); <code>Company::weather()</code> returns <code>['city','lat','lon']</code> or <code>null</code>.</li>
            <li><b>Network line:</b> <code>AppServiceProvider::boot</code> binds <code>devlib.net</code> to <code>Devlib\NetworkInfo::of(request())</code>; <code>x-dl::layer-menu</code> (desktop user row) and <code>x-dl::phone-shell</code> (Menu list) then draw <code>x-dl::net-line</code> instead of the old host text. The browser pings the DevLib route <code>GET /devlib/ping</code> (204, no session, no cookie, throttled) every 10 seconds while the tab is visible; two failures in a row = &quot;Server tidak terjangkau&quot; (server IP blinks), the browser <code>offline</code> event = &quot;Perangkat offline&quot; (client IP blinks). <code>session.ping</code> is not used on purpose: it extends the 30 minute session and a login redirect would look like a failure. A browser cannot tell a broken LAN from a broken internet; the line only says whether the server answered.</li>
            <li><b>Version label:</b> <code>AppServiceProvider::boot</code> binds <code>devlib.version</code> to <code>Devlib\AppVersion::of(base_path())</code>: <code>ver 0.&lt;git commit count&gt;</code> plus one stage letter from <code>APP_STAGE</code> (<code>config('devlib.stage')</code>, default <code>alpha</code>; <code>A</code> alpha, <code>B</code> beta, <code>RC</code>; empty = release = latest tag such as <code>ver 1.0.0</code>, no letter). The count is cached under <code>storage/framework/cache</code> keyed by the mtime of <code>.git/logs/HEAD</code>, so git runs once per new commit; without git (a server that only receives files) the file <code>VERSION</code> (<code>0.NNN</code>) is used, then <code>0.0</code>. It is drawn by <code>x-dl::env-badge part=&quot;version&quot;</code> in the logo title of the left menu and under the login form (<code>login</code>, <code>login-hp</code>). The server user running PHP must be allowed to read the repository (git <code>safe.directory</code>), otherwise the fallback is used.</li>
            <li><b>Tests:</b> <code>HomeGreetingTest</code> (greeting, weather props, fallback, phone chip, network binding, ping route) and <code>CompanyTest</code> (fallback place fields).</li>
        </ul>

        <h3>Company header of every printout</h3>
        <p>One screen (module <code>CMP</code>, Data Master &gt; Perusahaan, Supervisor only; <code>CompanyController</code>, <code>CompanyRequest</code>, view <code>company/form</code>) feeds one class, <code>App\Support\Company</code>, which is the only source of the print header. The application keeps the name ADMINKU (login, menu, titles; from <code>config('app.name')</code>, env <code>APP_NAME</code>); only printouts use the company name.</p>
        <ul class="g-list">
            <li><b>Where the data lives:</b> <code>constants</code> keys <code>company.name</code>, <code>legal_name</code>, <code>address</code>, <code>phone</code>, <code>email</code>, <code>npwp</code>, <code>logo</code> (the file name, always <code>logo.png</code>) and <code>kop_style</code> (<code>logo_name_address</code> | <code>logo_name</code> | <code>name</code>). Nothing new in the schema. Defaults before the first save: name = <code>config('devlib.company.name')</code>, style = <code>logo_name</code> when a logo exists, else <code>name</code>.</li>
            <li><b>Wiring:</b> <code>AppServiceProvider::boot</code> registers <code>Document::companyUsing(fn () =&gt; Company::kop())</code>, so <code>x-dl::print-doc</code> (stock documents) and <code>x-dl::print-sheet</code> (count sheet, minutes) use it through <code>Kop::of()</code> of DevLib. The six reports pass <code>Company::report()</code> to <code>x-dl::report</code> (it reads <code>address</code> and <code>contact</code> instead of <code>lines</code>). Nothing builds its own header. <code>kop()</code> gives at most 3 lines (address lines, then <code>Telp. ... · e-mail</code>); the tax id prints only on stock documents.</li>
            <li><b>Style rules</b> (in DevLib): a logo style without a logo falls back to the name-only look but the stored choice stays; a legal name equal to the name (ignoring case and spaces) is not printed twice. <code>report()</code> keeps the old tagline only while the company has never been saved.</li>
            <li><b>Logo upload:</b> PNG, JPEG or WebP only, checked by content (<code>mimetypes</code> and <code>getimagesizefromstring</code>; SVG, GIF and fake extensions are refused), max 1 MB, 32 to 4000 px per side. GD resizes it to at most 600 px wide and stores a standard PNG in <code>storage/app/company/logo.png</code>; the user never chooses a file name. <code>?v=</code> in the URL is the file time, so browsers refresh.</li>
            <li><b>Serving:</b> route <code>company.logo</code> sits outside <code>auth</code> because the public link of <code>x-dl::doc-share</code> prints the same header. It answers with <code>Content-Type: image/png</code>, <code>Cache-Control: public, max-age=86400</code> and <code>X-Content-Type-Options: nosniff</code> through <code>response()-&gt;file()</code>, so <code>storage:link</code> is not needed. URLs are relative (<code>route(..., absolute: false)</code>).</li>
            <li><b>Access:</b> migration <code>give_company_to_groups</code> grants <code>CMP</code> (RW) to groups that already write ADJ, OPN, UOM, CAT or LOC; users without a group get it from the role default. Test: <code>CompanyTest</code>.</li>
        </ul>

        <h2 id="conventions">9. Development conventions</h2>
        <table class="g-table">
            <thead><tr><th>Topic</th><th>Rule</th></tr></thead>
            <tbody>
                <tr><td>Language</td><td>Everything shown to users is Indonesian. Add new labels to <code>Indonesian</code>; validation messages live in <code>lang/id/validation.php</code>; currency stays <code>IDR</code></td></tr>
                <tr><td>Grids</td><td>Transaction grids are plain (bold document number, one colored Status badge). Master grids use a colored box per row (<code>box</code>, <code>hue</code> from <code>Palette</code>)</td></tr>
                <tr><td>Forms</td><td>The Back button always goes to the module grid (<code>:back</code>); a form must not submit twice</td></tr>
                <tr><td>Seeders</td><td>Sample data lives only in <code>database/seeders</code>; sample transactions go through <code>StockDocuments</code> then <code>StockLedger</code>; a schema change updates the seeders in the same change</td></tr>
                <tr><td>DevLib</td><td>Screens use the library components (<code>x-dl::*</code>) instead of hand-built markup; AdminKU only supplies data and its own CSS</td></tr>
                <tr><td>Git</td><td>Commit and push every change; never force-push; never commit <code>.env</code></td></tr>
                <tr><td>Help</td><td>User's Guide (Indonesian) is split per section in <code>resources/views/help/ug/*.blade.php</code> and wired by <code>help/user-guide.blade.php</code>; each module has an anchor (<code>m-</code>, <code>t-</code>, <code>r-</code>, <code>sec-</code>) and every section follows the same pattern (purpose, who, steps, notes, common problems). Diagrams are inline SVG in <code>resources/help/diagrams</code>, included with <code>help.diagram</code>. <code>HelpTest</code> compares the module list (<code>Modules::all()</code>) with those anchors, so a new module needs a help section. Change the UG and this guide together with the behaviour</td></tr>
            </tbody>
        </table>
        <h3>Adding a new document type</h3>
        <ol class="g-steps">
            <li>Add a case to <code>StockDocumentType</code> (slug, prefix, label, quantity sign).</li>
            <li>Register it in the <code>whereIn</code> of the <code>docs/{type}</code> route and in <code>Modules</code> (<code>all</code> and <code>forRoute</code>).</li>
            <li>Add rules to <code>StockDocumentRequest</code> and <code>StockDocuments</code>.</li>
            <li>Update the dictionary and the seeders, and add lifecycle tests in <code>DocumentLifecycleTest</code>.</li>
        </ol>

        <h2 id="operations">10. Operations</h2>
        <pre class="g-code">composer setup     # install, .env, key, create DB and MySQL user, migrate, seed, build
composer sample    # migrate:fresh --seed --force (ERASES the database including geo_*); then run sstock:import-geo
php artisan test   # in-memory SQLite
vendor/bin/pint --dirty
npm run build      # required after editing resources/css/app.css</pre>
        <ul class="g-list">
            <li>Test accounts (development only; change before production): <code>admin</code>, <code>spv</code>, <code>adm</code> (see <code>UserSeeder</code>).</li>
            <li>Tests: see <a href="#testing">Testing</a> below.</li>
            <li>Production: set SMTP (<a href="#mail">Mail</a>) and replace the test passwords. Migrations are the source of truth.</li>
        </ul>

        <h3 id="install">Install steps</h3>
        <p>The README (Indonesian, written for new testers) has the full walk-through. Short version:</p>
        <ol class="g-steps">
            <li>Install PHP 8.3 or newer (extension <code>pdo_mysql</code>), Composer 2, MySQL 8, Node 20.19+ or 22.12+, and Git.</li>
            <li>Clone <code>devlib</code> and <code>sstock</code> side by side: Composer takes <code>jo/devlib</code> from the path repository <code>../devlib</code>. Both repositories are private.</li>
            <li><code>composer install</code>, copy <code>.env.example</code> to <code>.env</code>, then <code>php artisan key:generate</code>.</li>
            <li>Set <code>DB_*</code> and the MySQL admin account (<code>DB_ADMIN_USERNAME</code>, <code>DB_ADMIN_PASSWORD</code>, used only by the installer). On a test machine also set <code>APP_ENV=local</code>, <code>APP_DEBUG=true</code> and <code>SSTOCK_SAMPLE_REFRESH=true</code>.</li>
            <li><code>php artisan sstock:install</code> creates the database and the MySQL user, migrates, tries to copy the region tables (a failure is not fatal), and seeds once when there are no users. It is safe to run again.</li>
            <li><code>npm install --ignore-scripts</code>, then <code>npm run build</code> (<code>public/build</code> is not in Git).</li>
            <li>Serve with Herd (<code>sstock.test</code>) or <code>php artisan serve</code>. <code>composer setup</code> runs steps 3 (key and <code>.env</code> copy), 5 and 6 without prompts.</li>
        </ol>

        <h3>Artisan commands</h3>
        <table class="g-table">
            <thead><tr><th>Command</th><th>Purpose</th></tr></thead>
            <tbody>
                <tr><td><code>php artisan sstock:install</code></td><td>First deploy: creates the MySQL database and the <code>sstock_usr</code> user (needs a MySQL admin: <code>--admin-user / --admin-password</code>, or <code>DB_ADMIN_USERNAME / DB_ADMIN_PASSWORD</code>, or it asks), migrates, copies region data, seeds once. Safe to re-run (never overwrites data)</td></tr>
                <tr><td><code>php artisan sstock:import-geo</code></td><td>Copies <code>geo_*</code> from a source MySQL database (<code>--source</code>, default env <code>GEO_SOURCE_DB</code> or <code>tm2mst</code>). Skipped if the tables are filled, unless <code>--fresh</code>. Needs a MySQL admin account</td></tr>
                <tr><td><code>php artisan sstock:wallpapers</code></td><td>Converts new images dropped into <code>public/media/bg/sstock</code> to JPG (max width 2560, odd ratios cropped to 16:9, originals moved to <code>storage/app/wallpaper-source</code>). Also runs by itself after a login page response (<code>defer()</code>). Every landscape JPG gets a 9:16 twin <code>&lt;name&gt;-portrait.jpg</code> (width up to 1080, quality 82, cut at the most detailed strip of the photo; made once, delete it to remake it). A portrait photo dropped by hand as <code>&lt;name&gt;-portrait.png</code> (or WebP etc.) is converted the same way and replaces the automatic twin. Phone login pages (<code>login-hp</code>, forgot / reset password) draw one random photo through <code>x-hp-wallpaper</code> (<code>Wallpapers::random()</code>: the portrait twin, or the landscape photo if there is none, or no photo if the folder is empty) under a thin navy veil with the form as a surface card; the desktop login keeps <code>x-dl::frame</code>, which switches to the twin by itself when the window is taller than wide. Phone data screens (shell, Home) show no photo. Test: <code>WallpapersTest</code></td></tr>
                <tr><td><code>php artisan sstock:mail-test you@example.com</code></td><td>Sends one test mail through the active <code>MAIL_*</code> settings; exit code 1 and the transport error when it fails (see <a href="#mail">Mail</a>)</td></tr>
                <tr><td><code>php artisan migrate --force</code></td><td>Apply migrations on a server</td></tr>
                <tr><td><code>composer dev</code></td><td>Local development runner (<code>php artisan dev</code>)</td></tr>
            </tbody>
        </table>

        <h3>Seeders and sample data</h3>
        <ul class="g-list">
            <li><code>DatabaseSeeder</code> runs, in order: <code>UserSeeder</code> (accounts admin, spv, adm), <code>CategorySeeder</code>, <code>SupplierSeeder</code>, <code>ItemSeeder</code> (products, batches, locations; units come from the migration), <code>OrganizationSeeder</code> (departments, recipients), <code>SampleTransactionSeeder</code>.</li>
            <li>Master seeders are idempotent (<code>updateOrCreate</code> keyed by a natural code). <code>SampleTransactionSeeder</code> posts through <code>StockDocuments</code> then <code>StockLedger</code>, never by direct inserts, and is skipped when documents already exist. It covers every important status (draft, confirmed, adjustment waiting and approved, items below the Reorder Point).</li>
            <li><b>Sample Data screen</b> (Keamanan &gt; Data Contoh, <code>SampleDataController</code>, <code>App\Services\SampleData</code>): the Administrator types <code>SEGARKAN</code>; the database is dropped and rebuilt from migrations plus <code>DatabaseSeeder</code>; <code>geo_*</code> is preserved; the user is logged out. The last refresh is logged in <code>storage/app/sample-refresh.json</code>. Disabled in production unless <code>SSTOCK_SAMPLE_REFRESH=true</code>.</li>
            <li><code>composer sample</code> is the CLI twin (<code>migrate:fresh --seed --force</code>) but it also erases <code>geo_*</code>; run <code>sstock:import-geo</code> afterwards.</li>
            <li>A schema change must update the seeders in the same change; <code>SeedersTest</code> runs the seeders twice and checks that stock equals the ledger.</li>
        </ul>

        <h3>Configuration</h3>
        <table class="g-table">
            <thead><tr><th>Key</th><th>Meaning</th></tr></thead>
            <tbody>
                <tr><td><code>APP_ENV</code>, <code>APP_DEBUG</code></td><td><code>.env.example</code> ships <code>production</code> and <code>false</code>. A test machine sets <code>local</code> and <code>true</code> in its own <code>.env</code>; never in production</td></tr>
                <tr><td><code>SESSION_LIFETIME=30</code></td><td>Server session minutes (<code>config/session.php</code> falls back to 120 if the variable is missing); also the idle-logout time in the browser (the warning shows 2 minutes before)</td></tr>
                <tr><td><code>SESSION_DRIVER=database</code></td><td>Sessions live in the <code>sessions</code> table, so deactivating a user can delete the user's sessions</td></tr>
                <tr><td><code>APP_LOCALE=id</code></td><td>Indonesian screens (dictionary active only for <code>id</code>)</td></tr>
                <tr><td><code>DB_*</code>, <code>DB_ADMIN_*</code></td><td>Application MySQL account; admin account used only by the installer and <code>sstock:import-geo</code></td></tr>
                <tr><td><code>MAIL_*</code></td><td><code>MAIL_MAILER=log</code> in development (reset links only appear in <code>storage/logs/laravel.log</code>); production needs SMTP, see <a href="#mail">Mail</a></td></tr>
                <tr><td><code>SSTOCK_SAMPLE_REFRESH</code></td><td>Allow the Sample Data screen. <code>.env.example</code> sets <code>false</code>; the code default (<code>config/sstock.php</code>) is on outside production. <code>phpunit.xml</code> sets <code>true</code> so the tests do not depend on <code>.env</code></td></tr>
                <tr><td><code>SSTOCK_REORDER_EXCLUDE_DAYS</code></td><td>Batches expiring within N days do not count as available stock for the Reorder Point (default 0)</td></tr>
                <tr><td><code>config/devlib.php</code></td><td><code>back_history = false</code> (<code>DL_BACK_HISTORY</code>): the Back button goes to the module grid, not the previous screen; <code>translate_fixed = true</code></td></tr>
            </tbody>
        </table>

        <h3>Deployment checklist</h3>
        <ol class="g-steps">
            <li>Clone the repository next to <code>../devlib</code> (<code>telasih/devlib</code> is a path repository without a remote) and run <code>composer setup</code>.</li>
            <li>Set <code>APP_ENV=production</code>, <code>APP_DEBUG=false</code>, real SMTP (<a href="#mail">Mail</a>), and a strong <code>DB_PASSWORD</code>.</li>
            <li>Replace the seeded test passwords (or do not seed accounts); keep <code>SSTOCK_SAMPLE_REFRESH</code> off.</li>
            <li>Run <code>npm run build</code> after any CSS or JS change and hard-reload the browser.</li>
            <li>On a small server run <code>php artisan optimize:clear &amp;&amp; php artisan optimize</code> after every pull (config, route, event and view caches) and set <code>LOG_LEVEL=warning</code> and <code>CACHE_STORE=file</code> (sessions stay in the database). With the config cache on, <code>.env</code> is no longer read at run time, so code reads settings only through <code>config()</code>, never <code>env()</code>. The server needs no tests, docs or Node sources: <code>git sparse-checkout</code> can hide them.</li>
            <li>Help files live in <code>resources/</code> and are served through <code>auth</code> routes; never put them in <code>public/</code>.</li>
        </ol>

        <h3 id="mail">Mail</h3>
        <p class="g-note">Since GA, a second mail exists: <code>AcVisitNotice</code>, the visit notice to an AC vendor (<a href="#ga-messages">section 13</a>). It goes through the same <code>MAIL_*</code> settings.</p>
        <p>The only mail the Stok module sends is the password-reset link (<code>PasswordResetController</code>, text in <code>AppServiceProvider</code>: Indonesian, subject and signature use <code>APP_NAME</code>). Everything is set in <code>.env</code> and read through <code>config/mail.php</code>; code never calls <code>env()</code>, so it also works with <code>config:cache</code>.</p>
        <ul class="g-list">
            <li><code>MAIL_MAILER</code> (<code>log</code> writes the mail to <code>storage/logs/laravel.log</code>; <code>smtp</code> sends), <code>MAIL_HOST</code>, <code>MAIL_PORT</code>, <code>MAIL_SCHEME</code> (<code>null</code> = STARTTLS on 587; <code>smtps</code> with 465), <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>, <code>MAIL_FROM_ADDRESS</code>, <code>MAIL_FROM_NAME</code>, <code>MAIL_TIMEOUT</code> (seconds, default 15), <code>MAIL_EHLO_DOMAIN</code> (default: host of <code>APP_URL</code>).</li>
            <li>When sending fails, the user sees a friendly message on the forgot-password screen (not a 500), the exception is written to the log with <code>Log::error</code> (<code>LOG_LEVEL=warning</code> still records it), and the reset token is deleted so the user can ask again at once.</li>
            <li>Test the transport with <code>php artisan sstock:mail-test you@example.com</code>. Tests use <code>MAIL_MAILER=array</code> (<code>phpunit.xml</code>); <code>PasswordResetTest</code> and <code>MailTestCommandTest</code> cover the message, the sender and the failure path.</li>
            <li>Small server checklist: no local MTA is needed. Relay through an outside SMTP account (for example Gmail with an app password on port 587), put the <code>MAIL_*</code> values in the server <code>.env</code>, then <code>php artisan optimize:clear &amp;&amp; php artisan optimize</code> and reload php-fpm. Limit: a server that has only an IP address (no domain, no SPF/DKIM) can relay through a provider, but mail sent straight from that IP usually lands in spam or is rejected. Full steps: <code>docs/SMTP.md</code>.</li>
        </ul>

        <h3 id="testing">Testing</h3>
        <p>Tests run on in-memory SQLite (<code>phpunit.xml</code>: <code>DB_CONNECTION=sqlite</code>, <code>DB_DATABASE=:memory:</code>, array cache and session), so they never touch the MySQL database. <code>Tests\TestCase</code> disables Vite (no <code>npm run build</code> needed) and clears the static <code>Constants</code> cache between tests. <code>makeItem()</code> creates a product with its batch.</p>
        <pre class="g-code">php artisan test                     # everything
php artisan test --filter=HelpTest   # one class; use one --filter per command
composer test                        # config:clear, then php artisan test</pre>
        <table class="g-table">
            <thead><tr><th>Area</th><th>Test classes (<code>tests/Feature</code>, one in <code>tests/Unit</code>)</th></tr></thead>
            <tbody>
                <tr><td>Documents and ledger</td><td><code>DocumentLifecycleTest</code>, <code>StockFlowTest</code>, <code>TransactionalRulesTest</code>, <code>DoublePostingGuardTest</code>, <code>NumberingRaceTest</code>, <code>PreciseValueTest</code>, <code>ItemIntegrityMigrationTest</code>, <code>IssuanceStockUnitTest</code>, <code>DocumentReportsShowEntriesTest</code></td></tr>
                <tr><td>Items, units, masters</td><td><code>ItemCodeTest</code>, <code>ProductUnitTest</code>, <code>ProductUnitRulesTest</code>, <code>UnitTest</code>, <code>SupplierTest</code>, <code>KontanSupplierTest</code>, <code>QuickAddEndpointsTest</code>, <code>QuickAddScreensTest</code></td></tr>
                <tr><td>Re-order and lead time</td><td><code>ReorderOrderRecordTest</code>, <code>ReorderOrderClosingTest</code>, <code>ReorderOrderInfoTest</code>, <code>ReorderOrderHistoryTest</code>, <code>LeadTimeTest</code></td></tr>
                <tr><td>Stok Opname</td><td><code>StockCountEngineTest</code>, <code>StockCountEntryTest</code>, <code>StockCountReviewTest</code>, <code>StockCountAuditTest</code>, <code>StockCountPrintTest</code>, <code>StockCountScheduleTest</code>, <code>StockCountStartScreensTest</code>, <code>StockCountHelpTest</code>, <code>Unit/StockCountLineTest</code></td></tr>
                <tr><td>Access and session</td><td><code>SecurityTest</code>, <code>IdleLogoutTest</code>, <code>NoRememberMeTest</code>, <code>PasswordResetTest</code>, <code>BackButtonTest</code></td></tr>
                <tr><td>Company header</td><td><code>CompanyTest</code> (access, validation, logo upload and serving, three styles on documents, reports and the count sheet, defaults, group migration)</td></tr>
                <tr><td>GA vehicle care</td><td><code>VehicleCareTest</code> (due dates, status, odometer rule), <code>VehicleMasterTest</code>, <code>VehicleFormsTest</code>, <code>FleetScreensTest</code>, <code>VehicleSeederTest</code></td></tr>
                <tr><td>GA AC care</td><td><code>AcCareTest</code> (due dates, capacity, auto plan), <code>AcVisitsTest</code> (stage order, mail, reschedule), <code>AcVisitScreenTest</code>, <code>AcScheduleScreenTest</code>, <code>AcMasterTest</code>, <code>AcSeederTest</code>, <code>GaSettingsTest</code></td></tr>
                <tr><td>Seeds, config, help</td><td><code>SeedersTest</code>, <code>SampleDataTest</code>, <code>EnvExampleTest</code>, <code>WallpapersTest</code>, <code>HelpTest</code> (module list against help anchors, anchors against the table of contents, role guards, glossary, diagrams)</td></tr>
            </tbody>
        </table>

        <h2 id="decisions">11. Key decisions and why</h2>
        <table class="g-table">
            <thead><tr><th>Decision</th><th>Why</th></tr></thead>
            <tbody>
                <tr><td>Stock changes only in <code>StockLedger</code>; <code>stock_qty</code> and <code>avg_cost</code> are not mass-assignable</td><td>One auditable code path; a stray <code>update()</code> cannot desynchronise the balance from the ledger</td></tr>
                <tr><td>Ledger rows are never edited; Cancel adds reversal rows (<code>reversal_of</code>)</td><td>Full history and an honest Stock Card; the original posting stays visible</td></tr>
                <tr><td>Save creates a draft; stock moves on Confirm</td><td>Typing mistakes cost nothing until the user commits; a confirmed document is locked</td></tr>
                <tr><td>Cancel needs a reason, a reversibility check, and a Supervisor for non-Supervisors</td><td>Control without blocking daily work; stock must never go negative, so an impossible cancel is refused and corrected with an Adjustment</td></tr>
                <tr><td>Stok Opname is a separate module that ends in ONE auto-approved ADJ document, and the transaction lock sits in <code>StockLedger</code></td><td>The routine count is a different job from an ad-hoc Adjustment, so the Adjustment is untouched; the ledger stays the single writer of stock and the single place that can refuse a posting while a count runs</td></tr>
                <tr><td>Incoming units belong to the product; global conversions are only a fallback</td><td>Pack sizes differ per product (1 box can be 12 pcs for one item and 24 for another), so the factor lives with the product. The older global pair still works for units without a product factor</td></tr>
                <tr><td>Quick add posts to the normal <code>store</code> routes and uses the same module right</td><td>One validation path and one permission rule; the plus button is not rendered for users who could not save that master on its own screen</td></tr>
                <tr><td>A re-order order closes by quantity, not by any Receiving</td><td>A partial delivery must not hide a missing quantity. <code>reconcile</code> depends only on receiving data, so confirm and cancel can both call it safely</td></tr>
                <tr><td>One class (<code>Company</code>) decides every print header</td><td>Documents, count sheets and reports must never disagree about the company name; DevLib already decides the look (<code>Kop</code>), so the app only supplies data</td></tr>
                <tr><td>Kontan is a protected system supplier</td><td>A Receiving always names a supplier, so reports stay complete, and cash purchases do not invent one-off suppliers</td></tr>
                <tr><td>Idle logout = browser timer + server session lifetime</td><td>The warning needs a client timer. The server session (<code>SESSION_LIFETIME</code>) is the real limit and the ping route extends it only while the user is active</td></tr>
                <tr><td>Small settings in a key / value table (<code>constants</code>)</td><td>The Stok Opname schedule and the company header need no migration per setting. Reads are cached per request and safe when the table is missing</td></tr>
                <tr><td>Adjustment needs approval and a stale-count check</td><td>Opname differences move value; the approver must be sure the count still matches the system</td></tr>
                <tr><td>Moving average cost</td><td>Simple, no cost layers; the weekly value chart reads <code>balance_qty x balance_avg_cost</code> from the ledger</td></tr>
                <tr><td>Access = module list x role x group, with RO</td><td>One row in <code>Modules</code> adds a screen to menu, groups and route guard at once</td></tr>
                <tr><td>Documents copy recipient and department names</td><td>Old documents stay readable after master data is renamed</td></tr>
                <tr><td>Master data is deactivated, never deleted</td><td>History and foreign keys stay valid</td></tr>
                <tr><td>Product / batch model, expiry not in the code, FEFO warning only, Reorder Point per product</td><td>Matches GS1 / SAP practice; a warning avoids blocking a legitimate pick (see the <code>stok-batch-expiry</code> skill)</td></tr>
                <tr><td>Screens written in English, translated by <code>Indonesian</code></td><td>DevLib convention; the User's Guide is Indonesian plain HTML and this guide is English plain HTML, the only text outside the dictionary</td></tr>
                <tr><td>Region data kept in AdminKU's own <code>geo_*</code> tables, filled once by <code>sstock:import-geo</code></td><td>Suppliers pick a region from local tables; no outside lookup at run time</td></tr>
                <tr><td>Sample data only in seeders, through the official path</td><td>Repeatable, and stock always equals the ledger</td></tr>
                <tr><td>Help in <code>resources/</code>, diagrams as inline SVG, Technical Guide behind the <code>administrator</code> middleware</td><td>Not reachable without login, diagrams follow the theme, hiding a link alone is not security</td></tr>
            </tbody>
        </table>

        <h2 id="open">12. Open items</h2>
        <ul class="g-list">
            <li>Item / Product wording: in the owner's terms <code>m_item</code> is the stock item master (the model is still <code>Product</code>; its table became <code>m_item</code> on 2026-11-17) and a batch is <code>s_batch</code> (the model is still <code>Item</code>, table <code>items</code> until the s_ rename). Option A renames only the tables, so <code>product_id</code> points to <code>m_item</code> and <code>item_id</code> to <code>s_batch</code>. Option B also renames models, columns (<code>item_id</code> to <code>batch_id</code>, then <code>product_id</code> to <code>item_id</code>) and relations. Not decided; no table has both columns, so the column renames are independent in the database, but the search-and-replace in code must run in that order.</li>
            <li>Table prefix for <code>geo_*</code> (see Table naming convention).</li>
            <li>Re-order records: no screen to edit or delete an order (only <i>Close Order</i> exists). The list and detail are in Riwayat Pemesanan.</li>
            <li>Segregation of duties between creator and approver (Adjustment, Stok Opname).</li>
            <li>Hide the Save button on RO modules (the server already rejects the save).</li>
            <li>Company screen: no separate black-and-white logo for Ink Saver (the same PNG is used), and no second letterhead for a VAT variant (<code>companyPpn</code>).</li>
        </ul>

        <h2 id="ga">13. GA: vehicle care and AC care</h2>
        <p>General Affairs modules of AdminKU for Jays: <b>Perawatan Kendaraan</b> (vehicle documents, service schedule, odometer) and <b>Perawatan AC</b> (cleaning schedule, vendor visits, floor plan). Both are self-contained: they never touch warehouse stock. Permission codes <code>GV*</code> (vehicle) and <code>GA*</code> (AC) sit under the menu parent <code>GA</code>. Perawatan Gedung is still a placeholder (<a href="#ga-open">open items</a>); HR (<a href="#hr">section 14</a>), Purchase (<a href="#purchase">section 15</a>) and Kas (<a href="#kas">section 16</a>) are built.</p>
        <table class="g-table">
            <thead><tr><th>Layer</th><th>What it is</th><th>Where</th></tr></thead>
            <tbody>
                <tr><td>Services</td><td><code>VehicleCare</code> (due dates, status, recording), <code>AcCare</code> (settings, due dates, capacity, scheduling), <code>AcVisits</code> (visit order life cycle), <code>AcMessage</code> (pure message text, WhatsApp number and link)</td><td><code>app/Services</code></td></tr>
                <tr><td>Print and mail</td><td><code>AcVisitPrint</code> (data for the A4 minutes, <code>x-dl::print-sheet</code>), Mailable <code>AcVisitNotice</code> with plain-text view <code>mail.ac-visit</code></td><td><code>app/Support</code>, <code>app/Mail</code>, <code>resources/views/mail</code></td></tr>
                <tr><td>Screens</td><td><code>FleetController</code> (Monitoring, Detail, attachments), <code>VehicleDocController</code>, <code>VehicleServiceController</code>, <code>VehicleOdometerController</code>, <code>VehicleController</code>, <code>VehicleTypeController</code>, <code>AcCareController</code> (Monitoring, Detail AC <code>ac-care.show</code>, read only, history capped at 20 finished jobs), <code>AcScheduleController</code>, <code>AcVisitController</code>, <code>AcUnitController</code>, <code>AcAreaController</code>, <code>AcRoomController</code>, <code>AcVendorController</code>, <code>GaSettingsController</code></td><td><code>app/Http/Controllers</code>, views <code>fleet</code>, <code>vehicle-*</code>, <code>vehicles</code>, <code>ac-*</code>, <code>ga-settings</code></td></tr>
                <tr><td>Models</td><td><code>Vehicle</code>, <code>VehicleType</code>, <code>VehicleTypePlan</code>, <code>VehicleDoc</code>, <code>VehicleService</code>, <code>VehicleServiceLine</code>, <code>VehicleOdometer</code>, <code>AcArea</code>, <code>AcRoom</code>, <code>AcUnit</code>, <code>AcVendor</code>, <code>AcOrder</code>, <code>AcOrderLine</code>, <code>AcReschedule</code></td><td><code>app/Models</code></td></tr>
                <tr><td>Sample data</td><td><code>VehicleSeeder</code>, <code>AcSeeder</code> (fictional, dates relative to today so all three statuses exist; transactions through the services, never raw inserts; master rows idempotent by natural code)</td><td><code>database/seeders</code>, called by <code>DatabaseSeeder</code></td></tr>
                <tr><td>Files</td><td>Private disk <code>ga</code></td><td><code>config/filesystems.php</code>, <a href="#ga-files">below</a></td></tr>
            </tbody>
        </table>

        <h3 id="ga-tables">GA tables and the prefix rule</h3>
        <p>The prefix rule of <a href="#table-prefix">Table naming convention</a> applies: <code>m_</code> master, <code>g_</code> General Affairs transaction, <code>sys_</code> system; names singular; every model sets <code>$table</code>; foreign keys name their table (<code>constrained('m_vehicle')</code>). Migrations: <code>2026_11_18_100000_create_vehicle_masters</code>, <code>2026_11_18_110000_create_vehicle_transactions</code>, <code>2026_11_19_100000_create_ac_masters</code>, <code>2026_11_19_110000_create_ac_transactions</code>; the three <code>give_*_to_admin_groups</code> migrations grant rights (see <a href="#ga-modules">modules</a>).</p>
        @include('help.diagram', ['name' => 'tg-ga-erd', 'caption' => 'GA relations: vehicle care (left) and AC care (right). Arrow = foreign key, dashed = nullable.'])
        <table class="g-table">
            <thead><tr><th>Table</th><th>Important columns</th><th>Relations and rules</th></tr></thead>
            <tbody>
                <tr><td><code>m_vehicle_type</code></td><td><code>code</code> (unique, locked after save), <code>name</code>, <code>is_active</code></td><td>has many plan items and vehicles</td></tr>
                <tr><td><code>m_vehicle_type_plan</code></td><td><code>vehicle_type_id</code>, <code>name</code>, <code>interval_km</code>, <code>interval_months</code> (at least one), <code>sort</code>; unique (<code>vehicle_type_id</code>, <code>name</code>)</td><td>cascade delete with the type. <code>VehicleTypeController::syncPlans</code> matches form rows to rows by name (case-insensitive), so a renamed job is a new job</td></tr>
                <tr><td><code>m_vehicle</code></td><td><code>plate</code> (unique, upper-cased, acts as the code), <code>vehicle_type_id</code>, <code>name</code>, <code>year</code>, <code>vin</code>, <code>engine_no</code>, <code>kir_required</code>, <code>odometer_km</code>, <code>odometer_at</code>, <code>is_active</code></td><td><code>odometer_km</code> only rises, through <code>VehicleCare</code>; the type cannot change once a service exists (<code>VehicleRequest</code>); never deleted, only deactivated</td></tr>
                <tr><td><code>g_vehicle_doc</code></td><td><code>vehicle_id</code>, <code>doc_type</code> (<code>pajak | stnk | kir | asuransi</code>), <code>doc_no</code>, <code>paid_at</code>, <code>valid_until</code>, <code>cost</code> decimal(15,2), <code>attachment_path / _name</code>, <code>created_by</code>; index (<code>vehicle_id</code>, <code>doc_type</code>, <code>valid_until</code>)</td><td>append-only: every renewal is a new row; the current validity is the greatest <code>valid_until</code> per vehicle and type</td></tr>
                <tr><td><code>g_vehicle_service</code></td><td><code>vehicle_id</code>, <code>serviced_at</code>, <code>odometer_km</code>, <code>workshop</code>, <code>cost</code>, <code>note</code>, <code>created_by</code></td><td>has many lines</td></tr>
                <tr><td><code>g_vehicle_service_line</code></td><td><code>vehicle_service_id</code> (cascade), <code>vehicle_type_plan_id</code> (nullable, <code>nullOnDelete</code>), <code>name</code> (copied)</td><td>the copied name keeps history readable when the plan item is renamed or removed; the schedule reads the last service of each plan item from these rows</td></tr>
                <tr><td><code>g_vehicle_odometer</code></td><td><code>vehicle_id</code>, <code>read_at</code>, <code>km</code>, <code>source</code> (<code>awal | manual | servis</code>), <code>recorded_by</code>, <code>photo_path / _name</code></td><td><code>awal</code> row = the starting odometer written when the vehicle is registered; it is the schedule baseline of a job never serviced</td></tr>
                <tr><td><code>m_ac_area</code></td><td><code>code</code> (unique), <code>name</code>, <code>plan_path / _name</code>, <code>plan_w</code>, <code>plan_h</code>, <code>is_active</code></td><td>cannot be deactivated while it has active rooms</td></tr>
                <tr><td><code>m_ac_room</code></td><td><code>area_id</code>, <code>code</code> (unique, may change), <code>name</code>, <code>x, y, w, h</code> (percent of the plan), <code>pic_name</code>, <code>pic_phone</code>, <code>pic_email</code>, <code>is_active</code></td><td>cannot be deactivated while it has active units</td></tr>
                <tr><td><code>m_ac_unit</code></td><td><code>asset_code</code> (unique, permanent, <code>AC-nnnn</code>), <code>room_id</code>, <code>brand</code>, <code>model</code>, <code>capacity_pk</code>, <code>installed_at</code>, <code>pos_x</code>, <code>pos_y</code> (percent, both or none), <code>last_wash_at</code>, <code>last_major_at</code> (before the system), <code>is_active</code></td><td>moving an AC changes <code>room_id</code>, never the asset code; cannot be deactivated while it has an open order</td></tr>
                <tr><td><code>m_ac_vendor</code></td><td><code>code</code> (unique, <code>VND-nnn</code>), <code>name</code>, <code>contact_name</code>, <code>phone</code> (WhatsApp), <code>email</code>, <code>is_active</code></td><td>capacity is counted per vendor</td></tr>
                <tr><td><code>g_ac_service_order</code></td><td><code>no</code> (unique, <code>ACV-YYMM-NNNN</code>), <code>vendor_id</code>, <code>visit_date</code>, <code>visit_time</code>, <code>status</code>, <code>sent / confirmed / done / cancelled</code> <code>_at</code> and <code>_by</code>, <code>reschedule_reason</code>, <code>cancel_reason</code>, <code>created_by</code>; indexes (<code>vendor_id</code>, <code>visit_date</code>), <code>status</code></td><td>has many lines and reschedule rows; <code>AcOrder::OPEN</code> = draft, sent, confirmed, rescheduled</td></tr>
                <tr><td><code>g_ac_service_line</code></td><td><code>order_id</code> (cascade), <code>unit_id</code>, <code>job</code> (<code>wash | major</code>), <code>done_at</code>, <code>photo_before_* / photo_after_*</code>; unique (<code>order_id</code>, <code>unit_id</code>)</td><td><code>done_at</code> is the basis of the next due date; one AC once per order</td></tr>
                <tr><td><code>g_ac_service_reschedule</code></td><td><code>order_id</code> (cascade), <code>from_date/time</code>, <code>to_date/time</code>, <code>reason</code>, <code>created_by</code></td><td>history of every move; the order keeps only the last reason</td></tr>
                <tr><td><code>sys_constant</code></td><td>keys <code>ga.vehicle.*</code> and <code>ga.ac.*</code>, group <code>ga</code></td><td>see <a href="#ga-settings">settings</a></td></tr>
            </tbody>
        </table>

        <h3 id="ga-vehicle">Vehicle care: due dates and status</h3>
        <p><code>VehicleCare::overview(vehicles)</code> computes everything for a set of vehicles with a fixed number of queries (current documents, last service per plan item, starting odometers, plans of the types), so the Monitoring grid does not grow with the fleet. Detail uses the same call with one vehicle.</p>
        <table class="g-table">
            <thead><tr><th>Item</th><th>Rule</th></tr></thead>
            <tbody>
                <tr><td>Document validity</td><td><code>VehicleCare::DOCS</code>: <code>pajak</code> 12 months, <code>stnk</code> 60 months, <code>kir</code> 6 months (only when <code>kir_required</code>), <code>asuransi</code> 12 months. Default <code>valid_until</code> = <code>paid_at</code> + months with <code>addMonthsNoOverflow</code> (31 Aug + 6 months = 28 Feb); the form fills it with the same rule in JavaScript until the user edits it. The server validates <code>valid_until &gt; paid_at</code> and at most 10 years ahead.</td></tr>
                <tr><td>Document status</td><td>Current document = greatest <code>valid_until</code>. <code>terlambat</code> when it is before today, <code>segera</code> when it is within <code>ga.vehicle.soon_days</code>, else <code>aman</code>. No document: <code>segera</code> for pajak, stnk and a required kir; asuransi and a non-required kir give no status at all.</td></tr>
                <tr><td>Plan item due</td><td>Base = last service of that plan item (date, odometer), else the <code>awal</code> odometer row, else today. <code>next_km</code> = base km + <code>interval_km</code>; <code>next_at</code> = base date + <code>interval_months</code>. <code>terlambat</code> if <code>km_left &lt; 0</code> or <code>days_left &lt; 0</code>; <code>segera</code> if <code>km_left &lt;= ga.vehicle.soon_km</code> or <code>days_left &lt;= soon_days</code>; else <code>aman</code>. Whichever limit is reached first decides. Progress = the larger of the km ratio and the day ratio.</td></tr>
                <tr><td>Next job</td><td>Worst status first, then the smallest ETA in days (days left, or km left divided by the average daily use since the starting odometer).</td></tr>
                <tr><td>Vehicle status</td><td>Worst of all document statuses and all plan item statuses (<code>aman &lt; segera &lt; terlambat</code>). The Monitoring grid sorts by status then plate.</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Recording:</b> <code>recordDoc</code>, <code>recordService</code>, <code>recordOdometer</code> and <code>startOdometer</code>. Service and odometer run in a DB transaction that re-reads the vehicle row <code>FOR UPDATE</code>, so two clerks cannot both pass the "odometer must not fall" check. <code>assertOdometer</code> refuses a km below <code>m_vehicle.odometer_km</code> with a message naming the last value and date; <code>raiseOdometer</code> updates the vehicle only when the reading is higher or later.</li>
            <li>A service stores its jobs as lines, writes a <code>servis</code> odometer row and raises the vehicle odometer. Jobs must belong to the plan of the vehicle's type or a validation error is thrown. <code>note</code> is required when no job is ticked (<code>VehicleServiceRequest</code>). Money uses <code>App\Support\Decimal</code> (bcmath) and is stored as decimal(15,2).</li>
            <li>Registering a vehicle (<code>VehicleController::store</code>) creates the vehicle and its <code>awal</code> odometer row in one transaction. <code>odometer_km</code> is <code>prohibited</code> in the update request.</li>
            <li>The three input screens call <code>abort_unless(canWrite(...))</code> on GET as well, so a read-only user cannot even open the form.</li>
        </ul>

        <h3 id="ga-ac">AC care: due dates, status and codes</h3>
        <table class="g-table">
            <thead><tr><th>Item</th><th>Rule (<code>AcCare</code>)</th></tr></thead>
            <tbody>
                <tr><td>Last wash</td><td>Latest of <code>last_wash_at</code>, <code>last_major_at</code> and the <code>done_at</code> of any done line (a major service includes the wash).</td></tr>
                <tr><td>Last major</td><td>Latest of <code>last_major_at</code> and the <code>done_at</code> of done <code>major</code> lines.</td></tr>
                <tr><td>Next dates</td><td><code>next_wash</code> = last wash + <code>ga.ac.wash_months</code>; <code>next_major</code> = last major + <code>ga.ac.major_months</code>; with no history both start from <code>installed_at</code>, then from the registration date. <code>addMonthsNoOverflow</code> throughout.</td></tr>
                <tr><td>Due and status</td><td>Due = the earlier of the two. <code>terlambat</code> if before today, <code>segera</code> within <code>ga.ac.soon_days</code>, else <code>aman</code>.</td></tr>
                <tr><td>Job type</td><td><code>jobFor(next_major, visit)</code>: <code>major</code> when the next major is due on or before visit date + <code>soon_days</code>, else <code>wash</code>. Computed at scheduling time and stored on the line.</td></tr>
                <tr><td>Codes</td><td>Area and room code from the name (<code>AcCare::codeFrom</code>: several words = initials, up to 4; one word = first three letters; <code>uniqueCode</code> appends 2, 3, ...). <code>AC-nnnn</code> (<code>AcUnit::nextCode</code>) and <code>VND-nnn</code> (<code>AcVendor::nextCode</code>) are suggestions the user may override before saving. Asset code is rejected on update (<code>prohibited</code>); the room code can change.</td></tr>
                <tr><td>Open order per AC</td><td><code>openLines()</code> joins lines and orders in <code>AcOrder::OPEN</code>; <code>assertNoOpen</code> allows one open order per AC; deactivating an AC with an open order is refused.</td></tr>
            </tbody>
        </table>
        <p>Like vehicles, <code>AcCare::overview</code> runs a fixed number of queries (last done per unit and job, open lines).</p>

        <h3 id="ga-capacity">Scheduling, capacity and concurrency</h3>
        @include('help.diagram', ['name' => 'tg-ga-ac-schedule-flow', 'caption' => 'AcCare::schedule(): the checks in order. Any failure throws a validation error and rolls the transaction back.'])
        <ul class="g-list">
            <li><b>Capacity is per vendor and per day</b> (<code>ga.ac.capacity</code>, default 3). <code>used()</code> counts the lines of every order of that vendor on that date whose status is not <code>cancelled</code>, so done visits still use their day.</li>
            <li><b>Enforced on the server.</b> The calendar and the disabled buttons are convenience only. <code>schedule()</code>, <code>autoPlan()</code> and <code>AcVisits::reschedule()</code> run inside <code>DB::transaction</code> and first lock the <b>vendor row</b> (<code>lockForUpdate</code>), so two planners serialize and the second sees the first one's lines. Lock order is always vendor first, then order; <code>reschedule()</code> follows the same order to avoid deadlocks.</li>
            <li><b>Slot rules</b> (<code>assertSlot</code>): <code>Y-m-d</code> date that is real and not in the past, ISO weekday in <code>ga.ac.days</code>, time <code>HH:MM</code> between <code>ga.ac.start_time</code> and <code>ga.ac.end_time</code>.</li>
            <li><b>Order number</b> <code>ACV-YYMM-NNNN</code>: <code>createOrder</code> reads the last number of the month, inserts, and on a unique-index collision retries up to five times, locking the read from the second try (same idea as stock document numbers).</li>
            <li><b>Auto plan</b> (<code>autoPlan</code>): all active ACs with status <code>segera</code> or <code>terlambat</code> and no open order, ordered by days to due then asset code; walks forward day by day from the chosen date (never before today, at most 400 days), skipping disallowed weekdays and full days, filling the remaining capacity. Each day becomes (or extends) a draft order. The area filter of the screen is not applied.</li>
            <li><b>Draft reuse:</b> if the vendor already has a draft order for that date, new lines are added to it instead of creating a second one.</li>
            <li><b>Capacity setting from the schedule screen:</b> <code>ac-schedule.capacity</code> writes <code>ga.ac.capacity</code> (<code>AcScheduleRequest</code>: integer 1..10). The settings screen allows 1..20; the reader clamps to 1..20. Existing orders are never changed.</li>
        </ul>

        <h3 id="ga-visit">Visit order state machine</h3>
        @include('help.diagram', ['name' => 'tg-ga-ac-visit-states', 'caption' => 'Visit order statuses and the methods that move between them.'])
        <table class="g-table">
            <thead><tr><th>From</th><th>Method</th><th>To</th><th>Rules and side effects</th></tr></thead>
            <tbody>
                <tr><td>(new)</td><td><code>AcCare::schedule()</code>, <code>autoPlan()</code></td><td><span class="g-st g-grey">draft</span></td><td>Order and lines created; see <a href="#ga-capacity">scheduling</a>.</td></tr>
                <tr><td>draft, rescheduled, sent</td><td><code>AcVisits::send()</code></td><td><span class="g-st g-yellow">sent</span></td><td>Needs at least one line, a visit date that is not past, and a vendor e-mail or a valid WhatsApp number. With an e-mail it sends <code>AcVisitNotice</code> synchronously inside the transaction; a mail failure is logged (<code>Log::error</code>), a friendly validation error is thrown and the status does not change. Records <code>sent_at</code> and <code>sent_by</code>. From <code>sent</code> it is a resend.</td></tr>
                <tr><td>sent</td><td><code>confirm()</code></td><td><span class="g-st g-grey">confirmed</span></td><td>Records <code>confirmed_at/by</code>. A manual statement that the vendor answered; the system does not read replies.</td></tr>
                <tr><td>confirmed</td><td><code>complete(done_at)</code></td><td><span class="g-st g-green">done</span></td><td><code>done_at</code> not before the visit date and not after today; sets <code>done_at</code> on every line, which feeds <code>AcCare::overview</code>.</td></tr>
                <tr><td>draft, sent, confirmed, rescheduled</td><td><code>reschedule(date, time, reason)</code></td><td><span class="g-st g-yellow">rescheduled</span></td><td>Reason 3 to 255 characters; slot rules and the capacity of the new day (excluding this order); the new slot must differ; appends <code>g_ac_service_reschedule</code>; clears <code>sent_*</code> and <code>confirmed_*</code>, so the order must be sent again.</td></tr>
                <tr><td>draft, sent, confirmed, rescheduled</td><td><code>cancel(reason)</code></td><td><span class="g-st g-red">cancelled</span></td><td>Reason 3 to 255 characters; frees the ACs and the capacity of that day.</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Stage order is enforced.</b> Every method calls <code>AcVisits::lock()</code>: the order row is re-read <code>FOR UPDATE</code> and the status checked on that fresh copy; any other status throws "... tidak bisa: pesanan ... berstatus ... Urutan tahap: ...". A double click or two users cannot move an order twice.</li>
            <li><b>Photos:</b> <code>attachPhoto</code> accepts <code>before | after</code> per line only while the order is <code>confirmed</code> or <code>done</code>; the request allows jpg, jpeg, png up to 5 MB; the file is stored first, the line updated, and the old file deleted after the update (the new file is deleted if the update fails).</li>
            <li>Screens: the grid (<code>actions=worklist</code>, no new button) lists the latest 500 orders; the order screen offers actions by status and by <code>canWrite('GAV')</code>.</li>
        </ul>

        <h3 id="ga-messages">Messages, e-mail, WhatsApp and the minutes</h3>
        <ul class="g-list">
            <li><b>Text:</b> <code>AcMessage</code> is pure (no database, no network). <code>vendor()</code> lists date, start time, number of ACs, order number and one line per AC (area, room and code, asset code, type, job, PIC), then asks for before/after photos, the signed minutes and a reply to confirm. <code>pic()</code> builds the copy for one PIC; rooms with the same PIC and phone are merged and rooms without a PIC are listed as missing. <code>subject()</code> = "Jadwal perawatan AC {day, date} ({n} unit) - {no}". Data comes from <code>AcVisits::noticeData</code>.</li>
            <li><b>E-mail:</b> only <code>AcVisits::send</code> sends mail, when the officer presses Kirim and confirms; <code>AcVisitNotice</code> renders <code>mail.ac-visit</code> as plain text through the normal mail configuration (<a href="#mail">Mail</a>). The PIC copy is never mailed by the application.</li>
            <li><b>WhatsApp by link, not by API.</b> <code>AcMessage::waNumber</code> normalizes a number to international digits (<code>0812...</code> to <code>62812...</code>, <code>+62</code> and <code>0062</code> accepted, <code>620...</code> corrected, a bare <code>8...</code> gets <code>62</code>) and rejects anything outside 9 to 15 digits. <code>waLink</code> builds <code>https://wa.me/{number}?text={rawurlencode(text)}</code>. The button "Buka WhatsApp" only opens that link; the officer presses send in WhatsApp. Nothing is posted to a WhatsApp service. The message holds visit text only.</li>
            <li><b>PIC timing:</b> <code>picNotices()</code> returns <code>send_at</code> = visit date minus one day at <code>ga.ac.pic_notice_time</code> (default 15:00). It is a hint on the screen; there is no scheduled job (<a href="#ga-open">open items</a>).</li>
            <li><b>Minutes (Berita Acara):</b> <code>AcVisitPrint::report</code> feeds <code>x-dl::print-sheet</code> (A4 portrait): title, order number, date, vendor, MODE (status), table of ACs (room and asset code, area and room with PIC, job, done date, a blank condition column) and signature boxes for the vendor, the area PIC and GA. Cancelled orders print a "not valid" note; unfinished ones print that the sheet is filled during the visit.</li>
        </ul>

        <h3 id="ga-files">Attachments (private disk <code>ga</code>)</h3>
        <table class="g-table">
            <thead><tr><th>File</th><th>Path on disk <code>ga</code></th><th>Served by (module needed)</th><th>Accepted</th></tr></thead>
            <tbody>
                <tr><td>Document attachment</td><td><code>doc/{vehicle_id}/...</code></td><td><code>FleetController@file</code>, route <code>fleet.file</code> (GVM)</td><td>jpg, jpeg, png, pdf; 5 MB</td></tr>
                <tr><td>Odometer photo</td><td><code>odometer/{vehicle_id}/...</code></td><td><code>fleet.file</code> (GVM)</td><td>jpg, jpeg, png, pdf; 5 MB</td></tr>
                <tr><td>AC visit photos</td><td><code>ac/service/{order_id}/...</code></td><td><code>AcVisitController@file</code>, route <code>ac-visits.file</code> (GAV)</td><td>jpg, jpeg, png; 5 MB</td></tr>
                <tr><td>Area plan image</td><td><code>ac/plan/{area_id}/...</code></td><td><code>AcAreaController::planResponse</code> through <code>ac-areas.plan</code> (GAA)</td><td>jpg, jpeg, png; 5 MB; width and height are read on upload so the percent coordinates fit</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li>The disk has <code>root = storage/app/ga</code>, <code>serve = false</code>, so there is no public URL; files are streamed by the controllers behind <code>auth</code>, <code>active</code> and <code>module</code>, with <code>X-Content-Type-Options: nosniff</code> and <code>Cache-Control: private</code>. File names shown to the user are rebuilt from the plate or order number, not taken from the upload.</li>
            <li>The database keeps only <code>*_path</code> and the original name (<code>*_name</code>, cut to 150 characters). A file written before a failed insert is deleted again. <b>Back up <code>storage/app/ga</code> together with the database</b>: a database dump alone loses every attachment.</li>
        </ul>

        <h3 id="ga-settings">Settings (<code>sys_constant</code>, group <code>ga</code>)</h3>
        <p>Read through <code>App\Support\Constants</code> (cached per request) by <code>VehicleCare</code> and <code>AcCare</code>, which clamp and validate on every read, so a bad row can never break a screen. Written by the Pengaturan GA screen (<code>GaSettingsController</code>, module <code>GAP</code>, Supervisor or above), which also writes "Ubah pengaturan GA" with old and new values to <code>ActivityLog</code>. The seeders add the defaults only when a key is missing.</p>
        <table class="g-table">
            <thead><tr><th>Key</th><th>Default</th><th>Meaning and limits</th></tr></thead>
            <tbody>
                <tr><td><code>ga.vehicle.soon_days</code></td><td>30</td><td>Document and service "Segera" window in days (form 1 to 365)</td></tr>
                <tr><td><code>ga.vehicle.soon_km</code></td><td>1000</td><td>Service "Segera" window in km (form 100 to 50,000)</td></tr>
                <tr><td><code>ga.ac.wash_months</code></td><td>3</td><td>Regular wash interval (read clamp 1 to 60)</td></tr>
                <tr><td><code>ga.ac.major_months</code></td><td>12</td><td>Major service interval (read clamp 1 to 120; the form requires 1 to 60 and not shorter than the wash interval)</td></tr>
                <tr><td><code>ga.ac.soon_days</code></td><td>30</td><td>AC "Segera" window, also used by <code>jobFor</code></td></tr>
                <tr><td><code>ga.ac.capacity</code></td><td>3</td><td>ACs per vendor per day (1 to 20)</td></tr>
                <tr><td><code>ga.ac.days</code></td><td><code>1,2,3,4,5</code></td><td>Allowed ISO weekdays, comma separated; empty or invalid falls back to Monday to Friday</td></tr>
                <tr><td><code>ga.ac.start_time</code>, <code>ga.ac.end_time</code></td><td><code>09:00</code>, <code>16:00</code></td><td>Visit window, <code>HH:MM</code>; end after start</td></tr>
                <tr><td><code>ga.ac.pic_notice_time</code></td><td><code>15:00</code></td><td>Suggested send time of the PIC copy, the day before the visit</td></tr>
            </tbody>
        </table>

        <h3 id="ga-modules">Modules, rights and grants</h3>
        <p>All codes live in <code>App\Support\Modules</code> under the parent <code>GA</code>. The parent is excluded from <code>Modules::defaults()</code> (like <code>SEC</code>): a role never grants GA by itself. Access = the minimum role <b>and</b> the module ticked in the user's group; Administrators always have everything. A module on <code>ro</code> refuses every non-safe request in <code>EnsureModuleAccess</code>. The input forms also check <code>canWrite</code> on GET.</p>
        <table class="g-table">
            <thead><tr><th>Code</th><th>Menu</th><th>Min role</th><th>Guarded route prefix</th></tr></thead>
            <tbody>
                <tr><td>GVM</td><td>Monitoring Kendaraan (also Detail and attachments)</td><td>Data Entry</td><td><code>fleet.</code></td></tr>
                <tr><td>GVD</td><td>Perpanjang Dokumen Kendaraan</td><td>Data Entry</td><td><code>vehicle-docs.</code></td></tr>
                <tr><td>GVS</td><td>Catat Servis Kendaraan</td><td>Data Entry</td><td><code>vehicle-services.</code></td></tr>
                <tr><td>GVO</td><td>Catat Odometer (the driver module)</td><td>Data Entry</td><td><code>vehicle-odometers.</code></td></tr>
                <tr><td>GVK</td><td>Master Kendaraan</td><td>Data Entry</td><td><code>vehicles.</code></td></tr>
                <tr><td>GVT</td><td>Jenis Kendaraan (with Rencana Perawatan)</td><td>Data Entry</td><td><code>vehicle-types.</code></td></tr>
                <tr><td>GAS</td><td>Penjadwalan AC</td><td>Data Entry</td><td><code>ac-schedule.</code></td></tr>
                <tr><td>GAV</td><td>Kunjungan AC (also photos and minutes)</td><td>Data Entry</td><td><code>ac-visits.</code></td></tr>
                <tr><td>GAK</td><td>Master AC</td><td>Data Entry</td><td><code>ac-units.</code></td></tr>
                <tr><td>GAA</td><td>Area dan Gedung AC</td><td>Data Entry</td><td><code>ac-areas.</code></td></tr>
                <tr><td>GAR</td><td>Ruang AC</td><td>Data Entry</td><td><code>ac-rooms.</code></td></tr>
                <tr><td>GAE</td><td>Vendor AC</td><td>Data Entry</td><td><code>ac-vendors.</code></td></tr>
                <tr><td>GAP</td><td>Pengaturan GA</td><td>Supervisor</td><td><code>ga-settings.</code></td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Rights migrations</b> give the built-in group <code>ADMIN</code> read-write on the new codes with <code>insertOrIgnore</code> (safe to re-run; <code>down()</code> deletes those rows): <code>2026_11_18_120000_give_vehicle_care_to_admin_groups</code> (GVM, GVD, GVS, GVO, GVK, GVT), <code>2026_11_19_120000_give_ac_care_to_admin_groups</code> (GAS, GAV, GAK, GAA, GAR, GAE) and <code>2026_11_20_120000_give_ga_settings_to_admin_groups</code> (GAP). No other group receives anything: the Administrator ticks GA modules per group in the Grup screen (typically a GA officer group with everything, and a driver group with only GVO).</li>
            <li><b>Menu:</b> <code>Menu::layers</code> builds the GA parent from <code>Modules</code>; for Administrators only it appends the placeholder <b>Perawatan Gedung</b> (route <code>soon</code>, view <code>soon.blade.php</code>). It is the only remaining placeholder: HR and Purchase menus are now built the same way as GA (parent built from <code>Modules</code>), and Kas the same way again.</li>
            <li><b>Phone view:</b> GA has no native phone screens; its pages are the desktop pages and use the DevLib phone rules (<code>:phone-cards</code> on the grids, <code>dl-table--stack</code> tables).</li>
        </ul>

        <h3 id="ga-routes">Routes</h3>
        <table class="g-table">
            <thead><tr><th>Route name</th><th>Method and path</th><th>Notes</th></tr></thead>
            <tbody>
                <tr><td><code>fleet.index</code>, <code>fleet.show</code>, <code>fleet.file</code></td><td>GET <code>fleet</code>, <code>fleet/{vehicle}</code>, <code>fleet/file/{doc|odometer}/{id}</code></td><td>GVM; read only</td></tr>
                <tr><td><code>vehicle-docs.create / store</code>, <code>vehicle-services.create / store</code>, <code>vehicle-odometers.create / store</code></td><td>GET <code>.../create</code>, POST <code>vehicle-docs</code>, <code>vehicle-services</code>, <code>vehicle-odometers</code></td><td>GVD, GVS, GVO; RW needed even for GET; multipart for docs and odometer; redirect to <code>fleet.show</code> when the user has GVM</td></tr>
                <tr><td><code>vehicles.*</code>, <code>vehicle-types.*</code></td><td>index, create, store, show, update, <code>POST .../toggle</code></td><td>GVK, GVT; <code>PUT</code> accepts <code>_action = activate | deactivate</code></td></tr>
                <tr><td><code>ac-schedule.index / store / auto / capacity</code></td><td>GET and POST <code>ac-schedule</code>, <code>ac-schedule/auto</code>, <code>ac-schedule/capacity</code></td><td>GAS</td></tr>
                <tr><td><code>ac-visits.index / show / print / file</code> and POST <code>send, confirm, complete, reschedule, cancel, photo</code></td><td><code>ac-visits</code>, <code>ac-visits/{order}</code>, <code>.../print</code>, <code>ac-visits/file/{line}/{before|after}</code>, <code>ac-visits/{order}/{action}</code></td><td>GAV; the order screen is the only place that changes an order</td></tr>
                <tr><td><code>ac-units.*</code>, <code>ac-areas.*</code> (+ <code>ac-areas.plan</code>), <code>ac-rooms.*</code>, <code>ac-vendors.*</code></td><td>generated in a loop in <code>routes/web.php</code>: index, create, store, show, update, toggle</td><td>GAK, GAA, GAR, GAE; ids restricted with <code>whereNumber</code></td></tr>
                <tr><td><code>ga-settings.edit / update</code></td><td>GET and PUT <code>ga-settings</code></td><td>GAP</td></tr>
            </tbody>
        </table>

        <h3 id="ga-decisions">GA decisions and why</h3>
        <table class="g-table">
            <thead><tr><th>Decision</th><th>Why</th></tr></thead>
            <tbody>
                <tr><td>AC vendors live in their own table <code>m_ac_vendor</code>, not <code>m_supplier</code></td><td><code>m_supplier</code> has no e-mail column and its rows feed the supplier dropdown of Penerimaan Barang; AC contractors would pollute that list and a stock supplier would get fields it does not need</td></tr>
                <tr><td>WhatsApp through a pre-filled <code>wa.me</code> link, confirmed by a person; no WhatsApp Cloud API</td><td>The Cloud API needs a verified business account, approved message templates and per-message cost, and would send without a human glance. A link needs none of that and nothing leaves the building unless an officer presses send. Only e-mail is sent by the system, and only after a confirm dialog</td></tr>
                <tr><td>No OCR on odometer photos</td><td>A wrong reading would silently move every service schedule; the photo is evidence, the number is typed, and the server refuses a number lower than the last one</td></tr>
                <tr><td>A major AC service includes the wash</td><td>A technician who services the unit also cleans it; counting the wash separately would schedule a pointless visit three months later</td></tr>
                <tr><td>Capacity per vendor, enforced inside a transaction that locks the vendor row</td><td>Each vendor has its own crew, so a global limit is wrong; the lock makes the limit hold when two clerks plan at once</td></tr>
                <tr><td>GA menu is one item per module (<code>GVM</code>, <code>GAM</code>); the other GA modules are hidden from the sidebar (<code>Modules::MENU_UNDER</code>) only for users who hold the panel module</td><td>The sidebar stays short and the buttons on the panel open the rest; rights and route guards are still per module code, and a driver with only <code>GVO</code> still sees that one screen</td></tr>
                <tr><td>Order type (<code>g_ac_service_order.type</code>: <code>preventive</code> | <code>breakdown</code>) instead of a separate breakdown table; repair lines use job <code>fix</code>, which never moves a preventive due date</td><td>A breakdown needs the same vendor, capacity, message, stage and minutes machinery as a visit; an urgent breakdown with a reason may exceed capacity and visit days (<code>override_reason</code>)</td></tr>
                <tr><td>Result per unit (<code>g_ac_service_line</code> findings / cause / action / labor_cost, <code>g_ac_service_part</code> free-text spare-parts); the order completes itself when every line has a result</td><td>The old "complete all" stamped one date on every unit and kept no findings or costs; spare-parts are free text with suggestions, not linked to the warehouse stock</td></tr>
                <tr><td>One AC, one open order</td><td>Prevents double booking and keeps "when is this AC scheduled" a single answer</td></tr>
                <tr><td>Stage order of a visit is enforced in the service, not only hidden in the screen</td><td>A stale page or a double click must not skip Sent or complete a cancelled order; every transition re-reads the row <code>FOR UPDATE</code></td></tr>
                <tr><td>Reschedule keeps its own history table and clears sent / confirmed</td><td>The vendor must be told again; the old confirmation no longer applies; the audit trail of moves stays</td></tr>
                <tr><td>Due dates are computed from done lines plus a starting date on the unit, not stored</td><td>One source of truth; changing an interval in settings changes the next due dates without a migration</td></tr>
                <tr><td>Vehicle maintenance plan belongs to the vehicle type</td><td>Every pickup shares the same plan; typing it per service would drift. Jobs are matched by name so the form can stay a plain table</td></tr>
                <tr><td>Vehicle documents are append-only and the greatest <code>valid_until</code> wins</td><td>History of costs and attachments is kept and a late-entered older renewal cannot shorten the validity. There is deliberately no edit or delete screen yet (<a href="#ga-open">open items</a>)</td></tr>
                <tr><td>Insurance not recorded does not flag a vehicle, other documents do</td><td>Insurance is optional at Jays; tax, STNK and a required KIR are legal duties</td></tr>
                <tr><td>Asset code is permanent, room code is not</td><td>The asset code is a sticker on the unit; moving an AC changes only the room, so the label <code>ROOM · AC-nnnn</code> stays truthful</td></tr>
                <tr><td>GA rights come only from groups, and settings need Supervisor</td><td>GA staff and drivers are not the warehouse roles; changing due-date rules affects every schedule, so Data Entry never gets it even if a group ticks it</td></tr>
                <tr><td>Attachments on a private disk, served by controllers</td><td>Documents and photos are internal records; the same module rights as the screens apply</td></tr>
            </tbody>
        </table>

        <h3 id="ga-open">GA open items (not built)</h3>
        <ul class="g-list">
            <li>No WhatsApp Cloud API and no automatic sending: the PIC copy at 15:00 the day before is only a suggested time. There is no scheduled job, so a reminder service would need <code>routes/console.php</code> plus a queue worker or a cron entry.</li>
            <li>No OCR for odometer photos.</li>
            <li>Vehicle documents, services and odometer readings cannot be edited or deleted from a screen; a wrong entry needs a database correction by an Administrator.</li>
            <li>No alerts: Segera and Terlambat items appear only on the GA screens (no Beranda card, no e-mail digest, no GA report screens).</li>
            <li>Servicing does not deduct stock; oil and filters taken from the warehouse are issued manually in Pengeluaran Barang.</li>
            <li>Perawatan Gedung is still a placeholder ("Segera hadir"), visible to Administrators only; the table <code>g_maintenance_order</code> from the prefix plan does not exist. HR, Purchase and Kas are built (<a href="#hr">14</a>, <a href="#purchase">15</a>, <a href="#kas">16</a>).</li>
            <li>No scan or signature capture for the printed minutes (Berita Acara); the paper is signed by hand.</li>
            <li>No spare-part master list and no cost report yet: parts are free text with an optional total cost per row.</li>
        </ul>

        <h2 id="hr">14. HR: employees, recruitment, discipline</h2>
        <p>Pure personnel records: no payroll, attendance or leave. Three independent sub-modules share only the employee master. Like GA, rights come only from a group (HR is excluded from <code>Modules::defaults()</code>) and screens follow the same English-code / Indonesian-label / DevLib pattern as the rest of the app.</p>
        <table class="g-table">
            <thead><tr><th>Layer</th><th>What it is</th><th>Where</th></tr></thead>
            <tbody>
                <tr><td>Models</td><td><code>Employee</code>, <code>EmployeeFamily</code>, <code>EmployeeDoc</code>, <code>EmployeeContract</code>, <code>RecruitStep</code>, <code>Candidate</code>, <code>CandidateEvent</code>, <code>CandidateMove</code>, <code>Discipline</code></td><td><code>app/Models</code></td></tr>
                <tr><td>Screens</td><td><code>EmployeeController</code> (card/list, tabs Profile/Pekerjaan/Dokumen/Keluarga/Kontrak, photo and KTP scan crop), <code>CandidateController</code> (kanban board, drag/drop, Setup), <code>RecruitStepController</code> (stage Setup), <code>DisciplineController</code> (issue/revoke/print)</td><td><code>app/Http/Controllers</code>, views <code>employees</code>, <code>candidates</code>, <code>recruit-steps</code>, <code>discipline</code></td></tr>
                <tr><td>Sample data</td><td><code>SampleHr</code> service + <code>sample:hr</code> artisan command (idempotent, resettable independently of the main Sample Data refresh)</td><td><code>app/Services/SampleHr.php</code>, <code>app/Console/Commands/SampleHrCommand.php</code></td></tr>
            </tbody>
        </table>

        <h3 id="hr-tables">Tables</h3>
        <table class="g-table">
            <thead><tr><th>Table</th><th>Key columns</th><th>Relations and rules</th></tr></thead>
            <tbody>
                <tr><td><code>m_employee</code></td><td><code>code</code> (<code>E-0001</code>..., <code>Employee::nextCode</code>), <code>nik_ktp</code>, <code>department_id</code>, <code>employment</code> (<code>tetap|kontrak|harian|magang</code>), <code>work_status</code> (<code>working|leave|furlough</code>, only meaningful while <code>is_active</code>), <code>user_id</code> (nullable, unique, <code>nullOnDelete</code>), <code>is_active</code>, <code>exit_reason</code>, <code>exit_date</code></td><td>never deleted; leaving sets <code>is_active=false</code> and an exit reason, and deactivates the linked <code>user_id</code> account (not the other way round)</td></tr>
                <tr><td><code>m_employee_family</code>, <code>m_employee_doc</code>, <code>m_employee_contract</code></td><td>family: <code>relation</code>, <code>is_deceased</code>; doc: <code>doc_type</code>, <code>doc_no</code>, <code>expiry_date</code>; contract: <code>employment</code>, <code>start_date</code>, <code>end_date</code> (null only for <code>tetap</code>), <code>probation_end</code></td><td>all three <code>cascadeOnDelete</code> on <code>employee_id</code> (never actually triggered since employees are not deleted)</td></tr>
                <tr><td><code>m_recruit_step</code></td><td><code>name</code>, <code>sort</code>, <code>kind</code> (<code>start|mid|end</code>, exactly one <code>start</code> = Daftar and one <code>end</code> = Diterima, seeded), <code>needs_schedule</code> (pop-up date+time when a candidate moves in), <code>is_active</code></td><td>referenced by candidates and move/event rows; a stage already used is deactivated, not deleted</td></tr>
                <tr><td><code>h_recruit_candidate</code></td><td><code>nik</code> (16 digits, indexed, not unique &mdash; a new application is a new row), <code>step_id</code>, <code>status</code> (<code>active|rejected|withdrawn</code>), <code>source</code>, <code>ktp_path/name</code>, <code>cv_path/name</code>, <code>photo_path/name</code></td><td><code>h_recruit_candidate_dept</code> is a plain pivot (<code>candidate_id</code>, <code>department_id</code>, composite primary key) for "applied to more than one department"</td></tr>
                <tr><td><code>h_recruit_event</code></td><td><code>candidate_id</code>, <code>step_id</code>, <code>scheduled_on/time</code>, <code>assessor</code>, <code>result</code> (<code>waiting|pass|fail</code>), <code>note</code></td><td>one row per scheduled stage visit (interview, test); <code>CandidateEvent::result</code> feeds the "SP aktif"-style badge and the card's last-result chip</td></tr>
                <tr><td><code>h_recruit_move</code></td><td><code>candidate_id</code>, <code>from_step_id</code> (nullable), <code>to_step_id</code>, <code>moved_by</code>, <code>scheduled_on/time</code>, <code>assessor</code>, <code>moved_at</code></td><td>append-only log of every board drag or "Pindah ke"; <code>from_step_id</code> null only for the very first placement</td></tr>
                <tr><td><code>h_discipline</code></td><td><code>doc_no</code> (unique, <code>NNN/TYPE/ROMAN-MONTH/YEAR</code>), <code>year</code>, <code>seq</code> (unique together with <code>year, type</code>), <code>type</code> (<code>sp1|sp2|sp3|phk</code>), <code>employee_id</code>, <code>issued_on</code>, <code>until_on</code> (SP only), <code>status</code> (<code>issued|revoked</code>), <code>revoke_reason</code>, <code>revoked_at</code></td><td>never updated after issue except to revoke; <code>Discipline::state()</code> computes the DISPLAYED status (<code>active</code>, <code>expired</code>, <code>superseded</code>, <code>revoked</code>, or <code>issued</code> for a PHK) from dates and the other letters of the same employee, nothing is stored for that</td></tr>
            </tbody>
        </table>

        <h3 id="hr-recruit">Recruitment board and moves</h3>
        <p>A kanban board (<code>candidates.board</code>) with one column per active <code>m_recruit_step</code> ordered by <code>sort</code>; <code>Daftar</code> (kind <code>start</code>) and <code>Diterima</code> (kind <code>end</code>) are fixed endpoints, middle stages are configurable in Setup (name, order, colour, WIP limit, <code>needs_schedule</code>).</p>
        <ul class="g-list">
            <li><b>Moving a card</b> (<code>CandidateController@move</code>, drag or the "Pindah ke" menu) always writes one <code>h_recruit_move</code> row and updates <code>step_id</code>. Moving <b>into</b> a <code>needs_schedule</code> stage opens a required date+time+assessor dialog before the move is saved. Moving <b>out of</b> a scheduled stage whose event result is still <code>waiting</code> warns and offers: fill the result first, move anyway (recorded as "moved without a result"), or cancel.</li>
            <li><b>Jadikan karyawan</b> (candidate at <code>end</code> stage only) opens the Employee form pre-filled from the candidate; saving creates the <code>m_employee</code> row, the candidate itself is not changed or deleted.</li>
            <li><b>Rejected / withdrawn</b> candidates (<code>status</code>) disappear from the board but stay in the Daftar (list) view; a step once used by any candidate is deactivated rather than removed so history stays readable.</li>
            <li><b>Attention score</b> (<code>Employee::attention()</code>): worst of (a) the current contract ending within <code>CONTRACT_WARN_DAYS</code> = 60 days, (b) any document expiring within <code>DOC_WARN_DAYS</code> = 30 days, (c) "Data belum lengkap" when NIK, KTP area or phone is missing; computed on read from already-loaded <code>contracts</code>/<code>docs</code>, nothing is stored.</li>
        </ul>

        <h3 id="hr-discipline">Discipline letters</h3>
        <ul class="g-list">
            <li><b>Numbering:</b> <code>Discipline::nextNumber()</code> locks on <code>(year, type)</code> and returns the next <code>seq</code> plus the formatted <code>NNN/TYPE/ROMAN-MONTH/YEAR</code> string (for example <code>012/SP2/XI/2026</code>); called inside the save transaction, the same race-safe pattern as stock document numbers.</li>
            <li><b>Suggested type</b> on the issue form is the next rank above the employee's current <code>active</code> letter (SP1 &rarr; SP2 &rarr; SP3 &rarr; PHK); issuing a higher type out of order is allowed with a warning, never blocked.</li>
            <li><b>Revoke, never edit:</b> an issued letter's fields are frozen; a mistake is corrected with <span class="g-btn">Cabut surat</span> (reason required, sets <code>status=revoked</code>, <code>revoked_at</code>) and a fresh letter issued; the used number is never reused.</li>
            <li><b>PHK does not deactivate the employee by itself:</b> the PHK detail screen has a separate <span class="g-btn">Nonaktifkan karyawan</span> button that pre-fills the exit reason and opens the Employee Deactivate action.</li>
            <li><b>Printing:</b> an A4 letter with the company header (<code>Company::kop()</code>, like every other printout) and a signature box; the printed NIK is masked.</li>
        </ul>

        <h3 id="hr-modules">Modules and rights</h3>
        <table class="g-table">
            <thead><tr><th>Code</th><th>Menu</th><th>Min role</th><th>Guarded route prefix</th></tr></thead>
            <tbody>
                <tr><td>HRE</td><td>Karyawan</td><td>Supervisor</td><td><code>employees.</code></td></tr>
                <tr><td>HRR</td><td>Rekrutmen</td><td>Supervisor</td><td><code>candidates.</code>, <code>recruit-steps.</code></td></tr>
                <tr><td>HRD</td><td>Kedisiplinan</td><td>Supervisor</td><td><code>discipline.</code></td></tr>
            </tbody>
        </table>
        <p class="g-note">Migrations <code>give_employee_to_admin_groups</code> and <code>give_recruitment_to_admin_groups</code> grant HRE / HRR (RW) to the built-in <code>ADMIN</code> group only, the same <code>insertOrIgnore</code> / reversible pattern as GA; <code>create_discipline</code> does the same for HRD in the same migration that creates <code>h_discipline</code>. No other group receives anything automatically: an Administrator ticks HR modules per group, same as every other module.</p>

        <h2 id="purchase">15. Purchase: PR, PO, PV</h2>
        <p>Three document types, one pattern each (draft &rarr; submit &rarr; approve/reject, Supervisor-only approval, never the creator), chained: a <b>Permintaan Pembelian (PP/PR)</b> is turned into a <b>Pesanan Pembelian (PO)</b> (directly, or via Keputusan Pesanan), and a PO is received through one or more <b>Penerimaan Pembelian (PV)</b>, each PV creating and auto-confirming one Stok Receiving (<code>rm_stock</code> type IN) so stock increases without re-entering the line items.</p>
        <table class="g-table">
            <thead><tr><th>Layer</th><th>What it is</th><th>Where</th></tr></thead>
            <tbody>
                <tr><td>Models</td><td><code>PurchaseRequest</code>, <code>PurchaseRequestLine</code>, <code>PurchaseOrder</code>, <code>PurchaseOrderLine</code>, <code>PurchaseReceipt</code>, <code>PurchaseReceiptLine</code></td><td><code>app/Models</code></td></tr>
                <tr><td>Screens</td><td><code>PurchaseRequestController</code>, <code>PurchaseDecisionController</code> (Keputusan Pesanan: supplier history for one item, builds a PO draft), <code>PurchaseOrderController</code>, <code>PurchaseReceiptController</code></td><td><code>app/Http/Controllers</code>, views <code>pr</code>/<code>purchase-decisions</code>/<code>po</code>/<code>pv</code> (route prefixes below)</td></tr>
                <tr><td>Form requests</td><td><code>PurchaseRequestRequest</code>, <code>PurchaseOrderRequest</code></td><td><code>app/Http/Requests</code></td></tr>
            </tbody>
        </table>

        <h3 id="pur-tables">Tables</h3>
        <table class="g-table">
            <thead><tr><th>Table</th><th>Key columns</th><th>Relations and rules</th></tr></thead>
            <tbody>
                <tr><td><code>p_request</code> / <code>p_request_line</code></td><td>header: <code>doc_no</code> (<code>PP-YYMM-0001</code>), <code>department_id</code>, <code>need_date</code>, <code>status</code>; line: <code>item_id</code> (nullable &mdash; a free-text <code>item_name</code> is allowed for a product not yet in the master), <code>qty</code>, <code>unit</code>, <code>est_price</code>, <code>ordered_qty</code> (denormalised, see <code>syncOrdered()</code>)</td><td><code>status</code>: <code>draft &rarr; approval &rarr; approved|rejected</code>, <code>approved</code> may <code>cancelled</code>; once approved, <code>approved|partial|done</code> is recomputed by <code>syncOrdered()</code> from live PO lines</td></tr>
                <tr><td><code>p_order</code> / <code>p_order_line</code></td><td>header: <code>doc_no</code> (<code>PO-YYMM-0001</code>), <code>supplier_id</code>, <code>payment_term_days</code>, <code>vat_pct</code>, <code>status</code>; line: <code>request_line_id</code> (nullable, nulls on delete of the PR line &mdash; keeps the PO line even if the PP line disappears), <code>item_id</code>/<code>item_name</code>, <code>price</code>, <code>received_qty</code> (denormalised, see <code>syncReceived()</code>)</td><td>same status machine as PP, plus <code>approved|partial|done</code> recomputed by <code>syncReceived()</code> from live PV lines; <code>PurchaseOrder::LIVE</code> = every status except <code>rejected</code>/<code>cancelled</code> is what both sync methods count as "still alive"</td></tr>
                <tr><td><code>p_receipt</code> / <code>p_receipt_line</code></td><td>header: <code>doc_no</code> (<code>PV-YYMM-0001</code>), <code>order_id</code>, <code>supplier_id</code>, <code>ref_no</code> (supplier's surat jalan), <code>receiver</code>, <code>status</code> (<code>draft|confirmed|cancelled</code>, no approval step), <code>stock_document_id</code> (the <code>rm_stock</code> RCV created on Confirm); line: <code>order_line_id</code>, <code>qty</code>, <code>unit</code>, <code>price</code> (copied from the PO line), <code>expiry_date</code></td><td>Confirm is the only state change that matters: it books one Stok Receiving in the same transaction. <code>PurchaseReceipt::statusNow()</code> reports <code>cancelled</code> even while the stored <code>status</code> is still <code>confirmed</code>, if the linked <code>rm_stock</code> row was cancelled directly from the Stok side</td></tr>
            </tbody>
        </table>

        <h3 id="pur-lifecycle">Document lifecycle</h3>
        <table class="g-table">
            <thead><tr><th>Document</th><th>From</th><th>Action</th><th>To</th></tr></thead>
            <tbody>
                <tr><td rowspan="5">PP, PO (identical machine)</td><td>(new)</td><td>Save</td><td><span class="g-st g-grey">draft</span></td></tr>
                <tr><td>draft</td><td>Confirm (submit)</td><td><span class="g-st g-yellow">approval</span></td></tr>
                <tr><td>approval</td><td>Supervisor Approve / Reject (never the creator)</td><td><span class="g-st g-green">approved</span> / <span class="g-st g-red">rejected</span></td></tr>
                <tr><td>approved</td><td>Cancel (reason required; PP: only while no PO line still live; PO: only while nothing received)</td><td><span class="g-st g-red">cancelled</span></td></tr>
                <tr><td>rejected, cancelled</td><td>Duplicate</td><td>new draft, a copy</td></tr>
                <tr><td rowspan="2">PV</td><td>(new)</td><td>Save, then Confirm</td><td><span class="g-st g-grey">draft</span> &rarr; <span class="g-st g-green">confirmed</span> (books the Stok Receiving)</td></tr>
                <tr><td>confirmed</td><td>Cancel (Supervisor; reason required)</td><td><span class="g-st g-red">cancelled</span> (cancels the linked Stok Receiving, which reverses the ledger and PO <code>received_qty</code>)</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Numbering</b> (<code>PurchaseRequest::nextNo</code>, <code>PurchaseOrder::nextNo</code>, <code>PurchaseReceipt::nextNo</code>): <code>{PREFIX}-YYMM-NNNN</code> per month, drafts unnumbered until Confirm, the same lock-read-retry pattern as Stok document numbers.</li>
            <li><b>Keputusan Pesanan is read-only logic, no document of its own:</b> <code>PurchaseOrder::history($itemId)</code> reads <code>rm_stockcard</code> joined through <code>rm_stock</code> and <code>i_batch</code> (confirmed or cancel-pending Receiving only) grouped per supplier, giving last price, last date and how many times bought; the screen's "Buat PO" button then adds a line to the user's existing draft PO for that supplier, or starts a new one.</li>
            <li><b>Approval is single level</b>, same rule as the Stok Adjustment: <code>User::canApprove()</code> is false for Admin Data, so its Approve/Reject buttons never render for that role, and the creator is always refused regardless of role.</li>
            <li><b>Money</b> uses <code>App\Support\Decimal</code> (bcmath) like the rest of the app; PO total = sum(qty &times; price) + VAT.</li>
        </ul>

        <h3 id="pur-modules">Modules and rights</h3>
        <table class="g-table">
            <thead><tr><th>Code</th><th>Menu</th><th>Min role</th><th>Guarded route prefix</th></tr></thead>
            <tbody>
                <tr><td>PUP</td><td>Permintaan Pembelian</td><td>Data Entry</td><td><code>pr.</code></td></tr>
                <tr><td>PUK</td><td>Keputusan Pesanan</td><td>Data Entry</td><td><code>purchase-decisions.</code> (building a draft PO also needs <code>PUO</code> write)</td></tr>
                <tr><td>PUO</td><td>Pesanan Pembelian (PO)</td><td>Data Entry</td><td><code>po.</code></td></tr>
                <tr><td>PUV</td><td>Penerimaan Pembelian (PV)</td><td>Data Entry</td><td><code>pv.</code></td></tr>
            </tbody>
        </table>
        <p class="g-note">Approve/Reject on PP and PO still need the Supervisor role regardless of the group's module access (same split as every other approval in the app). Migrations <code>create_purchase_request</code>, <code>create_purchase_order</code> and <code>create_purchase_receipt</code> grant PUP, (PUK, PUO) and PUV (RW) to the built-in <code>ADMIN</code> group only, in the same migration that creates each table set.</p>

        <h2 id="kas">16. Kas: cash in, cash out, accounts</h2>
        <p>Dana masuk (Uang Masuk) and dana keluar (Uang Keluar) against one or more cash/bank accounts. Deliberately the simplest document pair in the app: Uang Keluar must name one approved Uang Masuk as its funding source and is capped by that source's remaining balance, so there is never a need for the app to track a separate running account balance &mdash; it is computed on read instead.</p>
        <table class="g-table">
            <thead><tr><th>Layer</th><th>What it is</th><th>Where</th></tr></thead>
            <tbody>
                <tr><td>Models</td><td><code>CashAccount</code>, <code>CashIn</code>, <code>CashOut</code></td><td><code>app/Models</code></td></tr>
                <tr><td>Screens</td><td><code>CashAccountController</code> (master, opened via Setup), <code>CashInController</code>, <code>CashOutController</code>, <code>CashReportController</code> (<a href="#kas-reports">Reports</a>)</td><td><code>app/Http/Controllers</code>, views <code>cash-accounts</code>, <code>cash-in</code>, <code>cash-out</code>, <code>reports/cash-*</code></td></tr>
                <tr><td>Form requests</td><td><code>CashAccountRequest</code>, <code>CashInRequest</code>, <code>CashOutRequest</code></td><td><code>app/Http/Requests</code></td></tr>
                <tr><td>Files</td><td>Private disk <code>kas</code> (<code>in/...</code>, <code>out/...</code>), proof attachment per document, served by <code>CashInController@file</code> / <code>CashOutController@file</code>, same pattern as GA's private disk</td><td><code>config/filesystems.php</code></td></tr>
            </tbody>
        </table>

        <h3 id="kas-tables">Tables</h3>
        <table class="g-table">
            <thead><tr><th>Table</th><th>Key columns</th><th>Relations and rules</th></tr></thead>
            <tbody>
                <tr><td><code>m_cash_account</code></td><td><code>name</code>, <code>type</code> (<code>cash|bank</code>), <code>bank_name</code>, <code>account_no</code>, <code>account_holder</code>, <code>opening_balance</code> decimal(15,2), <code>is_active</code></td><td>never deleted, only deactivated (<code>Deactivate</code> button); <code>CashAccount::currentBalance()</code> = <code>opening_balance</code> + sum of approved Uang Masuk into this account &minus; sum of approved Uang Keluar whose linked Uang Masuk points at this account &mdash; computed every read, nothing cached</td></tr>
                <tr><td><code>k_cash_in</code></td><td><code>doc_no</code> (<code>UM-YYMM-0001</code>), <code>cash_account_id</code> (<code>restrictOnDelete</code>), <code>source_name</code> (free text: institution, donor, "Pendapatan operasional", ...), <code>amount</code> decimal(15,2), <code>status</code></td><td>the funding side; <code>CashIn::usedAmount()</code> = sum of its own <code>approved</code> <code>k_cash_out</code> children, <code>remainingAmount()</code> = <code>amount - usedAmount()</code></td></tr>
                <tr><td><code>k_cash_out</code></td><td><code>doc_no</code> (<code>UK-YYMM-0001</code>), <code>cash_in_id</code> (<b>required</b>, <code>restrictOnDelete</code> &mdash; every Uang Keluar must name a source), <code>amount</code>, <code>purpose</code>, <code>status</code></td><td>no <code>cash_account_id</code> of its own: <code>CashOut::cashAccount()</code> simply reads <code>cashIn.cashAccount</code>, so the origin account always follows the chosen Uang Masuk</td></tr>
            </tbody>
        </table>

        <h3 id="kas-lifecycle">Document lifecycle</h3>
        <table class="g-table">
            <thead><tr><th>From</th><th>Action</th><th>To</th><th>Extra check</th></tr></thead>
            <tbody>
                <tr><td>(new)</td><td>Save</td><td><span class="g-st g-grey">draft</span></td><td>&mdash;</td></tr>
                <tr><td>draft</td><td>Confirm (submit)</td><td><span class="g-st g-yellow">approval</span></td><td>number born here (<code>UM-</code>/<code>UK-YYMM-0001</code>)</td></tr>
                <tr><td>approval</td><td>Supervisor Approve (never the creator, never Admin Data)</td><td><span class="g-st g-green">approved</span></td><td>Uang Keluar only: re-reads the Uang Masuk row <code>lockForUpdate</code> and refuses if <code>amount &gt; remainingAmount()</code> &mdash; the only place the balance is actually enforced</td></tr>
                <tr><td>approval</td><td>Supervisor Reject (reason required)</td><td><span class="g-st g-red">rejected</span></td><td>&mdash;</td></tr>
                <tr><td>approval, approved</td><td>Cancel (reason required; owner or Supervisor)</td><td><span class="g-st g-red">cancelled</span></td><td>Uang Masuk only: refused while it still has a linked Uang Keluar in <code>approval</code> or <code>approved</code> status</td></tr>
                <tr><td>draft</td><td>Delete (owner or Supervisor)</td><td>(removed)</td><td>&mdash;</td></tr>
                <tr><td>rejected, cancelled</td><td>Duplicate</td><td>new draft, a copy</td><td>&mdash;</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Balance is checked exactly once</b>, at Uang Keluar Approve, inside a transaction that locks the Uang Masuk row (<code>CashIn::query()->lockForUpdate()</code>) &mdash; the same race guard pattern as stock document numbering, so two Uang Keluar approved at the same instant cannot both pass against the same remaining balance.</li>
            <li><b>Approval is single level</b>, same rule as every other approval in the app: <code>User::canApprove()</code> is false for Admin Data (role opens the screens, buttons simply are not drawn), and the creator is always refused regardless of role.</li>
        </ul>

        <h3 id="kas-modules">Modules, rights and deliberate simplifications</h3>
        <table class="g-table">
            <thead><tr><th>Code</th><th>Menu</th><th>Min role</th><th>Guarded route prefix</th></tr></thead>
            <tbody>
                <tr><td>KSM</td><td>Uang Masuk</td><td>Data Entry</td><td><code>cash-in.</code></td></tr>
                <tr><td>KSK</td><td>Uang Keluar</td><td>Data Entry</td><td><code>cash-out.</code></td></tr>
                <tr><td>KSR</td><td>Kas / Rekening (opened from the Setup button on KSM/KSK, like GA's Pengaturan under <code>Modules::MENU_UNDER</code>)</td><td>Supervisor</td><td><code>cash-accounts.</code></td></tr>
            </tbody>
        </table>
        <p class="g-note">Migration <code>create_cash_in_out</code> grants KSM/KSK/KSR (RW) to the built-in <code>ADMIN</code> group only, same pattern as every other module. It is dated 2026-11-04, before the <code>sys_</code> rename (2026-11-17); the grant code was patched to detect whichever of <code>user_groups</code>/<code>sys_user_group</code> exists at that point in a fresh migrate, instead of assuming the post-rename name (the earlier hardcoded name broke <code>migrate:fresh</code> and the whole test suite until this was found and fixed).</p>
        <p class="g-note"><b>Menu parent:</b> all six Kas module codes (KSM, KSK, KSR, R-KFLOW, R-KIN, R-KOUT in <a href="#kas-reports">Reports</a> below) sit under parent code <code>KEU</code>, labelled "Keuangan" (<code>App\Support\Modules::PARENTS</code>; renamed from <code>KAS</code> on 2026-11-04 so other, not-yet-built finance modules can become sibling sub-groups later without touching Kas). On the <b>desktop</b> menu only, <code>Menu::layers()</code> wraps the whole <code>KEU</code> branch in one sub-group labelled "Kas" (the same technique used to wrap Stok's Transaksi and Laporan under one "Stok" parent), so a user actually clicks Keuangan &rarr; Kas &rarr; a screen. The phone menu (<code>Menu::modules()</code>) stays flat under "Keuangan", no sub-group. Module codes, route guards and group rights are unaffected by either the parent rename or the sub-group wrapper.</p>
        <table class="g-table">
            <thead><tr><th>Simplification (on purpose, for now)</th><th>Why / what it means</th></tr></thead>
            <tbody>
                <tr><td>Uang Keluar has no <code>cash_account_id</code> of its own; its origin account is whatever account the linked Uang Masuk belongs to</td><td>One join fewer, and the two documents can never disagree about which account a payment came from</td></tr>
                <tr><td><code>source_name</code> (Uang Masuk) and <code>purpose</code> (Uang Keluar) are free text, not a master list</td><td>Fast to ship for Jays' actual usage pattern (ad-hoc donors, operational income, ad-hoc expenses); a Sumber Dana / Kategori master can be added later without touching the approval flow</td></tr>
                <tr><td>No transfer between two Kas/Rekening accounts</td><td>Deliberately deferred: it needs its own document type (or a same-amount Uang Keluar + Uang Masuk pair with a link between them) and was out of scope for this first cut</td></tr>
                <tr><td>Account balance is always computed, never stored</td><td>Cannot drift from the documents; the cost is a small aggregate query per account view, acceptable at Jays' transaction volume</td></tr>
            </tbody>
        </table>

        <h3 id="kas-reports">Reports</h3>
        <p>Three <code>x-dl::report</code> screens (<code>CashReportController</code>, views <code>reports/cash-*</code>), built on the same pattern as the Stok reports (<a href="#screens">section 8</a>): a GET filter form, a criteria box, a summary row, a grouped table and a printable A4 landscape sheet. Reviewed and approved by the owner before being built (see commit <code>8e86efa</code>).</p>
        <table class="g-table">
            <thead><tr><th>Report</th><th>Rows and grouping</th><th>Filters</th></tr></thead>
            <tbody>
                <tr><td><b>Laporan Arus Kas</b> (<code>cashFlow</code>, route <code>reports.cash-flow</code>)</td><td>One book per <code>m_cash_account</code> (or combined into one "Semua Kas" book via the group toggle, <code>group-by="cash_account"</code> vs <code>"all"</code>): an opening-balance row, every <code>approved</code> Uang Masuk / Uang Keluar in the period in chronological order with a running <code>saldo</code>, then a closing-balance row. The opening balance is <b>not</b> <code>m_cash_account.opening_balance</code> read as-is: it is <code>opening_balance</code> plus every <code>approved</code> Uang Masuk / Uang Keluar dated before <code>from</code>, so it already reflects all activity prior to the chosen period &mdash; the same value <code>CashAccount::currentBalance()</code> would give if <code>to</code> were yesterday.</td><td>Period (<code>from</code>/<code>to</code>, default start of this month to today, same <code>period()</code> helper as the Stok reports), Kas (one account or all)</td></tr>
                <tr><td><b>Laporan Penerimaan</b> (<code>receipts</code>, route <code>reports.cash-receipts</code>)</td><td><code>k_cash_in</code> rows grouped by <code>source_name</code> (<code>group-by="source_name"</code>); columns <code>amount</code>, <code>used</code> (<code>CashIn::usedAmount()</code>) and <code>remaining</code> (<code>CashIn::remainingAmount()</code>) per row, summed in the report footer</td><td>Period (on <code>doc_date</code>), Sumber Dana (exact match against the distinct <code>source_name</code> values seen so far), Status (default <code>approved</code>, see below)</td></tr>
                <tr><td><b>Laporan Pengeluaran</b> (<code>disbursements</code>, route <code>reports.cash-disbursements</code>)</td><td><code>k_cash_out</code> rows; "Sumber Dana" and "Kas" columns are read through the linked <code>cashIn</code> (<code>$c->cashIn->source_name</code>, <code>$c->cashIn->cashAccount->name</code>), not a column of <code>k_cash_out</code> itself</td><td>Period (on <code>doc_date</code>), Kas, Sumber Dana (filters on the linked Uang Masuk's <code>source_name</code>), Status (default <code>approved</code>)</td></tr>
            </tbody>
        </table>
        <ul class="g-list">
            <li><b>Status filter bug and fix:</b> the "Semua" (all statuses) choice posts <code>status=</code> (empty string). Laravel's <code>ConvertEmptyStringsToNull</code> middleware turns that into <code>null</code> before the controller sees it, and a plain <code>$request->query('status', 'approved')</code> then falls back to the default even when the user explicitly chose "Semua". Fixed in <code>CashReportController::statusFilter()</code> by checking <code>$request->has('status')</code> (true whenever the key was sent, regardless of its value) instead of relying on the default parameter of <code>query()</code>.</li>
            <li><b>Modules:</b> three new codes under the <code>KEU</code> parent (see the menu-parent note above), type <code>report</code> (same <code>type</code> value the Stok report codes use), minimum role Data Entry (read-only, no RW concept for a report) &mdash; <code>R-KFLOW</code> Laporan Arus Kas, <code>R-KIN</code> Laporan Penerimaan, <code>R-KOUT</code> Laporan Pengeluaran. They needed no new grant migration: an existing data migration (2026-12-03, the Admin Data role rollout) grants every module outside <code>SEC</code> from <code>Modules::all()</code> read dynamically, so a fresh migrate picks up these three automatically for the <code>SUPERVISOR</code> and <code>ADMINDATA</code> groups; they also need no Opening/menu wiring, the existing "every module tile shows on its parent's Beranda" mechanism places them on Beranda Kas by itself.</li>
            <li><b>Tests:</b> <code>CashReportTest</code> (cash-flow running balance across accounts, per-source grouping and used/remaining totals, default-approved vs. "Semua" status, per-account filter, module access).</li>
        </ul>
    </main>
</div>
@endsection
