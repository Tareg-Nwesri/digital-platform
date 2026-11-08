<?php

namespace App\Console\Commands;

use App\Domain\Content\Services\PostService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('posts:publish-due')]
#[Description('Publish scheduled news posts whose time has come')]
class PublishDuePosts extends Command
{
    public function handle(PostService $posts): int
    {
        $count = $posts->publishDue();
        $this->info("Published {$count} post(s).");

        return self::SUCCESS;
    }
}
