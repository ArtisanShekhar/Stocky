<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    protected $fillable = [
        'enabled','store_name','logo_path','favicon_path',
        'primary_color','secondary_color','font_family',
        'hero_title','hero_subtitle','hero_image_path',
        'homepage_lineup','social_links',
        'default_warehouse_id','currency_code','language',
        'contact_email','contact_phone','contact_address',
        'seo_meta_title','seo_meta_description',
        'topbar_text_left','topbar_text_right','footer_text'
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'homepage_lineup' => 'array',
        'social_links' => 'array',
    ];
}
