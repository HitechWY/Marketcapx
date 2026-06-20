<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Auth')->group(function () {
    Route::middleware('admin.guest')->group(function () {
        Route::controller('LoginController')->group(function () {
            Route::get('/', 'showLoginForm')->name('login');
            Route::post('/', 'login')->middleware('throttle:login')->name('login');
            Route::get('logout', 'logout')->middleware('admin')->withoutMiddleware('admin.guest')->name('logout');
        });

        // Admin Password Reset
        Route::controller('ForgotPasswordController')->prefix('password')->name('password.')->middleware('throttle:password-reset')->group(function () {
            Route::get('reset', 'showLinkRequestForm')->name('reset');
            Route::post('reset', 'sendResetCodeEmail');
            Route::get('code-verify', 'codeVerify')->name('code.verify');
            Route::post('verify-code', 'verifyCode')->name('verify.code');
        });

        Route::controller('ResetPasswordController')->group(function () {
            Route::get('password/reset/{token}', 'showResetForm')->name('password.reset.form');
            Route::post('password/reset/change', 'reset')->name('password.change');
        });
    });
});

Route::middleware('admin')->group(function () {
    Route::controller('AdminController')->group(function () {
        Route::get('dashboard', 'dashboard')->name('dashboard');
        Route::get('transaction-detail-graph', 'trxDetailGraph')->name('trx.detail.graph');
        Route::get('chart/deposit-withdraw', 'depositAndWithdrawReport')->name('chart.deposit.withdraw');
        Route::get('chart/transaction', 'transactionReport')->name('chart.transaction');
        Route::get('chart/charge-commission', 'chargeCommissionReport')->name('chart.charge.commission');
        Route::get('chart/registration', 'registrationReport')->name('chart.registration');
        Route::get('profile', 'profile')->name('profile');
        Route::post('profile', 'profileUpdate')->name('profile.update');
        Route::get('password', 'password')->name('password');
        Route::post('password', 'passwordUpdate')->name('password.update');

        //Notification
        Route::get('notifications', 'notifications')->name('notifications');
        Route::get('notification/read/{id}', 'notificationRead')->name('notification.read');
        Route::get('notifications/read-all', 'readAllNotification')->name('notifications.read.all');
        Route::post('notifications/delete-all', 'deleteAllNotification')->name('notifications.delete.all');
        Route::post('notifications/delete-single/{id}', 'deleteSingleNotification')->name('notifications.delete.single');

        //Report Bugs
        Route::get('request-report', 'requestReport')->name('request.report');
        Route::post('request-report', 'reportSubmit');

        Route::get('download-attachments/{file_hash}', 'downloadAttachment')->name('download.attachment');
    });

    // Manage Currencies
    Route::controller('CurrencyController')->name('currency.')->prefix('currency')->group(function () {
        Route::get('/', 'allCurrency')->name('all');
        Route::post('add', 'add')->name('add')->middleware('permission:admin.currency.manage');
        Route::post('update', 'update')->name('update')->middleware('permission:admin.currency.manage');
        Route::post('api-key/update', 'updateApiKey')->name('api.update')->middleware('permission:admin.currency.manage');
    });

    //Manage Transfer Charge
    Route::controller('TransactionChargeController')->name('transaction')->prefix('transaction')->group(function () {
        Route::get('/charges', 'manageCharges')->name('.charges');
        Route::post('/charges/update', 'updateCharges')->name('.charges.update')->middleware('permission:admin.charges.update');
    });

    // Shared user/agent/merchant management routes. $guard scopes the permission
    // slugs (RBAC F9e) so a role can manage one account type but not another.
    $manageGuardRoutes = function ($guard) {
        Route::get('/', 'allUsers')->name('all');
        Route::get('active', 'activeUsers')->name('active');
        Route::get('banned', 'bannedUsers')->name('banned');
        Route::get('email-verified', 'emailVerifiedUsers')->name('email.verified');
        Route::get('email-unverified', 'emailUnverifiedUsers')->name('email.unverified');
        Route::get('mobile-unverified', 'mobileUnverifiedUsers')->name('mobile.unverified');
        Route::get('kyc-unverified', 'kycUnverifiedUsers')->name('kyc.unverified');
        Route::get('kyc-pending', 'kycPendingUsers')->name('kyc.pending');
        Route::get('mobile-verified', 'mobileVerifiedUsers')->name('mobile.verified');
        Route::get('with-balance', 'usersWithBalance')->name('with.balance');

        Route::get('detail/{id}', 'detail')->name('detail');
        Route::get('kyc-data/{id}', 'kycDetails')->name('kyc.details')->middleware("permission:admin.$guard.kyc.review");
        Route::post('kyc-approve/{id}', 'kycApprove')->name('kyc.approve')->middleware("permission:admin.$guard.kyc.approve");
        Route::post('kyc-reject/{id}', 'kycReject')->name('kyc.reject')->middleware("permission:admin.$guard.kyc.reject");
        Route::post('update/{id}', 'update')->name('update')->middleware("permission:admin.$guard.update");
        Route::post('add-sub-balance/{id}', 'addSubBalance')->name('add.sub.balance')->middleware("permission:admin.$guard.balance.adjust");
        Route::get('send-notification/{id}', 'showNotificationSingleForm')->name('notification.single');
        Route::post('send-notification/{id}', 'sendNotificationSingle')->name('notification.single')->middleware("permission:admin.$guard.notify");
        Route::get('login/{id}', 'login')->name('login')->middleware("permission:admin.$guard.login");
        Route::post('status/{id}', 'status')->name('status')->middleware("permission:admin.$guard.status.update");

        Route::get('send-notification', 'showNotificationAllForm')->name('notification.all');
        Route::post('send-notification', 'sendNotificationAll')->name('notification.all.send')->middleware("permission:admin.$guard.notify");
        Route::get('list', 'list')->name('list');
        Route::get('count-by-segment/{methodName}', 'countBySegment')->name('segment.count');
        Route::get('notification-log/{id}', 'notificationLog')->name('notification.log');
    };

    // Users Manager
    Route::group(['controller' => 'ManageGuardController', 'as' => 'users.', 'prefix' => 'users', 'guardType' => 'users'], fn () => $manageGuardRoutes('users'));
    // Agent Manager
    Route::group(['controller' => 'ManageGuardController', 'as' => 'agents.', 'prefix' => 'agents', 'guardType' => 'agents'], fn () => $manageGuardRoutes('agents'));
    // Merchant Manager
    Route::group(['controller' => 'ManageGuardController', 'as' => 'merchants.', 'prefix' => 'merchants', 'guardType' => 'merchants'], fn () => $manageGuardRoutes('merchants'));

    //Revenue Log
    Route::controller('ProfitController')->name('profit.')->prefix('profit')->group(function () {
        Route::get('/all', 'allProfit')->name('all');
        Route::get('/only', 'onlyProfit')->name('only');
        Route::get('/commission', 'profitCommission')->name('commission');
        Route::get('/export-csv', 'profitExportCsv')->name('export.csv');
        Route::get('/search', 'profitSearch')->name('search');
    });

    //Manage Category
    Route::controller('CategoryController')->prefix('category')->name('category.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('store/{id?}', 'store')->name('store')->middleware('permission:admin.category.manage');
        Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.category.manage');
    });

    //Escrow Manage

    Route::controller('EscrowController')->name('escrow.')->prefix('escrow')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('accepted', 'accepted')->name('accepted');
        Route::get('not-accepted', 'notAccepted')->name('not.accepted');
        Route::get('completed', 'completed')->name('completed');
        Route::get('disputed', 'disputed')->name('disputed');
        Route::get('canceled', 'canceled')->name('canceled');

        Route::get('details/{id}', 'details')->name('details');
        Route::get('milestone/{id}', 'milestone')->name('milestone');
        Route::post('message', 'replyMessage')->name('message.reply');
        Route::get('message-get', 'getMessage')->name('message.get');
        Route::post('action', 'action')->name('action')->middleware('permission:admin.escrow.action');
    });


    // Subscriber
    Route::controller('SubscriberController')->prefix('subscriber')->name('subscriber.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('send-email', 'sendEmailForm')->name('send.email');
        Route::post('remove/{id}', 'remove')->name('remove')->middleware('permission:admin.subscriber.manage');
        Route::post('send-email', 'sendEmail')->name('send.email')->middleware('permission:admin.subscriber.manage');
    });

    // Deposit Gateway
    Route::name('gateway.')->prefix('gateway')->group(function () {
        // Automatic Gateway
        Route::controller('AutomaticGatewayController')->prefix('automatic')->name('automatic.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{code}', 'update')->name('update')->middleware('permission:admin.gateway.update');
            Route::post('remove/{id}', 'remove')->name('remove')->middleware('permission:admin.gateway.update');
            Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.gateway.status');
        });

        // Manual Methods
        Route::controller('ManualGatewayController')->prefix('manual')->name('manual.')->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('new', 'create')->name('create');
            Route::post('new', 'store')->name('store')->middleware('permission:admin.gateway.update');
            Route::get('edit/{alias}', 'edit')->name('edit');
            Route::post('update/{id}', 'update')->name('update')->middleware('permission:admin.gateway.update');
            Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.gateway.status');
        });
    });

    // DEPOSIT SYSTEM
    Route::controller('DepositController')->prefix('deposit')->name('deposit.')->group(function () {
        Route::get('all/{user_id?}', 'deposit')->name('list');
        Route::get('pending/{user_id?}', 'pending')->name('pending');
        Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
        Route::get('approved/{user_id?}', 'approved')->name('approved');
        Route::get('successful/{user_id?}', 'successful')->name('successful');
        Route::get('initiated/{user_id?}', 'initiated')->name('initiated');
        Route::get('details/{id}', 'details')->name('details');
        Route::post('reject', 'reject')->name('reject')->middleware('permission:admin.deposits.reject');
        Route::post('approve/{id}', 'approve')->name('approve')->middleware('permission:admin.deposits.approve');
    });

    // WITHDRAW SYSTEM
    Route::name('withdraw.')->prefix('withdraw')->group(function () {

        Route::controller('WithdrawalController')->name('data.')->group(function () {
            Route::get('pending/{user_id?}', 'pending')->name('pending');
            Route::get('approved/{user_id?}', 'approved')->name('approved');
            Route::get('rejected/{user_id?}', 'rejected')->name('rejected');
            Route::get('all/{user_id?}', 'all')->name('all');
            Route::get('details/{id}', 'details')->name('details');
            Route::post('approve', 'approve')->name('approve')->middleware('permission:admin.withdrawals.approve');
            Route::post('reject', 'reject')->name('reject')->middleware('permission:admin.withdrawals.reject');
        });

        // Withdraw Method
        Route::controller('WithdrawMethodController')->prefix('method')->name('method.')->group(function () {
            Route::get('/', 'methods')->name('index');
            Route::get('create', 'create')->name('create');
            Route::post('create', 'store')->name('store')->middleware('permission:admin.withdrawals.methods.manage');
            Route::get('edit/{id}', 'edit')->name('edit');
            Route::post('edit/{id}', 'update')->name('update')->middleware('permission:admin.withdrawals.methods.manage');
            Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.withdrawals.methods.manage');
        });
    });

    // Report
    Route::controller('ReportController')->prefix('report')->name('report.')->group(function () {
        Route::get('transaction/{user_id?}', 'transaction')->name('transaction');
        Route::get('login/history', 'loginHistory')->name('login.history');
        Route::get('login/ip-history/{ip}', 'loginIpHistory')->name('login.ip.history');
        Route::get('notification/history', 'notificationHistory')->name('notification.history');
        Route::get('email/detail/{id}', 'emailDetails')->name('email.details');
    });

    // Admin Support
    Route::controller('SupportTicketController')->prefix('ticket')->name('ticket.')->group(function () {
        Route::get('/', 'tickets')->name('index');
        Route::get('pending', 'pendingTicket')->name('pending');
        Route::get('closed', 'closedTicket')->name('closed');
        Route::get('answered', 'answeredTicket')->name('answered');
        Route::get('view/{id}', 'ticketReply')->name('view');
        Route::post('reply/{id}', 'replyTicket')->name('reply')->middleware('permission:admin.support.reply');
        Route::post('close/{id}', 'closeTicket')->name('close')->middleware('permission:admin.support.close');
        Route::get('download/{attachment_id}', 'ticketDownload')->name('download');
        Route::post('delete/{id}', 'ticketDelete')->name('delete')->middleware('permission:admin.support.delete');
    });

    // Language Manager
    Route::controller('LanguageController')->prefix('language')->name('language.')->group(function () {
        Route::get('/', 'langManage')->name('manage');
        Route::post('/', 'langStore')->name('manage.store')->middleware('permission:admin.language.manage');
        Route::post('delete/{id}', 'langDelete')->name('manage.delete')->middleware('permission:admin.language.manage');
        Route::post('update/{id}', 'langUpdate')->name('manage.update')->middleware('permission:admin.language.manage');
        Route::get('edit/{id}', 'langEdit')->name('key');
        Route::post('import', 'langImport')->name('import.lang')->middleware('permission:admin.language.manage');
        Route::post('store/key/{id}', 'storeLanguageJson')->name('store.key')->middleware('permission:admin.language.manage');
        Route::post('delete/key/{id}', 'deleteLanguageJson')->name('delete.key')->middleware('permission:admin.language.manage');
        Route::post('update/key/{id}', 'updateLanguageJson')->name('update.key')->middleware('permission:admin.language.manage');
        Route::get('get-keys', 'getKeys')->name('get.key');
    });

    Route::controller('GeneralSettingController')->group(function () {

        Route::get('system-setting', 'systemSetting')->name('setting.system');

        // General Setting
        Route::get('general-setting', 'general')->name('setting.general');
        Route::post('general-setting', 'generalUpdate')->middleware('permission:admin.settings.update');

        Route::get('setting/qr-code/template', 'qrCodeTemplate')->name('setting.qr.code.template');
        Route::post('setting/qr-code/template', 'qrCodeTemplateUpdate')->middleware('permission:admin.settings.update');

        Route::get('setting/social/credentials', 'socialiteCredentials')->name('setting.socialite.credentials');
        Route::post('setting/social/credentials/update/{key?}', 'updateSocialiteCredential')->name('setting.socialite.credentials.update')->middleware('permission:admin.settings.socialite.update');
        Route::post('setting/social/credentials/status/{key?}', 'updateSocialiteCredentialStatus')->name('setting.socialite.credentials.status.update')->middleware('permission:admin.settings.socialite.update');

        //configuration
        Route::get('setting/system-configuration', 'systemConfiguration')->name('setting.system.configuration');
        Route::post('setting/system-configuration', 'systemConfigurationSubmit')->middleware('permission:admin.settings.update');

        // Logo-Icon
        Route::get('setting/logo-icon', 'logoIcon')->name('setting.logo.icon');
        Route::post('setting/logo-icon', 'logoIconUpdate')->name('setting.logo.icon')->middleware('permission:admin.settings.update');

        //Custom CSS
        Route::get('custom-css', 'customCss')->name('setting.custom.css');
        Route::post('custom-css', 'customCssSubmit')->middleware('permission:admin.settings.update');

        Route::get('sitemap', 'sitemap')->name('setting.sitemap');
        Route::post('sitemap', 'sitemapSubmit')->middleware('permission:admin.settings.update');

        Route::get('robot', 'robot')->name('setting.robot');
        Route::post('robot', 'robotSubmit')->middleware('permission:admin.settings.update');

        //Cookie
        Route::get('cookie', 'cookie')->name('setting.cookie');
        Route::post('cookie', 'cookieSubmit')->middleware('permission:admin.settings.update');

        //maintenance_mode
        Route::get('maintenance-mode', 'maintenanceMode')->name('maintenance.mode');
        Route::post('maintenance-mode', 'maintenanceModeSubmit')->middleware('permission:admin.settings.update');
    });

    Route::controller('CronConfigurationController')->name('cron.')->prefix('cron')->group(function () {
        Route::get('index', 'cronJobs')->name('index');
        Route::post('store', 'cronJobStore')->name('store')->middleware('permission:admin.system.cron.manage');
        Route::post('update', 'cronJobUpdate')->name('update')->middleware('permission:admin.system.cron.manage');
        Route::post('delete/{id}', 'cronJobDelete')->name('delete')->middleware('permission:admin.system.cron.manage');
        Route::get('schedule', 'schedule')->name('schedule');
        Route::post('schedule/store', 'scheduleStore')->name('schedule.store')->middleware('permission:admin.system.cron.manage');
        Route::post('schedule/status/{id}', 'scheduleStatus')->name('schedule.status')->middleware('permission:admin.system.cron.manage');
        Route::get('schedule/pause/{id}', 'schedulePause')->name('schedule.pause')->middleware('permission:admin.system.cron.manage');
        Route::get('schedule/logs/{id}', 'scheduleLogs')->name('schedule.logs');
        Route::post('schedule/log/resolved/{id}', 'scheduleLogResolved')->name('schedule.log.resolved')->middleware('permission:admin.system.cron.manage');
        Route::post('schedule/log/flush/{id}', 'logFlush')->name('log.flush')->middleware('permission:admin.system.cron.manage');
    });

    //Module setting
    Route::controller('ModuleSettingController')->group(function () {
        Route::get('module-setting', 'index')->name('module.setting');
        Route::post('module-setting/update', 'update')->name('module.update')->middleware('permission:admin.module.update');
    });

    //KYC setting
    Route::controller('KycController')->group(function () {
        Route::get('kyc-setting/{kycType?}', 'setting')->name('kyc.setting');
        Route::post('kyc-setting', 'settingUpdate')->name('kyc.setting.update')->middleware('permission:admin.kyc.settings.update');
    });

    //Notification Setting
    Route::name('setting.notification.')->controller('NotificationController')->prefix('notification')->group(function () {
        //Template Setting
        Route::get('global/email', 'globalEmail')->name('global.email');
        Route::post('global/email/update', 'globalEmailUpdate')->name('global.email.update')->middleware('permission:admin.settings.notifications.update');

        Route::get('global/sms', 'globalSms')->name('global.sms');
        Route::post('global/sms/update', 'globalSmsUpdate')->name('global.sms.update')->middleware('permission:admin.settings.notifications.update');

        Route::get('global/push', 'globalPush')->name('global.push');
        Route::post('global/push/update', 'globalPushUpdate')->name('global.push.update')->middleware('permission:admin.settings.notifications.update');

        Route::get('templates', 'templates')->name('templates');
        Route::get('template/edit/{type}/{id}', 'templateEdit')->name('template.edit');
        Route::post('template/update/{type}/{id}', 'templateUpdate')->name('template.update')->middleware('permission:admin.settings.notifications.update');

        //Email Setting
        Route::get('email/setting', 'emailSetting')->name('email');
        Route::post('email/setting', 'emailSettingUpdate')->middleware('permission:admin.settings.notifications.update');
        Route::post('email/test', 'emailTest')->name('email.test')->middleware('permission:admin.settings.notifications.update');

        //SMS Setting
        Route::get('sms/setting', 'smsSetting')->name('sms');
        Route::post('sms/setting', 'smsSettingUpdate')->middleware('permission:admin.settings.notifications.update');
        Route::post('sms/test', 'smsTest')->name('sms.test')->middleware('permission:admin.settings.notifications.update');

        Route::get('notification/push/setting', 'pushSetting')->name('push');
        Route::post('notification/push/setting', 'pushSettingUpdate')->middleware('permission:admin.settings.notifications.update');
        Route::post('notification/push/setting/upload', 'pushSettingUpload')->name('push.upload')->middleware('permission:admin.settings.notifications.update');
        Route::get('notification/push/setting/download', 'pushSettingDownload')->name('push.download');
    });

    // Plugin
    Route::controller('ExtensionController')->prefix('extensions')->name('extensions.')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('update/{id}', 'update')->name('update')->middleware('permission:admin.system.extensions.manage');
        Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.system.extensions.manage');
    });

    //System Information
    Route::controller('SystemController')->name('system.')->prefix('system')->group(function () {
        Route::get('info', 'systemInfo')->name('info');
        Route::get('server-info', 'systemServerInfo')->name('server.info');
        Route::get('optimize', 'optimize')->name('optimize')->middleware('permission:admin.system.optimize');
        Route::get('optimize-clear', 'optimizeClear')->name('optimize.clear')->middleware('permission:admin.system.optimize');
        Route::get('system-update', 'systemUpdate')->name('update');
        Route::post('system-update', 'systemUpdateProcess')->name('update.process')->middleware('permission:admin.system.update');
        Route::get('system-update/log', 'systemUpdateLog')->name('update.log');
    });

    // SEO
    Route::get('seo', 'FrontendController@seoEdit')->name('seo');

    // Frontend
    Route::name('frontend.')->prefix('frontend')->group(function () {

        Route::controller('FrontendController')->group(function () {
            Route::get('index', 'index')->name('index');
            Route::get('templates', 'templates')->name('templates');
            Route::post('templates', 'templatesActive')->name('templates.active')->middleware('permission:admin.content.frontend.manage');
            Route::get('frontend-sections/{key?}', 'frontendSections')->name('sections');
            Route::post('frontend-content/{key}', 'frontendContent')->name('sections.content')->middleware('permission:admin.content.frontend.manage');
            Route::get('frontend-element/{key}/{id?}', 'frontendElement')->name('sections.element');
            Route::get('frontend-slug-check/{key}/{id?}', 'frontendElementSlugCheck')->name('sections.element.slug.check');
            Route::get('frontend-element-seo/{key}/{id}', 'frontendSeo')->name('sections.element.seo');
            Route::post('frontend-element-seo/{key}/{id}', 'frontendSeoUpdate')->middleware('permission:admin.content.frontend.manage');
            Route::post('update-seo', 'updateSeoContent')->name('seo.update')->middleware('permission:admin.content.frontend.manage');
            Route::post('remove/{id}', 'remove')->name('remove')->middleware('permission:admin.content.frontend.manage');
        });

        // Page Builder
        Route::controller('PageBuilderController')->group(function () {
            Route::get('manage-pages', 'managePages')->name('manage.pages');
            Route::get('manage-pages/check-slug/{id?}', 'checkSlug')->name('manage.pages.check.slug');
            Route::post('manage-pages', 'managePagesSave')->name('manage.pages.save')->middleware('permission:admin.content.pages.manage');
            Route::post('manage-pages/update', 'managePagesUpdate')->name('manage.pages.update')->middleware('permission:admin.content.pages.manage');
            Route::post('manage-pages/delete/{id}', 'managePagesDelete')->name('manage.pages.delete')->middleware('permission:admin.content.pages.manage');
            Route::get('manage-section/{id}', 'manageSection')->name('manage.section');
            Route::post('manage-section/{id}', 'manageSectionUpdate')->name('manage.section.update')->middleware('permission:admin.content.pages.manage');

            Route::get('manage-seo/{id}', 'manageSeo')->name('manage.pages.seo');
            Route::post('manage-seo/{id}', 'manageSeoStore')->middleware('permission:admin.content.pages.manage');
        });
    });

    //Plugins
    Route::name('plugins.')->prefix('plugins')->controller('PluginController')->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/upload', 'upload')->name('upload')->middleware('permission:admin.system.plugins.manage');
        Route::post('/update/{id}', 'update')->name('update')->middleware('permission:admin.system.plugins.manage');
        Route::post('status/{id}', 'status')->name('status')->middleware('permission:admin.system.plugins.manage');
    });
});
