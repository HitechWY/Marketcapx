<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// F0 (Inertia/Vue foundation): isolated proof-of-concept route. Guest-accessible,
// no DB access, no business logic. Renders resources/js/Pages/Poc/Hello.vue to prove
// the new stack mounts (Vue + TS + Tailwind + MD3 tokens + Inertia props + Vite
// manifest). Two-segment path, so it is never shadowed by the '/{slug}' catch-all
// below. To be removed at the final cutover (F8).
Route::get('poc/inertia', function () {
    return Inertia::render('Poc/Hello', [
        'message'        => 'Inertia + Vue 3 foundation is live.',
        'framework'      => 'MarketCap X · F0',
        'laravelVersion' => Application::VERSION,
    ]);
})->name('poc.inertia');

// F1a (public Inertia PoC): isolated public preview of the new Vue/Tailwind public
// surface (PublicLayout + header/footer + hero). Guest-accessible, NO DB access, NO
// business logic — props are config()/static only (real read-only CMS data is wired
// in F1b). Does NOT replace '/' or any live page. To be removed at the final cutover.
Route::get('poc/public-home', function () {
    return Inertia::render('Public/HomePreview', [
        'brand'   => config('app.name', 'MarketCap X'),
        'tagline' => 'Send, receive, and manage digital payments — fast, secure, and global.',
        'features' => [
            ['icon' => '⚡', 'title' => 'Instant transfers', 'body' => 'Move money between wallets in seconds with transparent, upfront fees.'],
            ['icon' => '🛡', 'title' => 'Bank-grade security', 'body' => 'Multi-guard auth, 2FA, and KYC keep every account and transaction protected.'],
            ['icon' => '🌍', 'title' => 'Global gateways', 'body' => 'Deposit and withdraw through a wide range of trusted payment providers.'],
        ],
        'stats' => [
            ['value' => '32+', 'label' => 'Payment gateways'],
            ['value' => '99.9%', 'label' => 'Uptime target'],
            ['value' => '24/7', 'label' => 'Support'],
        ],
    ]);
})->name('poc.public-home');

// SECURITY (Phase 1): removed public unauthenticated GET /clear route that ran
// Artisan optimize:clear with no auth (cache-wipe / DoS vector). Use artisan CLI instead.

// SECURITY (Phase 1B): /cron guarded by a configurable shared-secret token
// (App\Http\Middleware\VerifyCronToken). Enforced when CRON_SECRET is set;
// backward-compatible (open + logged) until then.
Route::get('cron', 'CronController@cron')->middleware(\App\Http\Middleware\VerifyCronToken::class)->name('cron');

// User Support Ticket
Route::controller('TicketController')->prefix('ticket')->name('ticket.')->group(function () {
    Route::get('/', 'supportTicket')->name('index');
    Route::get('new', 'openSupportTicket')->name('open');
    Route::post('create', 'storeSupportTicket')->name('store');
    Route::get('view/{ticket}', 'viewTicket')->name('view');
    Route::post('reply/{id}', 'replyTicket')->name('reply');
    Route::post('close/{id}', 'closeTicket')->name('close');
    Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
});

Route::controller('OtpController')->middleware('throttle:otp')->group(function () {
    Route::get('otp-verification', 'otpVerification')->name('verify.otp');
    Route::get('otp-resend', 'otpResend')->name('verify.otp.resend');
    Route::post('otp-verify', 'otpVerify')->name('verify.otp.submit');
});

Route::get('app/deposit/confirm/{hash}', 'Gateway\PaymentController@appDepositConfirm')->name('deposit.app.confirm');

Route::controller('SiteController')->group(function () {
    Route::get('/session/status', 'sessionStatus')->name('session.status');
    Route::get('/login', 'login')->name('login'); 

    Route::get('/invoice/payment/{invoiceNum}', 'invoicePayment')->name('invoice.payment');
    Route::get('/api/documentation', 'apiDocumentation')->name('api.documentation');


    Route::post('subscribe', 'subscribe')->name('subscribe');

    
    Route::get('/contact', 'contact')->name('contact');
    Route::post('/contact', 'contactSubmit');
    Route::get('/change/{lang?}', 'changeLanguage')->name('lang');

    Route::get('cookie-policy', 'cookiePolicy')->name('cookie.policy');

    Route::get('/cookie/accept', 'cookieAccept')->name('cookie.accept');

    Route::get('/announces', 'blogs')->name('blogs');
    Route::get('announce/{slug}', 'blogDetails')->name('blog.details');

    Route::get('policy/{slug}', 'policyPages')->name('policy.pages');

    Route::get('placeholder-image/{size}', 'placeholderImage')->withoutMiddleware('maintenance')->name('placeholder.image');
    Route::get('maintenance-mode','maintenance')->withoutMiddleware('maintenance')->name('maintenance');

    Route::get('qr/scan/{uniqueCode}','SiteController@qrScan')->name('qr.scan');

    Route::get('/{slug}', 'pages')->name('pages');
    Route::get('/', 'index')->name('home');
});


//Live payment
Route::controller('GetPaymentController')->group(function () {
    Route::match(['get','post'],'/payment/initiate', 'initiatePayment')->name('initiate.payment');
    Route::get('initiate/payment/checkout', 'initiatePaymentAuthView')->name('initiate.payment.auth.view');
    Route::post('initiate/payment/check-mail', 'checkEmail')->name('payment.check.email');
    Route::get('verify/payment', 'verifyPayment')->name('payment.verify');
    Route::post('confirm/payment', 'verifyPaymentConfirm')->name('confirm.payment');
    Route::get('resend/verify/code', 'sendVerifyCode')->name('resend.code');
    Route::get('cancel/payment', 'cancelPayment')->name('cancel.payment');
});

//Test payment
Route::controller('TestPaymentController')->prefix('sandbox')->name('test.')->group(function () {
    Route::match(['get','post'],'/payment/initiate', 'initiatePayment')->name('initiate.payment');
    Route::get('initiate/payment/checkout', 'initiatePaymentAuthView')->name('initiate.payment.auth.view');
    Route::post('initiate/payment/check-mail', 'checkEmail')->name('payment.check.email');
    Route::get('verify/payment', 'verifyPayment')->name('payment.verify');
    Route::post('confirm/payment', 'verifyPaymentConfirm')->name('confirm.payment');
    Route::get('cancel/payment', 'cancelPayment')->name('cancel.payment');
});
