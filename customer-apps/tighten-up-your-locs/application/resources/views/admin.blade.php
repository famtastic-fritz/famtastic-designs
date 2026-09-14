<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#efe6d4">
    <title>Your booking desk · Tighten Up Your Locs</title>
    <link rel="stylesheet" href="/admin-assets/admin.css?v=20260914-1">
    <script type="module" src="/admin-assets/admin.js?v=20260914-1"></script>
</head>
<body>
<a class="skip-link" href="#desk-main">Skip to booking desk</a>
<header class="desk-header shell">
    <a class="wordmark" href="/" aria-label="Tighten Up Your Locs public website">TIGHTEN UP<span>YOUR LOCS</span></a>
    <div class="header-actions">
        <a class="quiet-link" href="/">View website</a>
        <form method="POST" action="/admin/logout">@csrf<button class="button button-quiet" type="submit">Sign out</button></form>
    </div>
</header>
<main id="desk-main" class="shell desk-main" data-component-instance="locs.independent-booking-desk.v1">
    <div class="desk-intro">
        <div><p class="eyebrow">Tighten Up Your Locs · Private admin</p><h1>Your booking desk.</h1><p class="intro-copy">A little more room for what you do best.</p></div>
        <div class="timezone-note"><span class="eyebrow">Your calendar</span><strong>Port St. Lucie time</strong><span>America/New_York · adjusts for daylight saving</span></div>
    </div>
    <div class="desk-toolbar">
        <nav class="desk-nav" aria-label="Booking desk sections">
            <button type="button" data-section="requests" aria-current="page">Requests</button>
            <button type="button" data-section="calendar">Calendar</button>
            <button type="button" data-section="openings">Openings</button>
        </nav>
        <button id="refresh-desk" class="button button-quiet" type="button">Refresh</button>
    </div>
    <div id="desk-notice" class="notice" role="status" hidden></div>
    <div id="desk-error" class="notice notice-error" role="alert" hidden></div>
    <div id="desk-partial" class="notice" role="status" hidden></div>
    <p id="desk-loading" class="loading-note" role="status">Loading your saved booking information…</p>
    <noscript><p class="notice notice-error">JavaScript is needed to manage bookings. Enable it and reload this page. Your saved appointments have not changed.</p></noscript>
    <div id="desk-content" hidden>
        <section id="section-requests" aria-labelledby="requests-title">
            <div class="section-heading"><div><p class="eyebrow">Start a conversation</p><h2 id="requests-title">Client requests</h2></div><p>A request isn’t a reservation. Confirm an agreed time, or send a proposal for your client to accept.</p></div>
            <div id="request-list" class="record-list"></div>
        </section>
        <section id="section-calendar" aria-labelledby="calendar-title" hidden>
            <div class="section-heading"><div><p class="eyebrow">Make room for your day</p><h2 id="calendar-title">Your calendar</h2></div><p>Confirmed appointments reserve time. Proposals are clearly marked and aren’t reserved.</p></div>
            <div class="calendar-layout">
                <section class="panel month-panel" aria-label="Choose a calendar day">
                    <div class="month-heading"><button id="previous-month" class="icon-button" type="button" aria-label="Previous month">←</button><h3 id="calendar-month"></h3><button id="next-month" class="icon-button" type="button" aria-label="Next month">→</button></div>
                    <div class="week-labels" aria-hidden="true"><span>Su</span><span>Mo</span><span>Tu</span><span>We</span><span>Th</span><span>Fr</span><span>Sa</span></div>
                    <div id="calendar-days" class="calendar-days" role="group" aria-label="Days in this month"></div>
                    <div class="calendar-jump"><label class="field">Go to a date<input id="selected-day" type="date" required></label><button id="today" class="button button-quiet" type="button">Today</button></div>
                    <p class="calendar-key"><span class="calendar-dot"></span> Has an appointment or proposal</p>
                </section>
                <section class="day-panel" aria-labelledby="selected-day-title"><h3 id="selected-day-title"></h3><div id="appointment-list" class="record-list"></div></section>
            </div>
        </section>
        <section id="section-openings" aria-labelledby="openings-title" hidden>
            <div class="section-heading"><div><p class="eyebrow">Invite the next visit</p><h2 id="openings-title">Available openings</h2></div><button id="new-opening" class="button" type="button">Add an opening</button></div>
            <p class="section-note">Published openings appear on your website as times clients may request. They don’t automatically book or hold an appointment. Drafts stay private.</p>
            <div id="opening-list" class="record-list"></div>
        </section>
    </div>
</main>
<footer class="desk-footer shell"><span>© {{ date('Y') }} Tighten Up Your Locs. All rights reserved.</span><nav aria-label="Website policies"><a href="/privacy/">Privacy</a><a href="/terms/">Terms</a></nav></footer>
<dialog id="action-dialog" class="action-dialog" aria-labelledby="dialog-title" aria-describedby="dialog-description">
    <form id="action-form">
        <div class="dialog-heading"><p class="eyebrow">Your next step</p><button id="close-dialog" class="icon-button" type="button" aria-label="Close without saving">×</button></div>
        <h2 id="dialog-title"></h2><p id="dialog-description"></p>
        <div id="dialog-fields"></div>
        <p id="dialog-error" class="notice notice-error" role="alert" hidden></p>
        <div class="dialog-actions"><button id="submit-action" class="button" type="submit">Save</button><button id="cancel-action" class="button button-quiet" type="button">Go back</button></div>
    </form>
</dialog>
</body>
</html>
