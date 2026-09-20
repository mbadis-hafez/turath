<?php

namespace App\Models;

use App\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;

class ArtistSocialLink extends Model
{
    use LogsChanges;

    public const PLATFORMS = ['website', 'instagram', 'x', 'facebook', 'youtube', 'tiktok', 'linkedin', 'snapchat', 'other'];

    /** @var array<int, string> */
    public $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_public' => 'boolean'];
    }

    public function activitySubjectLabel(): string
    {
        return "{$this->platform}: {$this->url}";
    }

    /**
     * @return array<string, array{ar: string, en: string}>
     */
    public static function activityFieldLabels(): array
    {
        return [
            'platform' => ['ar' => 'المنصة', 'en' => 'Platform'],
            'url' => ['ar' => 'الرابط', 'en' => 'URL'],
            'is_public' => ['ar' => 'ظاهر للعامة', 'en' => 'Public'],
        ];
    }
}
