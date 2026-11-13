<?php

namespace Tests\Feature;

use App\Contracts\AdminSecondFactor;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\PaymentTransactionType;
use App\Enums\UserRole;
use App\Models\Agency;
use App\Models\Booking;
use App\Models\BookingPaymentTransaction;
use App\Models\Package;
use App\Models\PackageAvailability;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminFinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(
            AdminSecondFactor::class,
            Mockery::mock(AdminSecondFactor::class),
        );
    }

    public function test_finance_dashboard_uses_only_real_payment_transactions_for_dynamic_totals(): void
    {
        $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking();

        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-REAL-SUCCEEDED',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount' => '12500.00',
            'currency' => 'XAF',
            'processed_at' => now(),
        ]);
        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-REAL-FAILED',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::FAILED,
            'amount' => '500.00',
            'currency' => 'XAF',
            'failure_reason' => 'Internal provider detail should not be shown',
            'processed_at' => now(),
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'financial_overview',
            'lang' => 'en',
            'created_from' => today()->toDateString(),
            'created_to' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('12,500.00')
            ->assertSee('data-admin-finance', false)
            ->assertSee('data-finance-metric="bookings" data-finance-value="1"', false)
            ->assertSee('data-finance-metric="payment-attempts" data-finance-value="2"', false)
            ->assertSee('data-finance-metric="confirmed-transactions" data-finance-value="1"', false)
            ->assertSee(__('messages.admin_finance.pending_failed_summary'))
            ->assertDontSee('Stripe')
            ->assertDontSee('Orange Money')
            ->assertDontSee('Flutterwave')
            ->assertDontSee('XAF 8.4M')
            ->assertDontSee('Internal provider detail should not be shown');

        $this->get(route('admin.dashboard.page', [
            'page' => 'financial_reports',
            'lang' => 'en',
            'created_from' => today()->toDateString(),
            'created_to' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('12,500.00')
            ->assertSee(__('messages.admin_finance.report_title'));

        $this->get(route('admin.dashboard.page', [
            'page' => 'all_transactions',
            'lang' => 'en',
            'created_from' => today()->toDateString(),
            'created_to' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('PAY-REAL-SUCCEEDED')
            ->assertSee('PAY-REAL-FAILED')
            ->assertSee($booking->booking_reference)
            ->assertSee('500.00')
            ->assertSee('Existing Traveler')
            ->assertSee('Existing Travel Agency')
            ->assertDontSee('Internal provider detail should not be shown');
    }

    public function test_financial_overview_renders_a_separate_money_curve_for_each_currency(): void
    {
        $this->actingAsVerifiedAdmin();
        $xafBooking = $this->createBooking('BOOKING-CURVE-XAF');
        $usdBooking = $this->createBooking('BOOKING-CURVE-USD', 'USD');
        $gbpBooking = $this->createBooking('BOOKING-CURVE-GBP', 'GBP');

        foreach ([
            [
                'booking_id' => $xafBooking->id,
                'payment_reference' => 'PAY-CURVE-XAF-CHARGE',
                'type' => PaymentTransactionType::CHARGE,
                'amount' => '12500.00',
                'currency' => 'XAF',
            ],
            [
                'booking_id' => $xafBooking->id,
                'payment_reference' => 'PAY-CURVE-XAF-REFUND',
                'type' => PaymentTransactionType::REFUND,
                'amount' => '2000.00',
                'currency' => 'XAF',
            ],
            [
                'booking_id' => $usdBooking->id,
                'payment_reference' => 'PAY-CURVE-USD-CHARGE',
                'type' => PaymentTransactionType::CHARGE,
                'amount' => '50.00',
                'currency' => 'USD',
            ],
            [
                'booking_id' => $gbpBooking->id,
                'payment_reference' => 'PAY-CURVE-GBP-CHARGE',
                'type' => PaymentTransactionType::CHARGE,
                'amount' => '3000000.00',
                'currency' => 'GBP',
            ],
        ] as $transaction) {
            BookingPaymentTransaction::query()->create([
                ...$transaction,
                'provider' => 'agency-reported-offline',
                'payment_method' => 'offline',
                'status' => PaymentTransactionStatus::SUCCEEDED,
                'processed_at' => now(),
            ]);
        }

        $this->get(route('admin.dashboard.page', [
            'page' => 'financial_overview',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee('data-finance-chart-currency="XAF"', false)
            ->assertSee('data-finance-chart-currency="USD"', false)
            ->assertSee('data-finance-chart-currency="GBP"', false)
            ->assertSee('XAF 12,500.00')
            ->assertSee('XAF 2,000.00')
            ->assertSee('USD 50.00')
            ->assertSee('5M')
            ->assertSee('2.5M')
            ->assertSee('20K')
            ->assertSee('10K')
            ->assertSee(' C ', false);
    }

    public function test_financial_overview_shows_an_empty_state_instead_of_a_fake_curve_without_successful_transactions(): void
    {
        $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking('BOOKING-CURVE-NO-SUCCESS');

        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-CURVE-PENDING',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::PENDING,
            'amount' => '12500.00',
            'currency' => 'XAF',
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'financial_overview',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee(__('messages.admin_finance.no_successful_money_title'))
            ->assertDontSee('data-finance-chart-currency=', false);
    }

    public function test_successful_payments_filter_excludes_booking_completion_and_non_succeeded_transactions(): void
    {
        $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking();
        $completedWithoutPayment = $this->createBooking('BOOKING-COMPLETE-NO-PAYMENT');

        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-ONLY-SUCCESS',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount' => '12500.00',
            'currency' => 'XAF',
            'processed_at' => now(),
        ]);
        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-NOT-SUCCESS',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::PENDING,
            'amount' => '12500.00',
            'currency' => 'XAF',
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'successful_payments',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee('PAY-ONLY-SUCCESS')
            ->assertDontSee('PAY-NOT-SUCCESS')
            ->assertDontSee($completedWithoutPayment->booking_reference);
    }

    public function test_payment_disputes_page_does_not_misrepresent_booking_disputes_as_chargebacks(): void
    {
        $this->actingAsVerifiedAdmin();

        $this->get(route('admin.dashboard.page', [
            'page' => 'payment_disputes',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee(__('messages.admin_finance.payment_disputes_unavailable_title'))
            ->assertSee(__('messages.admin_finance.payment_disputes_unavailable_description'));
    }

    public function test_refunds_and_pending_failed_pages_filter_only_matching_stored_transactions(): void
    {
        $this->actingAsVerifiedAdmin();
        $booking = $this->createBooking();

        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-REAL-REFUND',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::REFUND,
            'status' => PaymentTransactionStatus::SUCCEEDED,
            'amount' => '2000.00',
            'currency' => 'XAF',
            'processed_at' => now(),
        ]);
        BookingPaymentTransaction::query()->create([
            'booking_id' => $booking->id,
            'payment_reference' => 'PAY-REAL-PENDING',
            'provider' => 'agency-reported-offline',
            'payment_method' => 'offline',
            'type' => PaymentTransactionType::CHARGE,
            'status' => PaymentTransactionStatus::PENDING,
            'amount' => '500.00',
            'currency' => 'XAF',
        ]);

        $this->get(route('admin.dashboard.page', [
            'page' => 'refunds',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee('PAY-REAL-REFUND')
            ->assertDontSee('PAY-REAL-PENDING')
            ->assertSee(__('messages.admin_finance.original_payment_not_linked'));

        $this->get(route('admin.dashboard.page', [
            'page' => 'pending_failed_payments',
            'lang' => 'en',
        ]))
            ->assertOk()
            ->assertSee('PAY-REAL-PENDING')
            ->assertDontSee('PAY-REAL-REFUND');
    }

    public function test_finance_date_range_rejects_an_end_date_before_the_start_date(): void
    {
        $this->actingAsVerifiedAdmin();

        $this->get(route('admin.dashboard.page', [
            'page' => 'financial_reports',
            'lang' => 'en',
            'created_from' => '2026-11-09',
            'created_to' => '2026-11-08',
        ]))
            ->assertSessionHasErrors('created_to');
    }

    private function actingAsVerifiedAdmin(): void
    {
        $admin = User::factory()->withRole(UserRole::ADMIN)->create();
        $this->actingAs($admin)->withSession([
            'admin_login.verified_user_id' => $admin->id,
            'admin_login.session_version' => $admin->admin_session_version,
            'admin_login.verified_at' => now()->timestamp,
            'admin_login.last_activity' => now()->timestamp,
        ]);
    }

    private function createBooking(string $reference = 'BOOKING-EXISTING-001', string $currency = 'XAF'): Booking
    {
        $agency = Agency::factory()->create(['company_name' => 'Existing Travel Agency']);
        $package = Package::factory()->for($agency)->create();
        $availability = PackageAvailability::query()->create([
            'package_id' => $package->id,
            'departure_date' => today()->subDay()->toDateString(),
            'capacity' => 8,
            'status' => 'active',
        ]);
        $traveler = User::factory()->create([
            'name' => 'Existing Traveler',
            'role' => UserRole::TRAVELER,
        ]);

        return Booking::query()->create([
            'agency_id' => $agency->id,
            'package_id' => $package->id,
            'package_availability_id' => $availability->id,
            'user_id' => $traveler->id,
            'booking_reference' => $reference,
            'status' => BookingStatus::COMPLETED,
            'payment_status' => PaymentStatus::UNPAID,
            'traveler_count' => 1,
            'unit_price' => '12500.00',
            'total_amount' => '12500.00',
            'currency' => $currency,
            'completed_at' => now(),
        ]);
    }
}
