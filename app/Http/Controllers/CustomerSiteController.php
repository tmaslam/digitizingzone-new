<?php

namespace App\Http\Controllers;

use App\Support\PortalMailer;
use App\Support\PublicSitePricing;
use App\Support\SiteContext;
use App\Support\SignupOfferService;
use App\Support\CustomerPublicRateLimit;
use App\Support\EmailValidation;
use App\Support\TurnstileVerifier;
use Illuminate\Http\Request;

class CustomerSiteController extends Controller
{
    private const SERVICE_PAGES = [
        'embroidery-digitizing' => [
            'title' => 'Custom Embroidery Digitizing Service',
            'image' => '/images/embroidery-digitizing-services-1.webp',
            'banner_image' => '/images/banner-embroidery-%20digitizing-%20services.webp',
            'page_heading' => 'Professional Embroidery Digitizing — Starting at $1',
            'meta_description' => 'Expert custom embroidery digitizing from $1 per design. Production-ready DST, PES, EXP, VP3 files. 12-hour turnaround, free revisions, satisfaction guaranteed.',
            'paragraphs' => [
                'Embroidery digitizing is the process of converting your logo or artwork into a machine-ready stitch file. Unlike auto-digitizing software that produces rough, inefficient results, every design at 1DollarDigitizing is hand-crafted by an experienced digitizer who understands stitch paths, density, pull compensation, and underlay — the technical details that make the difference between a design that stitches cleanly and one that breaks needles and puckers fabric.',
                'We deliver production-ready files in all major formats including DST (Tajima), PES (Brother), EXP (Melco), VP3 (Pfaff), HUS, JEF, and XXX. Whether you need a simple left-chest logo or a complex multi-color jacket back, we digitize every design with the same attention to detail and care.',
                'Our standard turnaround is 12 hours, with rush delivery available for urgent projects. Every order includes free revisions until you are completely satisfied. With over 20 years of experience and more than 1 million designs completed, we are the trusted digitizing partner for embroidery shops, apparel decorators, and promotional product businesses worldwide.',
                'From corporate uniforms and sports kits to promotional merchandise and fashion brands — if it needs to be embroidered, we can digitize it. Upload your artwork, specify your requirements, and receive a production-ready file within hours.',
            ],
            'service_offers_title' => 'File formats included with every order:',
            'service_offers' => [
                'DST (Tajima) — industry standard for commercial machines',
                'PES (Brother) — for Brother and Babylock machines',
                'EXP (Melco) — for Melco and Ameco machines',
                'VP3 (Pfaff) — for Pfaff and Husqvarna Viking machines',
                'HUS, JEF, XXX, SEW — all other formats available on request',
            ],
            'gallery_columns' => 3,
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/embroidery-digitizing-services-1.webp',
                '/images/embroidery-digitizing-services-2.webp',
                '/images/embroidery-digitizing-services-3.webp',
            ],
        ],
        '3d-puff-embroidery-digitizing' => [
            'title' => '3D Puff Embroidery Digitizing',
            'image' => '/images/3d-puff-embroidery-digitizing-services-1.webp',
            'banner_image' => '/images/banner-3d-puff-embroidery.webp',
            'page_heading' => '3D Puff Embroidery Digitizing — Bold Raised Designs for Caps & Apparel',
            'meta_description' => 'Professional 3D puff embroidery digitizing for caps, jackets and hoodies. Correct foam specification, clean edges, no blowout. Starting at $1. Fast turnaround.',
            'paragraphs' => [
                '3D puff embroidery gives your designs a premium, raised look that instantly stands out on caps, jackets, and hoodies. It is the go-to technique for streetwear brands, sports teams, and corporate apparel that wants to make a bold impression. The raised foam beneath the stitches creates a three-dimensional effect that flat embroidery simply cannot replicate.',
                'Getting 3D puff digitizing right requires specialist knowledge that goes beyond standard embroidery. The foam underlay must be the correct thickness for the design size, the satin stitches must be angled precisely to cover the foam edges cleanly, and the pull compensation must account for the raised surface. Our digitizers specialize in exactly this — having completed tens of thousands of 3D puff designs across every garment type.',
                'Common mistakes in puff digitizing include foam blowout at the edges, insufficient density causing foam to show through, and incorrect stitch angle causing the design to lean. Our files are engineered to avoid all of these issues — every single time.',
            ],
            'content_blocks' => [
                [
                    'title' => 'Best garments for 3D puff embroidery:',
                    'list' => [
                        'Snapback and fitted caps — the classic puff application',
                        'Hoodies and sweatshirts — chest and sleeve logos',
                        'Jackets and outerwear — bold brand statements',
                        'Sports uniforms — team names and numbers',
                        'Workwear and hi-vis garments — company branding',
                    ],
                ],
                [
                    'title' => 'What makes our 3D puff digitizing different:',
                    'list' => [
                        'Correct foam thickness specification included with every file',
                        'Satin stitches precisely angled for complete foam coverage',
                        'No foam blowout — edges are always clean and sharp',
                        'Optimized stitch density to prevent show-through',
                        'Compatible with all commercial embroidery machines',
                        'Free revisions until the design stitches perfectly',
                    ],
                ],
            ],
            'gallery_columns' => 3,
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/3d-puff-embroidery-digitizing-services-1.webp',
                '/images/3d-puff-embroidery-digitizing-services-2.webp',
                '/images/3d-puff-embroidery-digitizing-services-3.webp',
            ],
        ],
        'applique-embroidery-digitizing' => [
            'title' => 'Applique Embroidery Digitizing',
            'image' => '/images/applique-embroidery-digitizing-1.webp',
            'banner_image' => '/images/banner-applique-embroidery-digitizing%20.webp',
            'page_heading' => 'Applique Embroidery Digitizing — Save Thread, Reduce Time, Keep Quality',
            'meta_description' => 'Expert applique embroidery digitizing with precise placement stitches, clean tack-down runs, and sharp satin borders. Affordable pricing from $1. Fast turnaround.',
            'paragraphs' => [
                'Applique embroidery is one of the most efficient ways to embroider large designs. Instead of filling every area with dense stitching, fabric pieces are sewn onto the base garment — saving thousands of stitches, reducing machine run time, and giving your design a bold, fabric-on-fabric aesthetic that dense fill simply cannot match.',
                'Getting applique digitizing right requires precise sequencing. The placement stitch must be accurate so the fabric is positioned correctly. The tack-down stitch must hold the fabric flat without distorting it. And the satin border must cover the raw edge cleanly without gaps or overlaps. Our digitizers follow a proven three-step process that ensures clean results on every machine, every time.',
                'Applique is the smart choice for large team logos, jacket backs, and any design where stitch count reduction matters. Fewer stitches means faster machine run time, lower thread consumption, and lower cost per garment — without any reduction in visual impact.',
            ],
            'content_blocks' => [
                [
                    'title' => 'Why choose applique over fill stitch:',
                    'list' => [
                        'Drastically reduces stitch count on large fills — saving time and thread',
                        'Faster machine run time means lower production cost per piece',
                        'Creates a premium fabric-on-fabric aesthetic',
                        'Less stress on the garment — ideal for lightweight fabrics',
                        'Works perfectly on jackets, bags, hats, and uniforms',
                    ],
                ],
                [
                    'title' => 'Our three-step applique digitizing process:',
                    'list' => [
                        'Step 1 — Placement stitch: marks exactly where the fabric piece goes',
                        'Step 2 — Tack-down stitch: secures the applique fabric flat to the garment',
                        'Step 3 — Satin border: covers the raw edge for a clean, professional finish',
                    ],
                ],
            ],
            'gallery_columns' => 3,
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/applique-embroidery-digitizing-1.webp',
                '/images/applique-embroidery-digitizing-2.webp',
                '/images/applique-embroidery-digitizing-3.webp',
            ],
        ],
        'chain-stitch-embroidery-digitizing' => [
            'title' => 'Chain Stitch Embroidery Digitizing',
            'image' => '/images/Chain-Stitch-Embroidery-Digitizing(1).webp',
            'banner_image' => '/images/banner-chain-stich-embroidery%20.webp',
            'page_heading' => 'Chain Stitch Embroidery Digitizing — Classic Style, Expert Execution',
            'meta_description' => 'Specialist chain stitch embroidery digitizing for vintage logos, denim, workwear and premium garments. Correct stitch path sequencing for single-needle machines.',
            'paragraphs' => [
                'Chain stitch embroidery has a distinctive looped, rope-like texture that is instantly recognizable on vintage workwear, denim jackets, and premium garments. Each stitch interlocks with the previous one to form a continuous chain — creating a handcrafted look that flat embroidery and standard fill simply cannot replicate.',
                'Chain stitch digitizing requires a completely different technical approach from standard embroidery. The stitch path must be continuous — chain stitch machines cannot jump between sections the way multi-needle machines can. The entire design must be planned as a single unbroken path, with stitch direction, travel, and sequence all carefully engineered from the start.',
                'Our specialist chain stitch digitizers produce files that run correctly on single-needle chainstitch machines, with clean path logic, correct stitch length, and no unnecessary jumps or tie-offs. The result is a design that stitches smoothly and looks exactly as intended.',
            ],
            'content_blocks' => [
                [
                    'title' => 'Best applications for chain stitch embroidery:',
                    'list' => [
                        'Vintage and retro brand logos — the authentic heritage look',
                        'Denim jackets and workwear — the classic chain stitch canvas',
                        'Premium garment branding — elevated tactile quality',
                        'Western and Americana-style designs',
                        'Decorative lettering and script work',
                    ],
                ],
            ],
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/Chain-Stitch-Embroidery-Digitizing(1).webp',
                '/images/Chain-Stitch-Embroidery-Digitizing(2).webp',
            ],
        ],
        'photo-digitizing' => [
            'title' => 'Photo Digitizing Service',
            'image' => '/images/Photo-Digitizing-Services-1.webp',
            'banner_image' => '/images/banner-photo%20-digitizing-services.webp',
            'page_heading' => 'Photo Digitizing — Turn Photographs into Embroidery-Ready Designs',
            'meta_description' => 'Professional photo digitizing service for portraits, memorials, and detailed artwork. Expert simplification and interpretation for clean embroidery output. Custom quote.',
            'paragraphs' => [
                'Photo digitizing is the art of interpreting a photograph and converting it into a design that an embroidery machine can stitch. Unlike logo digitizing where the shapes are clean and defined, photos present a unique challenge — gradients, shadows, skin tones, and fine detail all need to be interpreted and simplified into stitchable elements without losing the likeness or emotion of the original image.',
                'Our photo digitizing specialists have years of experience working with portraits, pet photos, memorial designs, and detailed artwork. We do not simply trace the photo — we study it, identify the key shapes and tonal areas, and reconstruct it as an embroidery design that will look impressive at the required size on the chosen garment.',
                'Every photo digitizing project begins with a review of your image. We advise on the best approach, confirm the recommended size and placement, and answer any questions before we begin. This ensures you receive exactly what you expect — with no wasted time or surprise revisions.',
            ],
            'service_offers_title' => 'Photo and artwork services we offer:',
            'service_offers' => [
                'Portrait and face digitizing',
                'Pet photo digitizing',
                'Memorial and tribute designs',
                'Logo vectorization from photos',
                'Logo cleanup and redraw',
                'Photo restoration',
                'Color separation for screen printing',
                'Custom logo creation from brief',
            ],
            'gallery_columns' => 3,
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/Photo-Digitizing-Services-1.webp',
                '/images/Photo-Digitizing-Services-3.webp',
                '/images/Photo-Digitizing-Services-2.webp',
            ],
        ],
        'vector-art' => [
            'title' => 'Vector Art Conversion Service',
            'image' => '/images/vector-art-services-1.webp',
            'banner_image' => '/images/banner-vector-art-services%20.webp',
            'page_heading' => 'Vector Art Conversion — Clean, Scalable Files for Any Medium',
            'meta_description' => 'Professional vector art conversion service. Convert any raster image into clean AI, EPS, SVG or PDF files. Perfect for screen printing, DTG, vinyl cutting and embroidery.',
            'paragraphs' => [
                'A vector file is the foundation of any professional print or embroidery project. Unlike raster images such as JPG or PNG, vector files are built from mathematical paths — meaning they can be scaled to any size, from a business card to a billboard, without any loss of quality or sharpness. They can also be color-separated, edited layer by layer, and output to any format a print shop or embroiderer needs.',
                'We convert any image — even low-resolution, blurry, or hand-drawn artwork — into a clean, professional vector file. Our vectorization is done manually by skilled artists, not auto-trace software that produces messy anchor points and rough edges. The result is a clean, production-ready file that any designer, printer, or digitizer can work with immediately.',
                'Vector files are the standard input for screen printing, direct-to-garment printing, heat transfer vinyl, laser engraving, large-format printing, sign making, and embroidery digitizing. If you need one file that works everywhere, vector is the answer.',
            ],
            'content_blocks' => [
                [
                    'title' => 'What we convert to vector:',
                    'list' => [
                        'Logos from photos or scanned images',
                        'Hand-drawn artwork and rough sketches',
                        'Low-resolution or blurry raster images',
                        'Old or corrupted vector files that need a full rebuild',
                        'Complex illustrations requiring careful manual redraw',
                    ],
                ],
                [
                    'title' => 'Vector output formats we deliver:',
                    'list' => [
                        'AI (Adobe Illustrator) — for print studios and designers',
                        'EPS — universal vector format accepted everywhere',
                        'SVG — for web, cutting machines and laser engravers',
                        'PDF — print-ready with embedded fonts and paths',
                    ],
                ],
            ],
            'hide_highlights' => true,
            'gallery_images' => [
                '/images/vector-art-services-1.webp',
                '/images/vector-art-services-2.webp',
            ],
        ],
    ];

    public function home(Request $request)
    {
        if ($request->session()->has('customer_user_id')) {
            return redirect(url('/dashboard.php'));
        }

        return response(file_get_contents(public_path('index.html')))
            ->header('Content-Type', 'text/html');
    }

    public function workProcess(Request $request)
    {
        return view('public.work-process', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function about(Request $request)
    {
        return view('public.about', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function quality(Request $request)
    {
        return view('public.quality', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function services(Request $request)
    {
        return view('public.services', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function servicePage(Request $request, string $section)
    {
        $service = self::SERVICE_PAGES[$section] ?? null;

        if (! $service) {
            abort(404);
        }

        return view('public.service-detail', [
            'site' => $request->attributes->get('siteContext'),
            'service' => array_merge($service, ['slug' => $section]),
        ]);
    }

    public function pricing(Request $request)
    {
        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        return view('public.pricing', [
            'site' => $site,
            'pricing' => PublicSitePricing::forSite($site),
        ]);
    }

    public function formats(Request $request)
    {
        return view('public.formats', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function paymentOptions(Request $request)
    {
        return redirect(url('/contact-us.php'));
    }

    public function robots(Request $request)
    {
        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Sitemap: '.$this->absoluteUrl($request, '/sitemap.xml'),
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function sitemap(Request $request)
    {
        return response()
            ->view('public.sitemap', [
                'urls' => $this->publicSiteUrls($request),
            ])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function contact(Request $request)
    {
        return view('public.contact', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function privacyPolicy(Request $request)
    {
        return view('public.privacy-policy', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function terms(Request $request)
    {
        return view('public.terms', [
            'site' => $request->attributes->get('siteContext'),
        ]);
    }

    public function sendContact(Request $request)
    {
        /** @var SiteContext $site */
        $site = $request->attributes->get('siteContext');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', EmailValidation::rule(), 'max:190'],
            'company' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'website_url' => ['nullable', 'string', 'max:1'],
        ]);

        if (trim((string) ($validated['website_url'] ?? '')) !== '') {
            return back()->with('success', 'Thanks. Your message has been received.');
        }

        if (! TurnstileVerifier::verify($request, 'public-contact')) {
            return back()->withErrors(['contact' => 'Please complete the security verification and try again.'])->withInput();
        }

        if (CustomerPublicRateLimit::tooManyAttempts($request, 'contact', $site->legacyKey, strtolower(trim((string) $validated['email'])), 5, 600)) {
            return back()->withErrors(['contact' => 'Too many messages were sent from this connection. Please try again later.'])->withInput();
        }

        $recipient = (string) config('mail.admin_alert_address', $site->supportEmail);
        $subject = '['.$site->displayLabel().'] '.trim((string) $validated['subject']);
        $body = view('customer.emails.contact-message', [
            'siteContext' => $site,
            'payload' => array_merge($validated, [
                'ip_address' => (string) ($request->ip() ?? '127.0.0.1'),
            ]),
        ])->render();

        $sent = PortalMailer::sendHtml($recipient, $subject, $body);

        return $sent
            ? back()->with('success', 'Thanks. Your message has been received.')
            : back()->withErrors(['contact' => 'We could not send your message right now. Please try again or email support directly.']);
    }

    private function publicSiteUrls(Request $request): array
    {
        $urls = [
            ['path' => '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['path' => '/about-us.php', 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['path' => '/our-quality.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/work-process.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/price-plan.php', 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['path' => '/formats.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/contact-us.php', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['path' => '/privacy-policy.php', 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['path' => '/terms.php', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        foreach (array_keys(self::SERVICE_PAGES) as $slug) {
            $urls[] = [
                'path' => '/'.$slug.'.php',
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ];
        }

        return array_map(function (array $url) use ($request): array {
            return [
                'loc' => $this->absoluteUrl($request, $url['path']),
                'changefreq' => $url['changefreq'],
                'priority' => $url['priority'],
            ];
        }, $urls);
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        return url($path);
    }
}
