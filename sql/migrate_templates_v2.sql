-- migrate_templates_v2.sql
-- Inserts 10 ready-to-sell video prompt templates.
-- Safe to run multiple times (ON DUPLICATE KEY UPDATE is a no-op if name unchanged).
-- Run in phpMyAdmin or via CLI: mysql -u user -p dbname < migrate_templates_v2.sql

INSERT INTO `prompt_templates` (`name`, `category`, `template`, `is_active`) VALUES

-- 1. F&B Product
('F&B Product Commercial', 'product',
'A high-energy commercial video for {{product_name}}.

Scene: A vibrant concert or social gathering with energetic crowd, music, and celebration atmosphere.

Camera: Cinematic close-ups, slow-motion liquid shots, dynamic crowd sweeps, handheld immersive angles.

Product Focus: {{product_name}} shown ice-cold with condensation, bubbles, and refreshing texture.

Action: Young adults laughing, cheering, raising drinks, opening bottles with crisp sound.

Mood & Lighting: Neon lights, high contrast, energetic flashes synced with music.

Key Benefits: {{key_benefits}}

Target Audience: Young, social, energetic consumers.

Ending: Logo reveal and tagline "{{tagline}}" with crowd cheering.

Style: Ultra-realistic, 4K, cinematic commercial.',
1),

-- 2. Mattress / Comfort
('Mattress & Comfort Ad', 'product',
'A calming, emotional commercial video for {{product_name}} mattress.

Scene: A peaceful bedroom with soft morning light and cozy atmosphere.

Camera: Slow motion, soft focus, close-ups of fabric texture, gentle camera movement.

Product Focus: Mattress surface, softness, body support, breathable fabric.

Action: A person lying down, smiling, hugging pillow, falling into deep restful sleep.

Mood & Lighting: Warm, soft, golden lighting, relaxing tone.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}" with a peaceful sleeping scene.

Style: Premium lifestyle, cinematic, soothing visuals.',
1),

-- 3. Beauty / Skincare
('Beauty & Skincare Promo', 'product',
'A premium beauty commercial for {{product_name}}.

Scene: Luxury bathroom or studio with clean, minimal aesthetic.

Camera: Macro close-ups of skin texture, slow-motion product application, soft glow lighting.

Product Focus: Cream texture, absorption, skin transformation.

Action: Model applying product, glowing skin reveal, confident smile.

Mood & Lighting: Bright, soft, clean, elegant.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}" with glowing skin close-up.

Style: Luxury brand, high-end commercial.',
1),

-- 4. Restaurant / Food Promo
('Restaurant & Food Promo', 'restaurant',
'An appetizing commercial video for {{restaurant_name}}.

Scene: Busy restaurant environment with chefs cooking and customers enjoying meals.

Camera: Close-ups of food, sizzling shots, slow-motion pouring sauce, cinematic plating shots.

Product Focus: {{signature_dishes}}, texture, steam, freshness.

Action: People eating, smiling, sharing food, chefs cooking.

Mood & Lighting: Warm, inviting, golden tones.

Key Benefits: {{key_benefits}}

Target Audience: Food lovers, families, social diners.

Ending: Logo and tagline "{{tagline}}", location: {{location}}.

Style: Food commercial, ultra-realistic.',
1),

-- 5. Real Estate / Property
('Real Estate Showcase', 'real_estate',
'A premium property showcase video for {{property_name}}.

Scene: Modern home interior and exterior, lifestyle living environment.

Camera: Drone shots, wide-angle interior, smooth cinematic transitions.

Property Focus: {{key_features}} — spacious layout, design, and facilities.

Action: Family enjoying home, relaxing, lifestyle moments.

Mood & Lighting: Bright, natural, aspirational.

Key Benefits: {{key_benefits}}

Target Audience: Home buyers, investors.

Ending: Logo and tagline "{{tagline}}" with call to action: {{call_to_action}}.

Style: Luxury property cinematic.',
1),

-- 6. Automotive
('Automotive Commercial', 'automotive',
'A high-performance commercial for {{car_model}}.

Scene: Open highway, city night drive, scenic landscapes.

Camera: Tracking shots, low angles, drone follow, speed motion blur.

Vehicle Focus: Exterior design, interior dashboard, driving experience.

Action: Car accelerating, turning, smooth driving.

Mood & Lighting: Dynamic, high contrast, dramatic lighting.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}".

Style: Premium automotive commercial, ultra-realistic, 4K.',
1),

-- 7. Fitness / Supplement
('Fitness & Supplement Ad', 'fitness',
'A powerful fitness commercial for {{product_name}}.

Scene: Gym environment with intense workout sessions.

Camera: Fast cuts, slow-motion muscle movement, sweat details.

Product Focus: {{product_name}} supplement, energy boost effect.

Action: Athletes training, lifting weights, pushing limits.

Mood & Lighting: High contrast, strong, energetic.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}".

Style: High-energy sports commercial, cinematic.',
1),

-- 8. Tech / Gadget
('Tech & Gadget Launch', 'tech',
'A sleek tech commercial for {{product_name}}.

Scene: Minimal futuristic environment.

Camera: Macro shots, smooth rotation, UI animation overlays.

Product Focus: Design, features, interface.

Action: User interacting with {{product_name}} seamlessly.

Mood & Lighting: Cool tones, modern, clean.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}".

Style: Futuristic, premium tech ad, 4K.',
1),

-- 9. E-Commerce / Sale Promo
('E-Commerce Sale Campaign', 'promo',
'A promotional video for {{brand_name}} sale campaign.

Scene: Fast-paced shopping visuals, product highlights.

Camera: Quick cuts, zoom transitions, energetic movement.

Product Focus: {{featured_products}}, discounts, deals.

Action: Customers browsing, buying, expressing excitement.

Mood & Lighting: Bright, vibrant, energetic.

Key Benefits: {{key_benefits}}

Target Audience: Online shoppers.

Ending: Bold SALE text — {{discount_offer}} — Call to action: {{call_to_action}}.

Style: Dynamic, social media ad, high energy.',
1),

-- 10. Wellness / Spa / Relaxation
('Wellness & Spa Promo', 'wellness',
'A relaxing wellness commercial for {{brand_name}}.

Scene: Nature, spa environment, calm setting.

Camera: Slow motion, smooth glide, soft focus.

Service Focus: {{services}}, relaxation oils, ambient atmosphere.

Action: Customer enjoying massage, breathing calmly, unwinding.

Mood & Lighting: Soft, natural, peaceful.

Key Benefits: {{key_benefits}}

Target Audience: {{target_audience}}

Ending: Logo and tagline "{{tagline}}".

Style: Calming, cinematic, premium wellness.',
1)

ON DUPLICATE KEY UPDATE `is_active` = VALUES(`is_active`);
