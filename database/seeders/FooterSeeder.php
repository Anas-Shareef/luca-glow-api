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
        $catalog = FooterSection::firstOrCreate(['title' => 'Catalog'], ['order' => 1, 'is_active' => true]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Skincare & Face'], ['url' => 'skincare-face', 'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Cleansing Soaps'], ['url' => 'cleansing-soaps', 'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Lykha Makeup'], ['url' => 'lykha-makeup', 'order' => 3]);
        FooterLink::firstOrCreate(['footer_section_id' => $catalog->id, 'label' => 'Fragrances'], ['url' => 'fragrances', 'order' => 4]);

        // 2. Help Section
        $help = FooterSection::firstOrCreate(['title' => 'Help'], ['order' => 2, 'is_active' => true]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Contact'], ['url' => '_contact', 'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Track Order'], ['url' => '_account', 'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'FAQ'], ['url' => '_faq', 'order' => 3]);
        FooterLink::firstOrCreate(['footer_section_id' => $help->id, 'label' => 'Shipping (India)'], ['url' => '_shipping', 'order' => 4]);

        // 3. Policies Section
        $policies = FooterSection::firstOrCreate(['title' => 'Policies'], ['order' => 3, 'is_active' => true]);
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Privacy Policy'], ['url' => '_privacy', 'order' => 1]);
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Terms & Conditions'], ['url' => '_terms', 'order' => 2]);
        FooterLink::firstOrCreate(['footer_section_id' => $policies->id, 'label' => 'Return Policy'], ['url' => '_return', 'order' => 3]);
    }
}
