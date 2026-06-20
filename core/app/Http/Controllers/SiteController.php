<?php

namespace App\Http\Controllers;

use App\Constants\Status;
use App\Models\AdminNotification;
use App\Models\Agent;
use App\Models\Currency;
use App\Models\Frontend;
use App\Models\Invoice;
use App\Models\Language;
use App\Models\Merchant;
use App\Models\Page;
use App\Models\Plugin;
use App\Models\QRcode;
use App\Models\Subscriber;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Validator;
use App\Models\UserWithdrawMethod;

class SiteController extends Controller
{
    public function index()
    {
        $pageTitle   = 'Home';
        $sections    = Page::where('tempname', activeTemplate())->where('slug', '/')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;

        // F1b: the public homepage is rendered via Inertia/Vue (Pages/Public/Home.vue)
        // using the SAME backend CMS data (read-only). The Blade view 'Template::home'
        // is intentionally retained, untouched, as an instant fallback.
        //   ROLLBACK (one line): replace the return below with —
        //   return view('Template::home', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
        return inertia('Public/Home', [
            'seo'  => [
                'title'       => $pageTitle,
                'description' => @$seoContents->description,
                'keywords'    => @$seoContents->keywords,
                'image'       => $seoImage,
            ],
            'home' => $this->homePageData($sections),
        ]);
    }

    /**
     * Build the Inertia homepage props from the existing CMS content (read-only).
     * Mirrors the Blade banner + section partials' getContent()/frontendImage()/__()
     * calls exactly — no new queries, no DB writes, no business logic.
     */
    protected function homePageData($page): array
    {
        $banner = @getContent('banner.content', true)?->data_values;

        $data = [
            'banner'   => $banner ? [
                'title'             => __(@$banner->title),
                'heading'           => __(str_replace('&amp;', '&', @$banner->heading)),
                'subheading'        => __(@$banner->subheading),
                'button_name'       => __(@$banner->button_name),
                'button_link'       => @$banner->button_link,
                'video_button_name' => __(@$banner->video_button_name),
                'video_link'        => @$banner->video_link,
                'background_image'  => frontendImage('banner', @$banner->background_image, '1920x1280'),
            ] : null,
            'sections' => [],
        ];

        $secs = ($page && $page->secs) ? json_decode($page->secs) : [];
        foreach ($secs as $sec) {
            $built = $this->homeSection($sec);
            if ($built) {
                $data['sections'][] = $built;
            }
        }

        return $data;
    }

    /**
     * Map a single home section key to { key, content, elements } using the same
     * content keys, image sizes and __() wrapping the matching Blade partial uses.
     */
    protected function homeSection(string $key): ?array
    {
        $content  = @getContent($key . '.content', true)?->data_values;
        $cText    = fn ($v) => $v !== null ? __($v) : null;

        switch ($key) {
            case 'brands':
                $els = @getContent('brands.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => ['heading' => $cText(@$content->heading)],
                    'elements' => $els->map(fn ($e) => [
                        'image' => frontendImage('brands', @$e->data_values->image, '60x32'),
                    ])->values()->all(),
                ];

            case 'about':
                $els = @getContent('about.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'title'           => $cText(@$content->title),
                        'heading'         => $cText(@$content->heading),
                        'short_details'   => $cText(@$content->short_details),
                        'button_name'     => $cText(@$content->button_name),
                        'button_link'     => @$content->button_link,
                        'experience_text' => $cText(@$content->experience_text),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'award_logo' => frontendImage('about', @$e->data_values->award_logo, '100x100'),
                    ])->values()->all(),
                ];

