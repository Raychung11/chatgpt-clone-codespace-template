-- ============================================================
-- PlotGold Malaysia — Memorial Parks Seed: Klang Valley
-- Migration: 004_klang_valley_parks.sql
-- 20 Memorial Parks & Columbaria (KL / Selangor)
-- Run once: mysql -u user -p plotgold < 004_klang_valley_parks.sql
-- ============================================================

USE plotgold;

INSERT IGNORE INTO memorial_parks
    (slug, name, name_zh, description, description_zh,
     city, state,
     phone, website,
     supported_religions,
     is_active, is_featured)
VALUES

-- ── NIRVANA GROUP ────────────────────────────────────────────────────────────

(
    'nirvana-memorial-park-shah-alam',
    'Nirvana Memorial Park Shah Alam',
    '富贵山庄纪念园（沙亚南）',
    'One of Malaysia\'s most recognised memorial parks, Nirvana Shah Alam offers beautifully landscaped burial plots and modern columbarium niches with excellent highway accessibility from the Klang Valley. Designed with feng shui principles and supported by professional bereavement services.',
    '马来西亚最知名的纪念园之一，沙亚南富贵山庄提供风水规划的墓地及现代骨灰龛，交通便利，毗邻巴生谷各大高速公路，配有专业哀伤辅导服务。',
    'Shah Alam', 'Selangor',
    '03-7890 5555', 'https://www.nirvana.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 1
),

(
    'nirvana-memorial-park-klang',
    'Nirvana Memorial Park Klang',
    '富贵山庄纪念园（巴生）',
    'Nirvana Memorial Park Klang provides dignified burial and columbarium services in the heart of Klang. The park features modern facilities, garden landscaping, and family-friendly amenities with convenient access from Klang town and the surrounding areas.',
    '巴生富贵山庄为巴生市中心提供庄重的殡葬及骨灰龛服务，园区设施现代，环境优美，交通便利。',
    'Klang', 'Selangor',
    '03-3385 1188', 'https://www.nirvana.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 1
),

(
    'nirvana-memorial-garden-semenyih',
    'Nirvana Memorial Garden Semenyih',
    '富贵纪念花园（士毛月）',
    'Rated among the highest in the region at 4.9 stars, Nirvana Memorial Garden Semenyih is a premium, serene memorial park set against lush green hills. It offers a wide range of burial plots and columbarium options, surrounded by tranquil natural landscaping and modern facilities.',
    '获得4.9星高度评价，士毛月富贵纪念花园是区域内最优质的纪念园之一，依山而建，环境幽静，提供多种墓地及骨灰龛选择，设施齐全。',
    'Semenyih', 'Selangor',
    '1-800-88-3778', 'https://www.nirvana.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 1
),

