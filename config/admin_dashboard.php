<?php

return [
    'page_routes' => [
        'all_bookings' => 'admin.bookings.index',
        'pending_bookings' => 'admin.bookings.pending',
        'confirmed_bookings' => 'admin.bookings.confirmed',
        'completed_bookings' => 'admin.bookings.completed',
        'cancelled_rejected_bookings' => 'admin.bookings.cancelled-rejected',
        'expired_bookings' => 'admin.bookings.expired',
        'booking_disputes' => 'admin.booking-disputes.index',
        'all_requests' => 'admin.custom-requests.index',
        'new_requests' => 'admin.custom-requests.index',
        'in_progress_requests' => 'admin.custom-requests.index',
        'quoted_requests' => 'admin.custom-requests.index',
        'accepted_converted_requests' => 'admin.custom-requests.index',
        'closed_requests' => 'admin.custom-requests.index',
        'all_users' => 'admin.users.index',
        'travelers' => 'admin.users.travelers',
        'agency_owners' => 'admin.users.agency-owners',
        'administrators' => 'admin.users.administrators',
        'suspended_deactivated_accounts' => 'admin.users.suspended',
    ],
    'sections' => [
        'main' => [
            'icon' => 'home',
            'groups' => [
                ['page' => 'overview', 'icon' => 'layout'],
                ['page' => 'alerts', 'icon' => 'bell'],
            ],
        ],
        'platform_management' => [
            'icon' => 'layers',
            'groups' => [
                ['label' => 'users', 'icon' => 'users', 'pages' => [
                    'all_users',
                    'travelers',
                    'agency_owners',
                    'administrators',
                    'suspended_deactivated_accounts',
                ]],
                ['label' => 'agencies', 'icon' => 'building', 'pages' => [
                    'all_agencies',
                    'pending_verification',
                    'verified_agencies',
                    'unverified_agencies',
                    'suspended_agencies',
                ]],
                ['label' => 'travel_management', 'icon' => 'map', 'pages' => [
                    'all_travel_packages',
                    'published_packages',
                    'draft_unpublished_packages',
                    'reported_pending_review',
                    'travel_services',
                    'destinations',
                    'countries',
                ]],
                ['label' => 'bookings', 'icon' => 'calendar', 'pages' => [
                    'all_bookings',
                    'pending_bookings',
                    'confirmed_bookings',
                    'completed_bookings',
                    'cancelled_rejected_bookings',
                    'expired_bookings',
                    'booking_disputes',
                ]],
                ['label' => 'custom_travel_requests', 'icon' => 'clipboard', 'pages' => [
                    'all_requests',
                    'new_requests',
                    'in_progress_requests',
                    'quoted_requests',
                    'accepted_converted_requests',
                    'closed_requests',
                ]],
                ['label' => 'roles_permissions', 'icon' => 'key', 'pages' => [
                    'roles',
                    'permissions',
                ]],
            ],
        ],
        'trust_safety' => [
            'icon' => 'shield',
            'groups' => [
                ['label' => 'verification', 'icon' => 'shield_check', 'pages' => [
                    'verification_pending',
                    'approved',
                    'rejected',
                ]],
                ['label' => 'reviews_reports', 'icon' => 'flag', 'pages' => [
                    'reviews_ratings',
                    'reported_content',
                    'user_agency_complaints',
                ]],
                ['label' => 'trust_scores', 'icon' => 'star', 'pages' => [
                    'trust_score_overview',
                    'agencies_requiring_attention',
                ]],
                ['label' => 'audit_logs', 'icon' => 'activity', 'pages' => [
                    'administrative_activity',
                    'security_events',
                ]],
            ],
        ],
        'business_insights' => [
            'icon' => 'chart',
            'groups' => [
                ['label' => 'finance', 'icon' => 'receipt', 'pages' => [
                    'financial_overview',
                    'all_transactions',
                    'successful_payments',
                    'pending_failed_payments',
                    'refunds',
                    'payment_disputes',
                    'financial_reports',
                ]],
                ['label' => 'app_analytics', 'icon' => 'chart', 'pages' => [
                    'platform_performance',
                    'user_growth',
                    'agency_growth',
                    'booking_analytics',
                    'popular_destinations',
                    'package_performance',
                    'trust_verification_statistics',
                    'export_reports',
                ]],
            ],
        ],
        'communication' => [
            'icon' => 'message',
            'groups' => [
                ['label' => 'support_center', 'icon' => 'life_buoy', 'pages' => [
                    'all_support_tickets',
                    'open_tickets',
                    'resolved_tickets',
                    'traveler_inquiries',
                    'agency_inquiries',
                ]],
                ['label' => 'announcements', 'icon' => 'megaphone', 'pages' => [
                    'all_announcements',
                    'create_announcement',
                    'delivery_history',
                ]],
            ],
        ],
        'system' => [
            'icon' => 'cog',
            'groups' => [
                ['label' => 'content_management', 'icon' => 'file', 'pages' => [
                    'content_countries_destinations',
                    'content_travel_services',
                    'faqs_help_pages',
                    'terms_conditions',
                    'privacy_policy',
                    'homepage_featured_content',
                ]],
                ['label' => 'settings_security', 'icon' => 'settings', 'pages' => [
                    'general_settings',
                    'language_localization',
                    'currency_regional_settings',
                    'booking_rules',
                    'security_settings',
                    'active_sessions',
                    'maintenance_mode',
                    'emergency_lockdown',
                ]],
                ['page' => 'system_health', 'icon' => 'activity'],
                ['page' => 'integrations', 'icon' => 'plug'],
            ],
        ],
    ],
];