            case 'service':
                $els = @getContent('service.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'title'        => $cText(@$e->data_values->title),
                        'service_icon' => @$e->data_values->service_icon, // raw admin HTML/SVG
                        'description'  => $cText(@$e->data_values->description),
                    ])->values()->all(),
                ];

            case 'why_choose_us':
                $els = @getContent('why_choose_us.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'title'         => $cText(@$e->data_values->title),
                        'short_details' => $cText(@$e->data_values->short_details),
                        'icon'          => frontendImage('why_choose_us', @$e->data_values->icon, '65x65'),
                    ])->values()->all(),
                ];

            case 'feature':
                $els = @getContent('feature.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'title'         => $cText(@$e->data_values->title),
                        'short_details' => $cText(@$e->data_values->short_details),
                        'icon'          => frontendImage('feature', @$e->data_values->icon, '65x65'),
                    ])->values()->all(),
                ];

            case 'business':
                $els = @getContent('business.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'title'      => $cText(@$content->title),
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'client_logo' => frontendImage('business', @$e->data_values->client_logo, '140x60'),
                    ])->values()->all(),
                ];

            case 'overview':
                $els = @getContent('overview.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'counter_digit' => $cText(@$e->data_values->counter_digit),
                        'title'         => $cText(@$e->data_values->title),
                    ])->values()->all(),
                ];

            case 'testimonial':
                $els = @getContent('testimonial.element', orderById: true) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'title'      => $cText(@$content->title),
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'author_name'  => $cText(@$e->data_values->author_name),
                        'designation'  => $cText(@$e->data_values->designation),
                        'quote'        => $cText(@$e->data_values->quote),
                        'author_image' => frontendImage('testimonial', @$e->data_values->author_image, '1080x620'),
                    ])->values()->all(),
                ];

            case 'subscribe':
                return [
                    'key'      => $key,
                    'content'  => [
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => [],
                ];

            case 'blog':
                $els = @getContent('blog.element', false, 3) ?: collect();
                return [
                    'key'      => $key,
                    'content'  => [
                        'title'      => $cText(@$content->title),
                        'heading'    => $cText(@$content->heading),
                        'subheading' => $cText(@$content->subheading),
                    ],
                    'elements' => $els->map(fn ($e) => [
                        'title'       => $cText(@$e->data_values->title),
                        'excerpt'     => strLimit(strip_tags((string) @$e->data_values->description), 200),
                        'image'       => frontendImage('blog', 'thumb_' . @$e->data_values->image),
                        'url'         => route('blog.details', $e->slug),
                    ])->values()->all(),
                ];
        }

        return null;
    }

    /**
     * Build an ordered list of { key, content, elements } for a page's `secs`
     * (read-only; reuses homeSection()). Used by the homepage and generic pages.
     */
    protected function sectionList($page): array
    {
        $secs = ($page && $page->secs) ? json_decode($page->secs) : [];
        $out  = [];
        foreach ($secs as $sec) {
            $built = $this->homeSection($sec);
            if ($built) {
                $out[] = $built;
            }
        }
        return $out;
    }

    /**
     * Shape SEO props for an Inertia public page (read-only).
     */
    protected function seoProps($title, $seoContents, $seoImage): array
    {
        $keywords = @$seoContents->keywords;
        return [
            'title'       => __($title),
            'description' => @$seoContents->description,
            'keywords'    => is_array($keywords) ? implode(',', $keywords) : $keywords,
            'image'       => $seoImage,
        ];
    }

    public function pages($slug)
    {
        $page        = Page::where('tempname', activeTemplate())->where('slug', $slug)->firstOrFail();
        $pageTitle   = $page->name;
        $sections    = $page->secs;
        $seoContents = $page->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;

        // F1c-1: render via Inertia. Rollback (one line):
        //   return view('Template::pages', compact('pageTitle', 'sections', 'seoContents', 'seoImage'));
        return inertia('Public/Page', [
            'seo'      => $this->seoProps($pageTitle, $seoContents, $seoImage),
            'sections' => $this->sectionList($page),
        ]);
    }

    public function contact()
    {
        $pageTitle   = "Contact Us";
        $user        = auth()->user();
        $sections    = Page::where('tempname', activeTemplate())->where('slug', 'contact')->first();
        $seoContents = $sections->seo_content;
        $seoImage    = @$seoContents->image ? getImage(getFilePath('seo') . '/' . @$seoContents->image, getFileSize('seo')) : null;
        return view('Template::contact', compact('pageTitle', 'user', 'sections', 'seoContents', 'seoImage'));
    }

    public function contactSubmit(Request $request)
    {
        $request->validate([
            'name'    => 'required',
            'email'   => 'required',
            'subject' => 'required|string|max:255',
            'message' => 'required',
        ]);

        $request->session()->regenerateToken();

        if (!verifyCaptcha()) {
            $notify[] = ['error', 'Invalid captcha provided'];
            return back()->withNotify($notify);
        }

        $random = getNumber();

        $ticket           = new SupportTicket();
        $ticket->user_id  = auth()->id() ?? 0;
        $ticket->name     = $request->name;
        $ticket->email    = $request->email;
        $ticket->priority = Status::PRIORITY_MEDIUM;

        $ticket->ticket     = $random;
        $ticket->subject    = $request->subject;
        $ticket->last_reply = Carbon::now();
        $ticket->status     = Status::TICKET_OPEN;
        $ticket->save();

        $adminNotification            = new AdminNotification();
        $adminNotification->user_id   = auth()->user() ? auth()->user()->id : 0;
        $adminNotification->title     = 'A new contact message has been submitted';
        $adminNotification->click_url = urlPath('admin.ticket.view', $ticket->id);
        $adminNotification->save();

        $message                    = new SupportMessage();
        $message->support_ticket_id = $ticket->id;
        $message->message           = $request->message;
        $message->save();

        $notify[] = ['success', 'Ticket created successfully!'];

        return to_route('ticket.view', [$ticket->ticket])->withNotify($notify);
    }

    public function policyPages($slug)
    {
        $policy      = Frontend::where('tempname', activeTemplateName())->where('slug', $slug)->where('data_keys', 'policy_pages.element')->firstOrFail();
        $pageTitle   = $policy->data_values->title;
        $seoContents = $policy->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('policy_pages', $seoContents->image, getFileSize('seo'), true) : null;

        // F1c-1: render via Inertia. Rollback (one line):
        //   return view('Template::policy', compact('policy', 'pageTitle', 'seoContents', 'seoImage'));
        return inertia('Public/PolicyPage', [
            'seo'     => $this->seoProps($pageTitle, $seoContents, $seoImage),
            'title'   => __($pageTitle),
            'details' => @$policy->data_values->details,
        ]);
    }

    public function changeLanguage($lang = null)
    {
        $language = Language::where('code', $lang)->first();
        if (!$language) {
            $lang = 'en';
        }

        session()->put('lang', $lang);
        return back();
    }

    public function blogDetails($slug)
    {
        $blog        = Frontend::where('slug', $slug)->where('data_keys', 'blog.element')->firstOrFail();
        $recentBlogs = Frontend::where('id', '!=', $blog->id)->where('data_keys', 'blog.element')->latest()->limit(8)->get();
        $pageTitle   = $blog->data_values->title;
        $seoContents = $blog->seo_content;
        $seoImage    = @$seoContents->image ? frontendImage('blog', $seoContents->image, getFileSize('seo'), true) : null;
        return view('Template::blog_details', compact('blog', 'pageTitle', 'seoContents', 'seoImage', 'recentBlogs'));
    }

    public function cookieAccept()
    {
        Cookie::queue('gdpr_cookie', gs('site_name'), 43200);
    }

    public function cookiePolicy()
    {
        $cookieContent = Frontend::where('data_keys', 'cookie.data')->first();
        abort_if($cookieContent->data_values->status != Status::ENABLE, 404);
        $pageTitle = 'Cookie Policy';
        $cookie    = $cookieContent;

        // F1c-1: render via Inertia. Rollback (one line):
        //   return view('Template::cookie', compact('pageTitle', 'cookie'));
        return inertia('Public/CookiePolicy', [
            'seo'     => ['title' => __($pageTitle), 'description' => null, 'keywords' => null, 'image' => null],
            'title'   => __($pageTitle),
            'details' => @$cookie->data_values->description,
        ]);
    }

    public function placeholderImage($size = null)
    {
        $imgWidth  = explode('x', $size)[0];
        $imgHeight = explode('x', $size)[1];
        $text      = $imgWidth . '×' . $imgHeight;
        $fontFile  = realpath('assets/font/solaimanLipi_bold.ttf');
        $fontSize  = round(($imgWidth - 50) / 8);
        if ($fontSize <= 9) {
            $fontSize = 9;
        }
        if ($imgHeight < 100 && $fontSize > 30) {
            $fontSize = 30;
        }

        $image     = imagecreatetruecolor($imgWidth, $imgHeight);
        $colorFill = imagecolorallocate($image, 100, 100, 100);
        $bgFill    = imagecolorallocate($image, 255, 255, 255);
        imagefill($image, 0, 0, $bgFill);
        $textBox    = imagettfbbox($fontSize, 0, $fontFile, $text);
        $textWidth  = abs($textBox[4] - $textBox[0]);
        $textHeight = abs($textBox[5] - $textBox[1]);
        $textX      = ($imgWidth - $textWidth) / 2;
        $textY      = ($imgHeight + $textHeight) / 2;
        header('Content-Type: image/jpeg');
        imagettftext($image, $fontSize, 0, $textX, $textY, $colorFill, $fontFile, $text);
        imagejpeg($image);
        imagedestroy($image);
    }

    public function maintenance()
    {
        $pageTitle = 'Maintenance Mode';
        if (gs('maintenance_mode') == Status::DISABLE) {
            return to_route('home');
        }
        $maintenance = Frontend::where('data_keys', 'maintenance.data')->first();
        return view('Template::maintenance', compact('pageTitle', 'maintenance'));
    }

    public function blogs()
    {
        $pageTitle = 'Announces';
        $blogs     = Frontend::where('data_keys', 'blog.element')->latest()->paginate(getPaginate());
        $sections  = Page::where('tempname', activeTemplate())->where('slug', 'blogs')->first();
        return view('Template::blogs', compact('blogs', 'pageTitle', 'sections'));
    }

    public function apiDocumentation()
    {
        $pageTitle   = 'Developer - Api Documentation';
        $allCurrency = Currency::enable()->get();
        $plugins     = Plugin::where('status', Status::ENABLE)->get();
        return view('Template::api_documentation', compact('pageTitle', 'allCurrency', 'plugins'));
    }

    public function qrScan($uniqueCode)
    {

        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: PUT, GET, POST");
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $qrCode = QRcode::where('unique_code', $uniqueCode)->first();

        if (!$qrCode) {
            return response()->json(['error' => 'Not found']);
        }

        if ($qrCode->user_type == 'USER') {
            $user = User::find($qrCode->user_id);
        } elseif ($qrCode->user_type == 'AGENT') {
            $user = Agent::find($qrCode->user_id);
        } else {
            $user = Merchant::find($qrCode->user_id);
        }

        if (!$user) {
            return response()->json(['error' => 'Not found']);
        }

        return $user->username;
    }

    public function invoicePayment($invoiceNum)
    {
        try {
            $invNum = decrypt($invoiceNum);
        } catch (\Throwable $th) {
            $notify[] = ['error', 'Invalid invoice number'];
            return back()->withNotify($notify);
        }

        $pageTitle = "Invoice-#$invNum";
        $invoice   = Invoice::where('invoice_num', $invNum)->first();

        if (!$invoice) {
            $notify[] = ['error', 'Invoice not found'];
            return to_route('home')->withNotify($notify);
        }

        if ($invoice->status == 0) {
            $notify[] = ['error', 'Sorry! Invoice not published'];
            return to_route('home')->withNotify($notify);
        }
        return view('Template::invoice', compact('invoice', 'invoiceNum', 'pageTitle'));
    }

    public function sessionStatus(Request $request)
    {

        if ($request->reload) {
            return ['status' => true];
        }

        $user = @userGuard()['type'];

        if ($user == $request->userType) {
            return ['status' => 200];
        }

        return ['status' => 404];
    }

    public function login()
    {
        return back();
    }

    public function subscribe(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:250|unique:subscribers,email',
        ]);

        if (!$validator->passes()) {
            return response()->json(['error' => $validator->errors()->all()]);
        }

        $newSubscriber        = new Subscriber();
        $newSubscriber->email = $request->email;
        $newSubscriber->save();

        return response()->json(['success' => true, 'message' => 'Thank you, We\'ll notice you our latest news']);
    }
}