(
    'nirvana-memorial-park-semenyih',
    'Nirvana Memorial Park Semenyih',
    '富贵山庄纪念园（士毛月）',
    'Nirvana Memorial Park Semenyih offers burial plots and columbarium niches in the Semenyih area of Selangor, providing families with a peaceful resting place accessible from Kajang and surrounding townships.',
    '士毛月富贵山庄提供墓地及骨灰龛，地处加影周边地区，为家庭提供宁静安息之所。',
    'Semenyih', 'Selangor',
    NULL, 'https://www.nirvana.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'nirvana-centre-kuala-lumpur',
    'Nirvana Centre Kuala Lumpur',
    '富贵中心（吉隆坡）',
    'Nirvana Centre Kuala Lumpur is a premium columbarium facility in the city, offering modern niche options with excellent transport links and professional bereavement care services for urban families.',
    '吉隆坡富贵中心是市区内的优质骨灰龛设施，提供现代龛位，交通便利，配备专业哀伤服务。',
    'Kuala Lumpur', 'Kuala Lumpur',
    NULL, 'https://mynirvana.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'nirvana-klang',
    'Nirvana Klang',
    '富贵山庄（巴生）',
    'Nirvana Klang provides comprehensive memorial park and columbarium services in Klang, Selangor. Part of Malaysia\'s leading funeral services group, offering trusted bereavement support and modern facilities.',
    '巴生富贵山庄提供全面的纪念园及骨灰龛服务，隶属马来西亚领先的殡葬集团，提供专业哀伤支援。',
    'Klang', 'Selangor',
    NULL, 'https://www.nirvana-malaysia-kl.com',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'nirvana-memorial-center-sungai-besi',
    'Nirvana Memorial Center Sungai Besi',
    '富贵纪念中心（双溪毛糯）',
    'Located in the Sungai Besi area of Kuala Lumpur, Nirvana Memorial Center provides modern columbarium niches and memorial services with good accessibility from central KL and the southern corridor.',
    '位于吉隆坡双溪毛糯，富贵纪念中心提供现代骨灰龛及纪念服务，交通便利，毗邻吉隆坡市中心南部走廊。',
    'Kuala Lumpur', 'Kuala Lumpur',
    NULL, 'https://mynirvana.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'nirvana-shah-alam-columbarium',
    'Nirvana Shah Alam Columbarium',
    '富贵山庄骨灰龛（沙亚南）',
    'A dedicated columbarium facility in Shah Alam operated by the Nirvana group, offering a wide range of niche options in a modern, air-conditioned environment with professional grief support services.',
    '沙亚南富贵山庄骨灰龛提供多种龛位选择，设有现代化冷气环境，配备专业哀伤辅导。',
    'Shah Alam', 'Selangor',
    NULL, 'https://www.nirvana.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

-- ── FAIRY PARK GROUP ─────────────────────────────────────────────────────────

(
    'fairy-park-klang-memorial-park',
    'Fairy Park Klang Memorial Park',
    '仙境花园纪念园（巴生）',
    'Fairy Park Klang Memorial Park is a serene and well-maintained memorial park in Klang, rated 4.5 stars by visitors. It offers both traditional ground burial plots and columbarium niches, set amid tranquil garden landscaping with modern facilities.',
    '巴生仙境花园纪念园获访客4.5星好评，提供传统墓地及骨灰龛，园内绿意盎然，设施完善。',
    'Klang', 'Selangor',
    '019-992 8883', 'https://www.fairypark.asia',
    'buddhist,taoist,christian,multi_faith',
    1, 1
),

(
    'fairy-park-berhad-shah-alam',
    'Fairy Park Berhad',
    '仙境花园有限公司（沙亚南）',
    'Fairy Park Berhad in Shah Alam is an established memorial park offering burial plots and columbarium services in the Shah Alam area of Selangor. Rated 4.1 stars, the park provides a peaceful environment for families.',
    '沙亚南仙境花园有限公司是沙亚南雪兰莪州内历史悠久的纪念园，提供墓地及骨灰龛服务，获4.1星评价。',
    'Shah Alam', 'Selangor',
    '03-3343 9371', 'https://www.fairypark.asia',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'fairy-park-setia-alam',
    'Fairy Park Setia Alam',
    '仙境花园（双溪毛糯）',
    'Fairy Park Setia Alam is a well-rated memorial cemetery in the Setia Alam area of Shah Alam, offering a calm and dignified environment for burial services. Rated 4.6 stars by visitors for its upkeep and accessibility.',
    '双溪毛糯仙境花园获4.6星高评，是沙亚南双溪毛糯区内幽静庄重的纪念墓园，设施维护良好，交通便利。',
    'Shah Alam', 'Selangor',
    '010-818 3883', 'https://www.fairyparksetiaalam.com',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

-- ── INDEPENDENT PARKS ────────────────────────────────────────────────────────

(
    'bliss-gardens-shah-alam',
    'Bliss Gardens',
    '福乐苑（沙亚南）',
    'Bliss Gardens is a highly regarded memorial park and cemetery in Shah Alam, rated 4.3 stars. It offers a wide range of burial and memorial services in a beautifully maintained garden setting, serving multi-faith communities across the Klang Valley.',
    '沙亚南福乐苑获4.3星好评，是沙亚南地区备受推崇的纪念园与墓地，提供多种墓葬服务，环境优美，服务多元宗教社群。',
    'Shah Alam', 'Selangor',
    '010-775 6663', 'https://blissgardens.com.my',
    'buddhist,taoist,christian,non_religious,multi_faith',
    1, 1
),

(
    'semenyih-memorial-hills',
    'Semenyih Memorial Hills',
    '士毛月纪念山庄',
    'Semenyih Memorial Hills is a peaceful hillside memorial park in Semenyih, Selangor, rated 4.3 stars. Set among natural green hills, it provides families with a serene and dignified resting place, offering both burial plots and memorial services.',
    '士毛月纪念山庄获4.3星好评，依山而建，环境幽静，为家庭提供庄重安息之所，提供墓地及纪念服务。',
    'Semenyih', 'Selangor',
    '03-8724 9068', 'https://jing-an.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'universal-memorial-park-semenyih',
    'Universal Memorial Park',
    '环宇纪念园（士毛月）',
    'Universal Memorial Park in Semenyih is rated 4.6 stars and stands out as one of the top memorial parks in the Klang Valley. The park offers premium burial plots, columbarium niches, and excellent facilities in a green, tranquil environment.',
    '士毛月环宇纪念园获4.6星高评，是巴生谷顶级纪念园之一，提供优质墓地、骨灰龛及完善设施，环境宁静翠绿。',
    'Semenyih', 'Selangor',
    '012-282 7133', 'https://www.umpofficialshengji.com',
    'buddhist,taoist,christian,multi_faith',
    1, 1
),

(
    'tian-ning-columbarium-kajang',
    'Tian Ning Columbarium',
    '天宁骨灰塔（加影）',
    'Tian Ning Columbarium in Kajang is a respected 4.5-star columbarium facility offering a range of niche options. Well-maintained and easily accessible from Kajang, it provides a peaceful environment for families visiting and paying respects.',
    '加影天宁骨灰塔获4.5星评价，提供多种骨灰龛位，环境维护良好，交通便利，是家属祭拜的宁静之所。',
    'Kajang', 'Selangor',
    '012-673 3232', 'https://www.tianning.com.my',
    'buddhist,taoist,christian',
    1, 1
),

(
    'semenyih-memorial-hills-berhad-pj',
    'Semenyih Memorial Hills Berhad (Petaling Jaya)',
    '士毛月纪念山庄有限公司（八打灵再也）',
    'Semenyih Memorial Hills Berhad has a branch serving the Petaling Jaya area, offering burial and memorial park services for families in the central Klang Valley region. Rated 3.8 stars.',
    '士毛月纪念山庄有限公司设有八打灵再也分支，为巴生谷中部的家庭提供墓地及纪念园服务，获3.8星评价。',
    'Petaling Jaya', 'Selangor',
    '1-800-88-0068', 'https://jing-an.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

(
    'gui-yuan-columbarium-petaling-jaya',
    'Gui Yuan Columbarium',
    '归园骨灰龛（八打灵再也）',
    'Gui Yuan Columbarium in Petaling Jaya provides columbarium niche services, including options for Christian families. It offers a respectful and peaceful environment for families to pay tribute to their loved ones.',
    '八打灵再也归园骨灰龛提供骨灰龛位服务，包含适合基督徒的选项，为家属提供庄重宁静的祭拜环境。',
    'Petaling Jaya', 'Selangor',
    NULL, 'https://www.icarefuneralservices.com.my',
    'buddhist,taoist,christian,multi_faith',
    1, 0
),

-- ── TRADITIONAL CEMETERIES ───────────────────────────────────────────────────

(
    'kwong-tong-cemetery-kuala-lumpur',
    'Kwong Tong Cemetery',
    '广东义山（吉隆坡）',
    'Kwong Tong Cemetery is one of Kuala Lumpur\'s oldest and most established Chinese cemeteries, serving the Cantonese community since the colonial era. Rated 4.2 stars, it offers both traditional burial plots and a columbarium pagoda, set amid mature trees in the heart of KL.',
    '广东义山是吉隆坡历史最悠久的华人墓地之一，自殖民时代起服务广东社群，获4.2星评价，提供传统墓穴及骨灰塔，位于吉隆坡市区繁茂树林之中。',
    'Kuala Lumpur', 'Kuala Lumpur',
    '03-2141 0838', 'https://ktc.org.my',
    'buddhist,taoist,non_religious',
    1, 1
),

(
    'kl-hokkien-cemetery',
    'KL Hokkien Cemetery',
    '吉隆坡福建义山',
    'KL Hokkien Cemetery is a highly rated traditional cemetery in Kuala Lumpur, serving the Hokkien (Fujian) community. Rated 4.7 stars, it is one of the most well-maintained heritage cemeteries in the city, with both ground burial plots and memorial facilities.',
    '吉隆坡福建义山获4.7星高评，是吉隆坡服务福建社群的传统义山，维护精良，提供传统墓穴及纪念设施。',
    'Kuala Lumpur', 'Kuala Lumpur',
    '018-201 0602', NULL,
    'buddhist,taoist,non_religious',
    1, 1
),

(
    'xiao-en-centre-cheras',
    'Xiao En Centre',
    '孝恩中心（瑟帝亚旺沙）',
    'Xiao En Centre in Cheras, Kuala Lumpur is a renowned multi-service funeral and columbarium centre rated 4.5 stars. It offers a comprehensive range of services including funeral planning, columbarium niches, and memorial halls, making it a one-stop centre for bereavement care in the Klang Valley.',
    '位于吉隆坡瑟帝亚旺沙的孝恩中心获4.5星好评，是知名的一站式殡仪及骨灰龛中心，提供殡葬策划、骨灰龛位及灵堂等全面服务。',
    'Kuala Lumpur', 'Kuala Lumpur',
    '03-9145 3888', 'https://www.xiao-en.com',
    'buddhist,taoist,christian,multi_faith',
    1, 1
);
