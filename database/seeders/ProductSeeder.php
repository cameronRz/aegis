<?php

namespace Database\Seeders;

use App\Enum\BillingInterval;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Categories
        $electronics = Category::factory()->create(['name' => 'Electronics', 'slug' => 'electronics']);
        $homeGarden = Category::factory()->create(['name' => 'Home & Garden', 'slug' => 'home-garden']);
        $officeSupplies = Category::factory()->create(['name' => 'Office Supplies', 'slug' => 'office-supplies']);
        $healthBeauty = Category::factory()->create(['name' => 'Health & Beauty', 'slug' => 'health-beauty']);
        $software = Category::factory()->create(['name' => 'Software', 'slug' => 'software']);
        $ebooks = Category::factory()->create(['name' => 'eBooks & Guides', 'slug' => 'ebooks-guides']);
        $templates = Category::factory()->create(['name' => 'Templates & Assets', 'slug' => 'templates-assets']);
        $courses = Category::factory()->create(['name' => 'Courses & Training', 'slug' => 'courses-training']);
        $memberships = Category::factory()->create(['name' => 'Memberships', 'slug' => 'memberships']);

        // Subscriptions (3)
        foreach ([
            ['name' => 'Starter Plan', 'description' => 'Essential features for individuals and freelancers.', 'price' => 999, 'billing_interval' => BillingInterval::Monthly, 'trial_period_days' => 7],
            ['name' => 'Pro Plan', 'description' => 'Advanced tools for growing teams.', 'price' => 2999, 'billing_interval' => BillingInterval::Monthly, 'trial_period_days' => 14],
            ['name' => 'Business Plan', 'description' => 'Full feature access with priority support for large organisations.', 'price' => 9999, 'billing_interval' => BillingInterval::Monthly, 'trial_period_days' => 30],
        ] as $product) {
            Product::factory()->subscription()->withCategory($memberships)->create($product);
        }

        // Physical products (20)
        foreach ([
            // Electronics (6)
            ['name' => 'Wireless Mouse', 'description' => 'Ergonomic wireless mouse with 12-month battery life.', 'price' => 2499, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 150],
            ['name' => 'Mechanical Keyboard', 'description' => 'Full-size mechanical keyboard with tactile switches and RGB backlight.', 'price' => 8999, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 75],
            ['name' => 'USB-C Hub 7-in-1', 'description' => 'Expand connectivity with HDMI, USB-A, SD card, and more.', 'price' => 4999, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 200],
            ['name' => 'HD Webcam 1080p', 'description' => 'Crisp 1080p video with built-in noise-cancelling microphone.', 'price' => 5999, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 60],
            ['name' => 'LED Desk Lamp', 'description' => 'Touch-controlled lamp with adjustable colour temperature and USB charging port.', 'price' => 3499, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 120],
            ['name' => 'Noise-Cancelling Headphones', 'description' => 'Over-ear headphones with active noise cancellation and 30-hour battery life.', 'price' => 12999, 'category_id' => $electronics->id, 'track_inventory' => true, 'stock_quantity' => 45],
            // Home & Garden (5)
            ['name' => 'Scented Candle Set', 'description' => 'Set of 3 hand-poured soy candles in seasonal scents.', 'price' => 1999, 'category_id' => $homeGarden->id, 'track_inventory' => true, 'stock_quantity' => 300],
            ['name' => 'Bamboo Cutting Board', 'description' => 'Large reversible bamboo cutting board with deep juice groove.', 'price' => 2799, 'category_id' => $homeGarden->id, 'track_inventory' => true, 'stock_quantity' => 180],
            ['name' => 'Ceramic Planter Set', 'description' => 'Set of 3 minimalist matte white ceramic planters with drainage holes.', 'price' => 3499, 'category_id' => $homeGarden->id, 'track_inventory' => true, 'stock_quantity' => 95],
            ['name' => 'Premium Dish Towel Set', 'description' => 'Pack of 6 absorbent quick-dry cotton dish towels.', 'price' => 1499, 'category_id' => $homeGarden->id],
            ['name' => 'Stainless Steel Water Bottle', 'description' => 'Double-walled 32oz bottle that keeps drinks cold for 24 hours.', 'price' => 2299, 'category_id' => $homeGarden->id, 'track_inventory' => true, 'stock_quantity' => 250],
            // Office Supplies (5)
            ['name' => 'Leather Desk Pad', 'description' => 'Large 36×18 inch faux-leather desk mat with stitched edges.', 'price' => 3999, 'category_id' => $officeSupplies->id, 'track_inventory' => true, 'stock_quantity' => 100],
            ['name' => 'Hardcover Notebook Set', 'description' => 'Set of 3 dot-grid hardcover notebooks in A5 size.', 'price' => 1799, 'category_id' => $officeSupplies->id],
            ['name' => 'Premium Ballpoint Pen Set', 'description' => 'Pack of 10 smooth-writing ballpoint pens in black and blue.', 'price' => 1299, 'category_id' => $officeSupplies->id],
            ['name' => 'Desktop Organizer', 'description' => 'Bamboo desktop organizer with 5 compartments and a sliding drawer.', 'price' => 2999, 'category_id' => $officeSupplies->id, 'track_inventory' => true, 'stock_quantity' => 85],
            ['name' => 'Magnetic Whiteboard', 'description' => '24×36 inch magnetic whiteboard with aluminium frame and eraser.', 'price' => 4499, 'category_id' => $officeSupplies->id, 'track_inventory' => true, 'stock_quantity' => 40],
            // Health & Beauty (4)
            ['name' => 'Foam Roller', 'description' => 'High-density EVA foam roller for post-workout muscle recovery.', 'price' => 2499, 'category_id' => $healthBeauty->id, 'track_inventory' => true, 'stock_quantity' => 175],
            ['name' => 'Resistance Bands Set', 'description' => 'Set of 5 progressive latex resistance bands for home workouts.', 'price' => 1899, 'category_id' => $healthBeauty->id, 'track_inventory' => true, 'stock_quantity' => 220],
            ['name' => 'Premium Yoga Mat', 'description' => 'Non-slip 6mm thick yoga mat with alignment lines and carry strap.', 'price' => 4999, 'category_id' => $healthBeauty->id, 'track_inventory' => true, 'stock_quantity' => 80],
            ['name' => 'Daily Vitamin Bundle', 'description' => 'Monthly supply of essential vitamins including D3, B12, and Omega-3.', 'price' => 3299, 'category_id' => $healthBeauty->id, 'track_inventory' => true, 'stock_quantity' => 150, 'is_active' => false],
        ] as $product) {
            Product::factory()->physical()->create($product);
        }

        // Digital products (52)
        foreach ([
            // Software (10)
            ['name' => 'Password Manager License', 'description' => 'Lifetime licence for a secure cross-device password manager.', 'price' => 3999, 'category_id' => $software->id],
            ['name' => 'PDF Editor Pro', 'description' => 'Edit, annotate, merge, and sign PDF documents on any platform.', 'price' => 5999, 'category_id' => $software->id],
            ['name' => 'Photo Editing Suite', 'description' => 'Professional-grade photo editor with RAW support and AI-powered tools.', 'price' => 14999, 'category_id' => $software->id],
            ['name' => 'VPN License (1 Year)', 'description' => 'Secure your connection on up to 5 devices for 12 months.', 'price' => 4999, 'category_id' => $software->id],
            ['name' => 'Antivirus Suite', 'description' => 'Real-time virus and malware protection for Windows and Mac, 1-year licence.', 'price' => 2999, 'category_id' => $software->id],
            ['name' => 'Screen Recorder Pro', 'description' => 'Record, edit, and share screen recordings with a single click.', 'price' => 3499, 'category_id' => $software->id],
            ['name' => 'Video Downloader & Converter', 'description' => 'Download and convert video from 1,000+ supported sites.', 'price' => 2499, 'category_id' => $software->id],
            ['name' => 'File Compression Tool', 'description' => 'Compress and extract ZIP, RAR, 7Z, and 20+ other archive formats.', 'price' => 1999, 'category_id' => $software->id],
            ['name' => 'Remote Desktop App', 'description' => 'Securely access and control any computer remotely from anywhere.', 'price' => 4499, 'category_id' => $software->id, 'is_active' => false],
            ['name' => 'Disk Cleaner & Optimizer', 'description' => 'Free up disk space and speed up your PC with automated cleanup routines.', 'price' => 1999, 'category_id' => $software->id],
            // eBooks & Guides (12)
            ['name' => 'Web Design Handbook', 'description' => 'A comprehensive guide to modern responsive web design principles.', 'price' => 1499, 'category_id' => $ebooks->id],
            ['name' => 'Digital Marketing Playbook', 'description' => 'Step-by-step strategies for growing and monetising an online audience.', 'price' => 1999, 'category_id' => $ebooks->id],
            ['name' => 'Ultimate Productivity Guide', 'description' => 'Proven systems and habits for managing time, focus, and energy.', 'price' => 999, 'category_id' => $ebooks->id],
            ['name' => 'Excel Masterclass eBook', 'description' => 'Master formulas, pivot tables, and data visualisation in Excel.', 'price' => 1299, 'category_id' => $ebooks->id],
            ['name' => 'SEO Starter Guide', 'description' => 'Understand how search engines work and rank your site higher.', 'price' => 999, 'category_id' => $ebooks->id],
            ['name' => 'Social Media Blueprint', 'description' => 'Build an engaged, loyal social media following from zero.', 'price' => 1499, 'category_id' => $ebooks->id],
            ['name' => 'Personal Finance Guide', 'description' => 'Simple frameworks for budgeting, saving, and getting started with investing.', 'price' => 1299, 'category_id' => $ebooks->id],
            ['name' => 'Python for Beginners', 'description' => 'A friendly, example-driven introduction to Python programming.', 'price' => 1799, 'category_id' => $ebooks->id],
            ['name' => 'Photography Basics', 'description' => 'Learn composition, natural lighting, and camera settings for better photos.', 'price' => 1499, 'category_id' => $ebooks->id],
            ['name' => 'Healthy Recipe Collection', 'description' => '100+ nutritionist-approved recipes for breakfast, lunch, dinner, and snacks.', 'price' => 799, 'category_id' => $ebooks->id],
            ['name' => 'Solo Travel Handbook', 'description' => 'Practical advice for planning and enjoying safe, rewarding solo trips.', 'price' => 1299, 'category_id' => $ebooks->id],
            ['name' => 'Business Writing Essentials', 'description' => 'Write clearer emails, reports, and proposals that get results.', 'price' => 999, 'category_id' => $ebooks->id],
            // Templates & Assets (15)
            ['name' => 'Resume Template Pack', 'description' => '10 professionally designed resume and matching cover letter templates.', 'price' => 1299, 'category_id' => $templates->id],
            ['name' => 'Invoice Template Bundle', 'description' => '15 clean invoice templates compatible with Word, Excel, and Google Docs.', 'price' => 999, 'category_id' => $templates->id],
            ['name' => 'PowerPoint Theme Pack', 'description' => '20 modern presentation themes with 200+ customisable slide layouts.', 'price' => 1999, 'category_id' => $templates->id],
            ['name' => 'Canva Business Template Set', 'description' => '50 editable Canva templates for social media and marketing collateral.', 'price' => 2499, 'category_id' => $templates->id],
            ['name' => 'Logo Design Bundle', 'description' => '50 fully editable SVG logo designs spanning popular industries.', 'price' => 3499, 'category_id' => $templates->id],
            ['name' => 'Icon Pack (500 Icons)', 'description' => '500 pixel-perfect icons in SVG, PNG, and Figma source formats.', 'price' => 1499, 'category_id' => $templates->id],
            ['name' => 'Premium Font Bundle', 'description' => '30 commercial-use fonts including display, body, and handwritten styles.', 'price' => 2999, 'category_id' => $templates->id],
            ['name' => 'Social Media Graphics Kit', 'description' => 'Editable post and story templates for Instagram, Facebook, and LinkedIn.', 'price' => 1999, 'category_id' => $templates->id],
            ['name' => 'Business Card Templates', 'description' => '20 double-sided business card designs in print-ready format.', 'price' => 999, 'category_id' => $templates->id],
            ['name' => 'Email Newsletter Templates', 'description' => '12 responsive HTML email templates optimised for marketing campaigns.', 'price' => 1499, 'category_id' => $templates->id],
            ['name' => 'Pitch Deck Template', 'description' => 'Investor-ready pitch deck with 40 slides and speaker notes included.', 'price' => 1799, 'category_id' => $templates->id],
            ['name' => 'Brand Style Guide Template', 'description' => 'Complete brand guidelines template covering colours, typography, and usage rules.', 'price' => 2299, 'category_id' => $templates->id],
            ['name' => 'Website Wireframe Kit', 'description' => '80+ wireframe components for Figma and Sketch with desktop and mobile variants.', 'price' => 2999, 'category_id' => $templates->id],
            ['name' => 'UI Component Library', 'description' => 'Full design system with 300+ UI components for Figma.', 'price' => 4999, 'category_id' => $templates->id],
            ['name' => 'Digital Illustration Pack', 'description' => '80 vector illustrations in flat and outline styles, fully editable.', 'price' => 2499, 'category_id' => $templates->id],
            // Courses & Training (15)
            ['name' => 'Project Management Fundamentals', 'description' => 'Learn Agile, Scrum, and Kanban for managing projects effectively.', 'price' => 4999, 'category_id' => $courses->id],
            ['name' => 'Digital Marketing Mastery', 'description' => 'Full course covering SEO, paid ads, email marketing, and analytics.', 'price' => 7999, 'category_id' => $courses->id],
            ['name' => 'Graphic Design Bootcamp', 'description' => 'Learn Figma, typography, and colour theory from absolute scratch.', 'price' => 9999, 'category_id' => $courses->id],
            ['name' => 'Python Programming for Beginners', 'description' => 'Hands-on Python course with 40+ coding exercises and projects.', 'price' => 5999, 'category_id' => $courses->id],
            ['name' => 'Content Writing Mastery', 'description' => 'Write compelling blog posts, web copy, and long-form content that ranks.', 'price' => 4999, 'category_id' => $courses->id],
            ['name' => 'Excel for Business Professionals', 'description' => 'Advanced Excel for data analysis, dynamic dashboards, and automation.', 'price' => 3999, 'category_id' => $courses->id],
            ['name' => 'Time Management & Productivity', 'description' => 'Build systems and habits that dramatically increase your daily output.', 'price' => 2999, 'category_id' => $courses->id],
            ['name' => 'Leadership Skills for Managers', 'description' => 'Develop the communication, delegation, and coaching habits of great leaders.', 'price' => 6999, 'category_id' => $courses->id],
            ['name' => 'Data Analysis Basics', 'description' => 'Analyse and visualise data using Python, Pandas, and Matplotlib.', 'price' => 5999, 'category_id' => $courses->id],
            ['name' => 'SEO Fundamentals Course', 'description' => 'Master on-page, off-page, and technical SEO from first principles.', 'price' => 4999, 'category_id' => $courses->id],
            ['name' => 'Video Editing Crash Course', 'description' => 'Edit professional-quality videos in Premiere Pro and DaVinci Resolve.', 'price' => 7999, 'category_id' => $courses->id],
            ['name' => 'Copywriting for Conversions', 'description' => 'Write sales pages, ads, and email sequences that drive real conversions.', 'price' => 5999, 'category_id' => $courses->id],
            ['name' => 'Email Marketing Mastery', 'description' => 'Build, segment, and automate email campaigns that convert subscribers into buyers.', 'price' => 4999, 'category_id' => $courses->id],
            ['name' => 'Google Ads Fundamentals', 'description' => 'Set up and optimise Google Ads campaigns to maximise return on ad spend.', 'price' => 3999, 'category_id' => $courses->id],
            ['name' => 'Social Media Marketing Course', 'description' => 'Grow brand awareness and drive sales through organic and paid social.', 'price' => 5499, 'category_id' => $courses->id, 'is_active' => false],
        ] as $product) {
            Product::factory()->digital()->create($product);
        }
    }
}
