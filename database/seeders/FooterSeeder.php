<?php

namespace Database\Seeders;

use App\Models\FooterSection;
use App\Models\FooterLink;
use Illuminate\Database\Seeder;

class FooterSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Catalog Section
        $catalog = FooterSection::firstOrCreate(
            ['title' => 'Catalog'],
            ['order' => 1, 'is_active' => true]
        );
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Skincare & Face'],    ['url' => '/collections/skincare-face',    'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Cleansing Soaps'],    ['url' => '/collections/cleansing-soaps',  'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Lykha Makeup'],       ['url' => '/collections/lykha-makeup',     'order' => 3]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Fragrances'],         ['url' => '/collections/fragrances',       'order' => 4]);

        // 2. Help Section
        $help = FooterSection::firstOrCreate(
            ['title' => 'Help'],
            ['order' => 2, 'is_active' => true]
        );
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Contact Us'],     ['url' => '/contact',         'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Track Order'],    ['url' => '/account',         'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'FAQ'],            ['url' => '/faq',             'order' => 3]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Shipping (India)'], ['url' => '/shipping-policy', 'order' => 4]);

        // 3. Policies Section
        $policies = FooterSection::firstOrCreate(
            ['title' => 'Policies'],
            ['order' => 3, 'is_active' => true]
        );
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Privacy Policy'],    ['url' => '/privacy-policy',   'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Terms & Conditions'], ['url' => '/terms-conditions', 'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Return Policy'],     ['url' => '/return-policy',    'order' => 3]);
    }
}
