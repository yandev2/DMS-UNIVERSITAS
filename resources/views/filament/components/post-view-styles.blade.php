@once
    @include('filament.components.document-timeline-styles')

    <style>
        /* ==========================================================================
           UNIVBI Warta & CMS: Editorial News & Activity Showcase Design System
           Enterprise, Informative, Crisp, Dark-Mode Native (Filament 5 & Tailwind)
           ========================================================================== */

        /* Layout & Grid Helpers */
        .post-view-container {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            width: 100%;
        }

        .post-main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            align-items: start;
        }

        @media (min-width: 1024px) {
            .post-main-grid {
                grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
            }
        }

        /* Status Banner Card */
        .post-banner-card {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--doc-shadow-sm);
            color: var(--doc-text-primary);
            position: relative;
            overflow: hidden;
        }

        .post-banner-top {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--doc-border);
        }

        .post-tag-group {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        /* Crisp Rectangular Badges (Strictly NO Pill) */
        .post-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1rem;
            border-radius: 0.375rem;
            border: 1px solid transparent;
            letter-spacing: 0.025em;
            text-transform: uppercase;
        }

        .post-badge-published {
            background-color: #ecfdf5;
            color: #065f46;
            border-color: #a7f3d0;
        }

        .dark .post-badge-published {
            background-color: rgba(16, 185, 129, 0.15);
            color: #34d399;
            border-color: #047857;
        }

        .post-badge-pending {
            background-color: #fffbeb;
            color: #92400e;
            border-color: #fde68a;
        }

        .dark .post-badge-pending {
            background-color: rgba(245, 158, 11, 0.15);
            color: #fbbf24;
            border-color: #b45309;
        }

        .post-badge-draft {
            background-color: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }

        .dark .post-badge-draft {
            background-color: #27272a;
            color: #a1a1aa;
            border-color: #3f3f46;
        }

        .post-badge-revision {
            background-color: #fff1f2;
            color: #9f1239;
            border-color: #fecdd3;
        }

        .dark .post-badge-revision {
            background-color: rgba(225, 29, 72, 0.15);
            color: #f87171;
            border-color: #be123c;
        }

        .post-badge-archived {
            background-color: #f0f9ff;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .dark .post-badge-archived {
            background-color: rgba(14, 165, 233, 0.15);
            color: #38bdf8;
            border-color: #0284c7;
        }

        .post-badge-featured {
            background-color: #fef3c7;
            color: #b45309;
            border-color: #fcd34d;
        }

        .dark .post-badge-featured {
            background-color: rgba(251, 191, 36, 0.15);
            color: #fde047;
            border-color: #d97706;
        }

        .post-badge-pinned {
            background-color: #e0e7ff;
            color: #3730a3;
            border-color: #c7d2fe;
        }

        .dark .post-badge-pinned {
            background-color: rgba(99, 102, 241, 0.15);
            color: #a5b4fc;
            border-color: #4f46e5;
        }

        .post-badge-borang {
            background-color: #e0f2fe;
            color: #0369a1;
            border-color: #bae6fd;
        }

        .dark .post-badge-borang {
            background-color: rgba(14, 165, 233, 0.15);
            color: #38bdf8;
            border-color: #0284c7;
        }

        /* Stat Box Compact (Header Banner) */
        .post-stat-strip {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .post-stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--doc-text-muted);
        }

        .post-stat-chip strong {
            color: var(--doc-text-primary);
            font-weight: 700;
        }

        /* Alert Box / Moderation Note */
        .post-moderation-alert {
            margin-top: 1rem;
            padding: 0.875rem 1rem;
            border-radius: 0.375rem;
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            border-left: 4px solid #f59e0b;
            color: #92400e;
            font-size: 0.8125rem;
            line-height: 1.375rem;
        }

        .dark .post-moderation-alert {
            background-color: rgba(245, 158, 11, 0.1);
            border-color: rgba(245, 158, 11, 0.25);
            border-left-color: #f59e0b;
            color: #fcd34d;
        }

        /* Article Reading Container */
        .post-article-box {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 1.5rem;
            box-shadow: var(--doc-shadow-sm);
            color: var(--doc-text-primary);
        }

        .post-cover-frame {
            width: 100%;
            max-height: 420px;
            border-radius: 0.375rem;
            overflow: hidden;
            margin-bottom: 1.25rem;
            border: 1px solid var(--doc-border);
            background-color: var(--doc-surface-muted);
            position: relative;
        }

        .post-cover-img {
            width: 100%;
            height: 100%;
            max-height: 420px;
            object-fit: cover;
            display: block;
        }

        .post-cover-caption {
            font-size: 0.75rem;
            color: var(--doc-text-muted);
            padding: 0.5rem 0.75rem;
            background-color: var(--doc-surface-muted);
            border-top: 1px solid var(--doc-border);
            font-style: italic;
        }

        .post-article-title {
            font-size: 1.375rem;
            font-weight: 800;
            line-height: 1.875rem;
            letter-spacing: -0.02em;
            color: var(--doc-text-primary);
            margin-bottom: 0.75rem;
        }

        .post-excerpt-lead {
            font-size: 0.9375rem;
            line-height: 1.5rem;
            color: var(--doc-text-secondary);
            background-color: var(--doc-surface-muted);
            border: 1px solid var(--doc-border);
            border-left: 3px solid #2563eb;
            border-radius: 0.375rem;
            padding: 0.875rem 1rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }

        /* Article Typography (.post-prose) */
        .post-prose {
            font-size: 0.9375rem;
            line-height: 1.75;
            color: var(--doc-text-secondary);
        }

        .post-prose p {
            margin-bottom: 1.125rem;
        }

        .post-prose h1,
        .post-prose h2,
        .post-prose h3,
        .post-prose h4 {
            color: var(--doc-text-primary);
            font-weight: 700;
            margin-top: 1.5rem;
            margin-bottom: 0.625rem;
            letter-spacing: -0.015em;
        }

        .post-prose h2 {
            font-size: 1.25rem;
        }

        .post-prose h3 {
            font-size: 1.125rem;
        }

        .post-prose h4 {
            font-size: 1rem;
        }

        .post-prose ul {
            list-style-type: disc;
            padding-left: 1.25rem;
            margin-bottom: 1.125rem;
        }

        .post-prose ol {
            list-style-type: decimal;
            padding-left: 1.25rem;
            margin-bottom: 1.125rem;
        }

        .post-prose li {
            margin-bottom: 0.375rem;
        }

        .post-prose blockquote {
            border-left: 3px solid #64748b;
            padding-left: 1rem;
            margin: 1.25rem 0;
            font-style: italic;
            color: var(--doc-text-muted);
        }

        .post-prose img {
            max-width: 100%;
            border-radius: 0.375rem;
            margin: 1.25rem 0;
            border: 1px solid var(--doc-border);
        }

        .post-prose table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.25rem 0;
            font-size: 0.8125rem;
        }

        .post-prose th,
        .post-prose td {
            padding: 0.625rem 0.75rem;
            border: 1px solid var(--doc-border);
        }

        .post-prose th {
            background-color: var(--doc-surface-muted);
            font-weight: 700;
            color: var(--doc-text-primary);
        }

        /* Sidebar Info Cards */
        .post-side-card {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 1.25rem 1.5rem;
            box-shadow: var(--doc-shadow-sm);
            color: var(--doc-text-primary);
            margin-bottom: 1.25rem;
        }

        .post-side-card-header {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--doc-border);
            margin-bottom: 0.25rem;
        }

        .post-side-card-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--doc-text-primary);
            margin: 0;
        }

        /* Sidebar Property Items (Generous Vertical Spacing to avoid cramped text near divider lines) */
        .post-prop-item {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            padding-top: 0.875rem;
            padding-bottom: 0.875rem;
            border-bottom: 1px solid var(--doc-border);
        }

        .post-prop-item:first-of-type {
            padding-top: 0.25rem;
        }

        .post-prop-item:last-of-type {
            border-bottom: none;
            padding-bottom: 0.125rem;
        }

        .post-prop-label {
            font-size: 0.6875rem;
            font-weight: 500;
            color: var(--doc-text-muted) !important;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            line-height: 1rem;
        }

        .post-prop-value {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--doc-text-primary) !important;
            line-height: 1.35rem;
        }

        /* Metric Stats Grid (Sidebar) */
        .post-metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .post-metric-box {
            background-color: var(--doc-surface-muted);
            border: 1px solid var(--doc-border);
            border-radius: 0.375rem;
            padding: 0.75rem;
            text-align: center;
        }

        .post-metric-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--doc-text-primary);
            line-height: 1.5rem;
        }

        .post-metric-lbl {
            font-size: 0.6875rem;
            font-weight: 600;
            color: var(--doc-text-muted);
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-top: 0.25rem;
        }

        /* Gallery Grid */
        .post-gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
        }

        .post-photo-card {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: var(--doc-shadow-sm);
            display: flex;
            flex-direction: column;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }

        .post-photo-card:hover {
            box-shadow: var(--doc-shadow-md);
            border-color: var(--doc-border-strong);
        }

        .post-photo-thumb-wrap {
            width: 100%;
            aspect-ratio: 16 / 10;
            overflow: hidden;
            background-color: var(--doc-surface-muted);
            position: relative;
        }

        .post-photo-thumb {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.25s ease;
        }

        .post-photo-card:hover .post-photo-thumb {
            transform: scale(1.02);
        }

        .post-photo-cover-tag {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            background-color: #2563eb;
            color: #ffffff;
            font-size: 0.6875rem;
            font-weight: 700;
            padding: 0.2rem 0.5rem;
            border-radius: 0.25rem;
            letter-spacing: 0.025em;
            text-transform: uppercase;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .post-photo-body {
            padding: 0.75rem 1rem;
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .post-photo-caption {
            font-size: 0.8125rem;
            color: var(--doc-text-primary);
            font-weight: 500;
            line-height: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .post-photo-meta {
            font-size: 0.6875rem;
            color: var(--doc-text-muted);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-top: 0.5rem;
            border-top: 1px solid var(--doc-border);
        }

        /* Attached Documents Cards */
        .post-doc-card {
            background-color: var(--doc-surface);
            border: 1px solid var(--doc-border);
            border-radius: 0.5rem;
            padding: 1rem 1.25rem;
            box-shadow: var(--doc-shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
            transition: border-color 0.15s ease;
        }

        .post-doc-card:hover {
            border-color: #2563eb;
        }

        .post-doc-left {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            flex: 1 1 300px;
        }

        .post-doc-icon-wrap {
            width: 2.25rem;
            height: 2.25rem;
            border-radius: 0.375rem;
            background-color: var(--doc-badge-info-bg);
            color: var(--doc-badge-info-solid);
            border: 1px solid var(--doc-badge-info-border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .post-doc-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
@endonce
